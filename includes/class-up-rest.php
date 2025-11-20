<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_REST {
    public static function register_routes() {
        // NOTE: /ingest route moved to class-up-rest-ingest.php for GTM forwarder
        // register_rest_route( 'up/v1', '/ingest', array(
        //     'methods' => 'POST',
        //     'callback' => array( __CLASS__, 'ingest' ),
        //     'permission_callback' => '__return_true',
        // ) );

        register_rest_route( 'up/v1', '/test', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'test_forward' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // process queue (admin-only)
        register_rest_route( 'up/v1', '/process-queue', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'process_queue' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // queue status (admin-only)
        register_rest_route( 'up/v1', '/queue/status', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'queue_status' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: list queue items
        register_rest_route( 'up/v1', '/queue/items', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'queue_items' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: list dead-letter items
        register_rest_route( 'up/v1', '/queue/deadletter', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'deadletter_items' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: retry dead-letter item (move back to queue)
        register_rest_route( 'up/v1', '/queue/deadletter/retry', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'deadletter_retry' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: delete dead-letter item
        register_rest_route( 'up/v1', '/queue/deadletter/delete', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'deadletter_delete' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: get logs
        register_rest_route( 'up/v1', '/logs', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'logs' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: retry item
        register_rest_route( 'up/v1', '/queue/retry', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'queue_retry' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // admin: delete item
        register_rest_route( 'up/v1', '/queue/delete', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'queue_delete' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // public health/diagnostics (non-sensitive) — safe for uptime checks
        register_rest_route( 'up/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'health' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public static function ingest( WP_REST_Request $request ) {
        $headers = $request->get_headers();
        $incoming_secret = isset( $headers['x-up-secret'] ) ? ( is_array( $headers['x-up-secret'] ) ? $headers['x-up-secret'][0] : $headers['x-up-secret'] ) : '';
        $incoming_nonce  = isset( $headers['x-wp-nonce'] ) ? ( is_array( $headers['x-wp-nonce'] ) ? $headers['x-wp-nonce'][0] : $headers['x-wp-nonce'] ) : '';

        $stored = defined( 'UP_SERVER_SECRET' ) ? UP_SERVER_SECRET : UP_Settings::get( 'server_secret', '' );

        // Accept in priority: server secret, WP REST nonce, or same-origin anonymous (strictly rate-limited)
        $authorized = false;
        if ( ! empty( $stored ) && ! empty( $incoming_secret ) && hash_equals( (string) $stored, (string) $incoming_secret ) ) {
            $authorized = true; // token-based
        } elseif ( ! empty( $incoming_nonce ) && wp_verify_nonce( $incoming_nonce, 'wp_rest' ) ) {
            $authorized = true; // logged-in REST nonce
        } else {
            // Fallback for anonymous front-end: require same-origin request (Origin/Referer host matches site)
            $site_host = parse_url( home_url(), PHP_URL_HOST );
            $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
            $referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
            $hdr = $origin ? $origin : $referer;
            if ( $hdr ) {
                $hdr_host = parse_url( $hdr, PHP_URL_HOST );
                if ( $hdr_host && $site_host && strcasecmp( $hdr_host, $site_host ) === 0 ) {
                    $authorized = true; // same-origin anonymous
                }
            }
        }

        if ( ! $authorized ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        // Basic rate-limiting to reduce abuse: per-IP and per-token counters using transients
        // Configure limits via UP_Settings keys: 'rate_limit_ip_per_min' and 'rate_limit_token_per_min'
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
        $ip_key = 'up_ingest_ip_' . md5( $ip );
        $ip_limit = 60;
        if ( class_exists( 'UP_Settings' ) ) {
            $ip_limit = intval( UP_Settings::get( 'rate_limit_ip_per_min', 60 ) );
        }
        $retry_after = 60;
        if ( class_exists( 'UP_Settings' ) ) {
            $retry_after = max( 1, intval( UP_Settings::get( 'retry_after_seconds', 60 ) ) );
        }
        $ip_count = intval( get_transient( $ip_key ) );
        $ip_count++;
        set_transient( $ip_key, $ip_count, $retry_after );
        if ( $ip_count > $ip_limit ) {
            if ( class_exists( 'UP_CAPI' ) ) {
                UP_CAPI::log( 'warn', sprintf( 'Rate-limited ingest by IP %s (%d/%d)', $ip, $ip_count, $ip_limit ) );
            }
            $resp = new WP_REST_Response( array( 'error' => 'rate_limited', 'scope' => 'ip', 'limit' => $ip_limit ), 429 );
            $resp->header( 'Retry-After', $retry_after );
            return $resp;
        }

        if ( ! empty( $incoming_secret ) ) {
            $tok_key = 'up_ingest_tok_' . md5( $incoming_secret );
            $tok_limit = 600;
            if ( class_exists( 'UP_Settings' ) ) {
                $tok_limit = intval( UP_Settings::get( 'rate_limit_token_per_min', 600 ) );
            }
            $tok_count = intval( get_transient( $tok_key ) );
            $tok_count++;
            set_transient( $tok_key, $tok_count, $retry_after );
            if ( $tok_count > $tok_limit ) {
                if ( class_exists( 'UP_CAPI' ) ) {
                    UP_CAPI::log( 'warn', sprintf( 'Rate-limited ingest by token %s (%d/%d)', substr( $incoming_secret, 0, 8 ), $tok_count, $tok_limit ) );
                }
                $resp = new WP_REST_Response( array( 'error' => 'rate_limited', 'scope' => 'token', 'limit' => $tok_limit ), 429 );
                $resp->header( 'Retry-After', $retry_after );
                return $resp;
            }
        }

        $payload = $request->get_json_params();
        if ( empty( $payload ) || ! is_array( $payload ) ) {
            return new WP_REST_Response( array( 'error' => 'Empty or invalid payload' ), 400 );
        }

        // Optional consent check (if client provides consent flag and it's false, reject)
        if ( isset( $payload['consent'] ) && $payload['consent'] !== true && $payload['consent'] !== 'true' ) {
            return new WP_REST_Response( array( 'error' => 'Consent required' ), 403 );
        }

        // Server-side PII hashing: convert email/phone to hashed fields and remove raw values
        if ( isset( $payload['user_data'] ) && is_array( $payload['user_data'] ) ) {
            $ud = $payload['user_data'];
            $clean = array();
            foreach ( $ud as $k => $v ) {
                if ( in_array( $k, array( 'email', 'phone' ), true ) && is_string( $v ) ) {
                    $val = strtolower( trim( wp_unslash( $v ) ) );
                    $clean[ $k . '_hash' ] = hash( 'sha256', $val );
                } else {
                    $clean[ $k ] = $v;
                }
            }
            $payload['user_data'] = $clean;
        }

        // Normalize optional source URL for adapters
        if ( empty( $payload['source_url'] ) ) {
            $payload['source_url'] = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : home_url();
        }

        // Normalize platform and event_name
        $platform = isset( $payload['platform'] ) ? sanitize_text_field( $payload['platform'] ) : 'generic';
        $event_name = isset( $payload['event_name'] ) ? sanitize_text_field( $payload['event_name'] ) : ( isset( $payload['event'] ) ? sanitize_text_field( $payload['event'] ) : 'event' );

        // Accept optional client-supplied event_id (sanitized) for idempotency
        if ( isset( $payload['event_id'] ) && is_string( $payload['event_id'] ) ) {
            $payload['event_id'] = substr( sanitize_text_field( $payload['event_id'] ), 0, 64 );
        }

        // Enqueue event for async processing using UP_CAPI
        if ( class_exists( 'UP_CAPI' ) && method_exists( 'UP_CAPI', 'enqueue_event' ) ) {
            $ok = UP_CAPI::enqueue_event( $platform, $event_name, $payload );
            if ( $ok ) {
                return new WP_REST_Response( array( 'ok' => true, 'queued' => true, 'event_id' => isset( $payload['event_id'] ) ? $payload['event_id'] : null ), 202 );
            }
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'enqueue_failed' ), 500 );
        }

        return new WP_REST_Response( array( 'ok' => false, 'error' => 'capi_unavailable' ), 500 );
    }

    public static function test_forward( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $event_key = isset( $params['event'] ) ? sanitize_text_field( $params['event'] ) : 'purchase';

        $sample = array(
            'event_name' => $event_key,
            'event_time' => time(),
            'event_id'   => uniqid( 'up_', true ),
            'user_data'  => array( 'email' => hash( 'sha256', 'test@example.com' ) ),
            'custom_data'=> array( 'value' => 9.99, 'currency' => 'USD' ),
        );

        $results = array();
        if ( class_exists( 'UP_CAPI' ) ) {
            // test endpoint should attempt an immediate blocking send
            $results[] = UP_CAPI::send_event( 'test', $event_key, $sample, true );
        }

        return new WP_REST_Response( array( 'ok' => true, 'results' => $results ), 200 );
    }

    public static function process_queue( WP_REST_Request $request ) {
        if ( ! class_exists( 'UP_CAPI' ) ) {
            return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        }
        $processed = UP_CAPI::process_queue( 50 );
        $last = get_option( 'up_capi_last_processed', 0 );
        return new WP_REST_Response( array( 'ok' => true, 'processed' => $processed, 'last_processed' => $last ), 200 );
    }

    public static function queue_status( WP_REST_Request $request ) {
        if ( ! class_exists( 'UP_CAPI' ) ) {
            return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        }
        $len = UP_CAPI::get_queue_length();
        $last = get_option( 'up_capi_last_processed', 0 );
        return new WP_REST_Response( array( 'ok' => true, 'length' => $len, 'last_processed' => $last ), 200 );
    }

    public static function queue_items( WP_REST_Request $request ) {
        $limit = intval( $request->get_param( 'limit' ) ?? 20 );
        $offset = intval( $request->get_param( 'offset' ) ?? 0 );
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        $rows = UP_CAPI::list_queue( $limit, $offset );
        return new WP_REST_Response( array( 'ok' => true, 'items' => $rows ), 200 );
    }

    public static function deadletter_items( WP_REST_Request $request ) {
        $limit = intval( $request->get_param( 'limit' ) ?? 20 );
        $offset = intval( $request->get_param( 'offset' ) ?? 0 );
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        if ( ! method_exists( 'UP_CAPI', 'list_deadletter' ) ) return new WP_REST_Response( array( 'error' => 'no_deadletter' ), 500 );
        $rows = UP_CAPI::list_deadletter( $limit, $offset );
        return new WP_REST_Response( array( 'ok' => true, 'items' => $rows ), 200 );
    }

    public static function deadletter_retry( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $id = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
        if ( ! $id ) return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        if ( ! method_exists( 'UP_CAPI', 'retry_deadletter' ) ) return new WP_REST_Response( array( 'error' => 'no_deadletter' ), 500 );
        $ok = UP_CAPI::retry_deadletter( $id );
        return new WP_REST_Response( array( 'ok' => (bool) $ok ), 200 );
    }

    public static function deadletter_delete( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $id = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
        if ( ! $id ) return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        if ( ! method_exists( 'UP_CAPI', 'delete_deadletter' ) ) return new WP_REST_Response( array( 'error' => 'no_deadletter' ), 500 );
        $ok = UP_CAPI::delete_deadletter( $id );
        return new WP_REST_Response( array( 'ok' => (bool) $ok ), 200 );
    }

    public static function logs( WP_REST_Request $request ) {
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        if ( ! method_exists( 'UP_CAPI', 'get_logs' ) ) return new WP_REST_Response( array( 'error' => 'no_logs' ), 500 );
        $logs = UP_CAPI::get_logs();
        return new WP_REST_Response( array( 'ok' => true, 'logs' => $logs ), 200 );
    }

    public static function queue_retry( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $id = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
        if ( ! $id ) return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        $ok = UP_CAPI::retry_item( $id );
        return new WP_REST_Response( array( 'ok' => (bool) $ok ), 200 );
    }

    public static function queue_delete( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $id = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
        if ( ! $id ) return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
        if ( ! class_exists( 'UP_CAPI' ) ) return new WP_REST_Response( array( 'error' => 'no_capi' ), 500 );
        $ok = UP_CAPI::delete_item( $id );
        return new WP_REST_Response( array( 'ok' => (bool) $ok ), 200 );
    }

    /**
     * Lightweight health / readiness probe.
     * Returns queue & dead-letter counts, last processed timestamp, enabled platform flags, recent log snippet.
     * No sensitive tokens are exposed.
     */
    public static function health( WP_REST_Request $request ) {
        $queue_len = 0;
        $deadletter_len = 0;
        $last_processed = get_option( 'up_capi_last_processed', 0 );
        if ( class_exists( 'UP_CAPI' ) ) {
            $queue_len = UP_CAPI::get_queue_length();
            global $wpdb; $dl_table = $wpdb->prefix . 'up_capi_deadletter';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$dl_table}'" ) === $dl_table ) {
                $deadletter_len = intval( $wpdb->get_var( "SELECT COUNT(1) FROM {$dl_table}" ) );
            }
        }
        $platforms = array();
        if ( class_exists( 'UP_Settings' ) ) {
            $platforms = array(
                'meta' => UP_Settings::get( 'enable_meta', 'no' ) === 'yes',
                'tiktok' => UP_Settings::get( 'enable_tiktok', 'no' ) === 'yes',
                'google_ads' => UP_Settings::get( 'enable_google_ads', 'no' ) === 'yes',
                'snapchat' => UP_Settings::get( 'enable_snapchat', 'no' ) === 'yes',
                'pinterest' => UP_Settings::get( 'enable_pinterest', 'no' ) === 'yes',
            );
        }
        $logs = array();
        if ( class_exists( 'UP_CAPI' ) && method_exists( 'UP_CAPI', 'get_logs' ) ) {
            $all = UP_CAPI::get_logs();
            $logs = array_slice( $all, 0, 10 ); // recent 10
        }
        return new WP_REST_Response( array(
            'ok' => true,
            'queue_length' => $queue_len,
            'deadletter_length' => $deadletter_len,
            'last_processed' => $last_processed,
            'platforms' => $platforms,
            'logs' => $logs,
            'timestamp' => time(),
        ), 200 );
    }

    // Simple admin renderer for queue dashboard (can be expanded into full SPA)
    public static function render_queue_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="up-admin-queue-dashboard" role="region" aria-labelledby="up-queue-title" style="background:#0f1724;color:#e6eef6;padding:28px;border-radius:14px;max-width:1100px;margin:24px auto;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
            <h2 id="up-queue-title" style="margin:0 0 12px;font-size:20px;">Ultra Pixels — Event Queue & Errors</h2>
            <p id="up-queue-desc" style="margin:0 0 18px;color:#9fb0c8;">Quick view of queued events, recent failures and actions. Use the buttons to process the queue or inspect dead-lettered items for troubleshooting.</p>

            <div id="up-queue-status" aria-live="polite" style="margin-bottom:18px;display:flex;gap:12px;align-items:center;">
                <button id="up-queue-refresh" class="button" aria-controls="up-queue-items up-deadletter-items up-error-entries">Refresh</button>
                <button id="up-process-queue" class="button" aria-controls="up-queue-items">Process Now</button>
                <span id="up-process-result" role="status" aria-live="polite" style="margin-left:8px;color:#9fb0c8"></span>
            </div>

            <div id="up-queue-items" style="background:#071022;padding:12px;border-radius:8px;" aria-label="Queued events">
                <em style="color:#789;">Loading queue items…</em>
            </div>

            <div id="up-deadletter-section" style="margin-top:20px;background:#071022;padding:12px;border-radius:8px;color:#e6eef6;" role="region" aria-labelledby="up-deadletter-title">
                <h3 id="up-deadletter-title" style="margin:0 0 8px;color:#ffb86b;">Dead-lettered Events</h3>
                <div id="up-deadletter-items" aria-live="polite"><em style="color:#789;">Loading dead-letter items…</em></div>
            </div>

            <div id="up-error-log" style="margin-top:20px;background:#071022;padding:12px;border-radius:8px;color:#f8d7da;display:none;" role="region" aria-labelledby="up-log-title">
                <h3 id="up-log-title" style="margin:0 0 8px;color:#f8d7da;">Errors & Logs</h3>
                <div id="up-error-entries" aria-live="polite"></div>
            </div>
        </div>
        <?php
    }
}
