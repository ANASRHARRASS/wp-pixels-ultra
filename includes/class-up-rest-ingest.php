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
        $nonce = $request->get_header( 'x-wp-nonce' );
        $nonce_action = sanitize_key( 'wp_rest' );

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Invalid or missing X-WP-Nonce.' ), array( 'status' => 401 ) );
        }
        return true;
    }

    public static function ingest_event_callback( WP_REST_Request $request ) {
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

        if ( ! isset( $params['custom_data'] ) ) {
            return new WP_Error( 'missing_custom_data', __( 'custom_data is required (can be empty object/array).' ), array( 'status' => 400 ) );
        }
        $custom_data = self::sanitize_custom_data( $params['custom_data'] );

        // Enrich server-side
        $client_ip = self::get_client_ip( $request );
        $user_agent = self::get_user_agent( $request );
        $event_time = time();

        $validated_payload = array(
            'event_name'        => $event,
            'event_id'          => $event_id,
            'custom_data'       => $custom_data,
            'client_ip_address' => $client_ip,
            'client_user_agent' => $user_agent,
            'event_time'        => $event_time,
            'received_via'      => 'wp_rest_ingest',
        );

        // Use existing queue mechanism
        if ( class_exists( 'UP_CAPI' ) && method_exists( 'UP_CAPI', 'enqueue_event' ) ) {
            try {
                UP_CAPI::enqueue_event( 'generic', $event, $validated_payload );
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
        return sanitize_text_field( wp_json_encode( $data ) );
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
