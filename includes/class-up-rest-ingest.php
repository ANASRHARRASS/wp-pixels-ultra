<?php
/**
 * REST ingest route for GTM/browser -> plugin server ingestion.
 * Registers POST /wp-json/up/v1/ingest and enqueues events via UP_CAPI::enqueue_event()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UP_REST_Ingest {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            'up/v1',
            '/ingest',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'ingest_event_callback' ),
                'permission_callback' => array( __CLASS__, 'permission_callback' ),
            )
        );
    }

    public static function permission_callback( $request ) {
        // 1) Allow server secret if provided
        $incoming_secret = $request->get_header( 'x-up-secret' );
        $stored_secret   = defined( 'UP_SERVER_SECRET' ) ? UP_SERVER_SECRET : ( class_exists( 'UP_Settings' ) ? UP_Settings::get( 'server_secret', '' ) : '' );
        if ( ! empty( $incoming_secret ) && ! empty( $stored_secret ) && hash_equals( (string) $stored_secret, (string) $incoming_secret ) ) {
            return true;
        }

        // 2) Accept valid REST nonce if present
        $nonce = $request->get_header( 'x-wp-nonce' );
        if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        // 3) Same-origin anonymous fallback with rate limiting — check Origin/Referer host match
        $site_host = parse_url( home_url(), PHP_URL_HOST );
        $origin    = $request->get_header( 'origin' );
        $referer   = $request->get_header( 'referer' );
        $hdr       = $origin ? $origin : $referer;
        if ( $hdr ) {
            $hdr_host = parse_url( $hdr, PHP_URL_HOST );
            if ( $hdr_host && $site_host && strcasecmp( $hdr_host, $site_host ) === 0 ) {
                return true;
            }
        }

        return new WP_Error( 'rest_forbidden', __( 'Unauthorized ingest request.' ), array( 'status' => 401 ) );
    }

    public static function ingest_event_callback( WP_REST_Request $request ) {
        // --- BEGIN Rate limiting (per-IP and per-token) ---
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
        $token = $request->get_header( 'x-up-secret' );

        // Get rate limit settings (with sane defaults)
        $ip_limit = ( class_exists( 'UP_Settings' ) ? intval( UP_Settings::get( 'rate_limit_ip_per_min', 60 ) ) : 60 );
        $token_limit = ( class_exists( 'UP_Settings' ) ? intval( UP_Settings::get( 'rate_limit_token_per_min', 300 ) ) : 300 );
        $retry_after = ( class_exists( 'UP_Settings' ) ? intval( UP_Settings::get( 'retry_after_seconds', 60 ) ) : 60 );

        // Per-IP rate limiting
        $ip_key = 'up_ingest_ip_' . md5( $ip );
        $ip_count = (int) get_transient( $ip_key );
        if ( $ip_count >= $ip_limit ) {
            if ( class_exists( 'UP_CAPI' ) ) {
                UP_CAPI::log( 'warn', sprintf( 'Rate-limited ingest by IP %s (%d/%d)', $ip, $ip_count, $ip_limit ) );
            }
            $resp = new WP_REST_Response( array( 'error' => 'rate_limited', 'scope' => 'ip', 'limit' => $ip_limit ), 429 );
            $resp->header( 'Retry-After', $retry_after );
            return $resp;
        }
        set_transient( $ip_key, $ip_count + 1, $retry_after );

        // Per-token rate limiting (if token provided)
        if ( ! empty( $token ) ) {
            $token_key = 'up_ingest_token_' . md5( $token );
            $token_count = (int) get_transient( $token_key );
            if ( $token_count >= $token_limit ) {
                if ( class_exists( 'UP_CAPI' ) ) {
                    UP_CAPI::log( 'warn', sprintf( 'Rate-limited ingest by token %s (%d/%d)', substr( $token, 0, 8 ), $token_count, $token_limit ) );
                }
                $resp = new WP_REST_Response( array( 'error' => 'rate_limited', 'scope' => 'token', 'limit' => $token_limit ), 429 );
                $resp->header( 'Retry-After', $retry_after );
                return $resp;
            }
            set_transient( $token_key, $token_count + 1, $retry_after );
        }
        // --- END Rate limiting ---

        $params = $request->get_json_params();
        if ( empty( $params ) || ! is_array( $params ) ) {
            return new WP_Error( 'invalid_payload', __( 'Invalid JSON payload.' ), array( 'status' => 400 ) );
        }

        // Accept either 'event' or 'event_name'
        if ( empty( $params['event'] ) && empty( $params['event_name'] ) ) {
            return new WP_Error( 'missing_event_name', __( 'event or event_name is required.' ), array( 'status' => 400 ) );
        }
        $event = sanitize_text_field( wp_unslash( $params['event'] ?? $params['event_name'] ) );

        if ( empty( $params['event_id'] ) ) {
            return new WP_Error( 'missing_event_id', __( 'event_id is required.' ), array( 'status' => 400 ) );
        }
        $event_id = sanitize_text_field( wp_unslash( $params['event_id'] ) );

        // custom_data is optional, default to empty array if not provided
        $custom_data = isset( $params['custom_data'] ) ? self::sanitize_custom_data( $params['custom_data'] ) : array();

        // Optional consent enforcement: if provided and false, reject
        if ( isset( $params['consent'] ) && $params['consent'] !== true && $params['consent'] !== 'true' ) {
            return new WP_Error( 'consent_required', __( 'Consent required' ), array( 'status' => 403 ) );
        }

        // Hash PII in user_data if present; support alias 'user' from GTM tags
        $user_data = array();
        if ( isset( $params['user_data'] ) && is_array( $params['user_data'] ) ) {
            $user_data = $params['user_data'];
        } elseif ( isset( $params['user'] ) && is_array( $params['user'] ) ) {
            $user_data = $params['user'];
        }
        if ( ! empty( $user_data ) ) {
            $clean_ud = array();
            
            // Handle email
            if ( isset( $user_data['email'] ) && is_string( $user_data['email'] ) ) {
                $val = strtolower( trim( wp_unslash( $user_data['email'] ) ) );
                $clean_ud['email_hash'] = hash( 'sha256', $val );
            }
            
            // Handle phone, prefer 'phone' over 'phone_number'
            $phone_val = null;
            if ( isset( $user_data['phone'] ) && is_string( $user_data['phone'] ) ) {
                $phone_val = $user_data['phone'];
            } elseif ( isset( $user_data['phone_number'] ) && is_string( $user_data['phone_number'] ) ) {
                $phone_val = $user_data['phone_number'];
            }
            if ( $phone_val !== null ) {
                $val = strtolower( trim( wp_unslash( $phone_val ) ) );
                $clean_ud['phone_hash'] = hash( 'sha256', preg_replace( '/\D+/', '', $val ) );
            }
            
            // Copy other fields, sanitizing appropriately
            foreach ( $user_data as $k => $v ) {
                if ( in_array( $k, array( 'email', 'phone', 'phone_number' ), true ) ) {
                    continue;
                }
                $clean_ud[ sanitize_text_field( $k ) ] = is_string( $v ) ? sanitize_text_field( $v ) : $v;
            }
            
            $user_data = $clean_ud;
        }

        // Enrich server-side
        $client_ip = self::get_client_ip( $request );
        $user_agent = self::get_user_agent( $request );
        $event_time = time();

        // Normalize source URL
        $source_url = '';
        if ( ! empty( $params['source_url'] ) ) {
            $source_url = esc_url_raw( $params['source_url'] );
        } else {
            $ref = $request->get_header( 'referer' );
            if ( $ref ) $source_url = esc_url_raw( $ref );
        }

        $validated_payload = array(
            'event_name'        => $event,
            'event_id'          => $event_id,
            'custom_data'       => $custom_data,
            'user_data'         => $user_data,
            'client_ip_address' => $client_ip,
            'client_user_agent' => $user_agent,
            'event_time'        => $event_time,
            'received_via'      => 'wp_rest_ingest',
            'source_url'        => $source_url,
        );

        // Use existing queue mechanism
        if ( class_exists( 'UP_CAPI' ) && method_exists( 'UP_CAPI', 'enqueue_event' ) ) {
            try {
                $platform = isset( $params['platform'] ) ? sanitize_text_field( $params['platform'] ) : 'generic';
                UP_CAPI::enqueue_event( $platform, $event, $validated_payload );
            } catch ( Throwable $e ) {
                error_log( '[UP_REST_Ingest] enqueue_event exception: ' . $e->getMessage() );
                return new WP_Error( 'enqueue_failed', __( 'Failed to enqueue event.' ), array( 'status' => 500 ) );
            }
        } else {
            error_log( '[UP_REST_Ingest] UP_CAPI::enqueue_event not available.' );
            return new WP_Error( 'server_misconfigured', __( 'Server queue not available.' ), array( 'status' => 500 ) );
        }

        $response_body = array(
            'accepted' => true,
            'message'  => 'Event queued for processing',
            'event_id' => $event_id,
        );

        return new WP_REST_Response( $response_body, 202 );
    }

    protected static function sanitize_custom_data( $data ) {
        if ( is_array( $data ) ) {
            $out = array();
            foreach ( $data as $k => $v ) {
                $key = is_string( $k ) ? sanitize_text_field( $k ) : $k;
                $out[ $key ] = self::sanitize_custom_data( $v );
            }
            return $out;
        }
        if ( is_bool( $data ) ) return $data;
        if ( is_numeric( $data ) ) return $data + 0;
        if ( is_string( $data ) ) return sanitize_text_field( wp_unslash( $data ) );
        // For unsupported types (objects, null, etc.), return null
        return null;
    }

    protected static function get_client_ip( $request ) {
        $xff = $request->get_header( 'x-forwarded-for' );
        if ( $xff ) {
            $parts = explode( ',', $xff );
            if ( ! empty( $parts ) ) return trim( sanitize_text_field( $parts[0] ) );
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return sanitize_text_field( $remote );
    }

    protected static function get_user_agent( $request ) {
        $ua = $request->get_header( 'user-agent' );
        if ( $ua ) return sanitize_text_field( $ua );
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        return '';
    }
}

UP_REST_Ingest::init();
