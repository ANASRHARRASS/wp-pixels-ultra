<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_REST {
    public static function register_routes() {
        // Admin endpoints requiring manage_options capability
        $admin_permission = function() {
            return current_user_can( 'manage_options' );
        };

        // Test event endpoint
        register_rest_route( 'up/v1', '/test', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'test_forward' ),
            'permission_callback' => $admin_permission,
        ) );

        // Queue management endpoints
        register_rest_route( 'up/v1', '/process-queue', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'process_queue' ),
            'permission_callback' => $admin_permission,
        ) );

        register_rest_route( 'up/v1', '/queue/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'queue_status' ),
            'permission_callback' => $admin_permission,
        ) );

        register_rest_route( 'up/v1', '/queue/items', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'queue_items' ),
            'permission_callback' => $admin_permission,
        ) );

        register_rest_route( 'up/v1', '/queue/retry', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'queue_retry' ),
            'permission_callback' => $admin_permission,
        ) );

        register_rest_route( 'up/v1', '/queue/delete', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'queue_delete' ),
            'permission_callback' => $admin_permission,
        ) );

        // Dead-letter endpoints
        register_rest_route( 'up/v1', '/queue/deadletter', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'deadletter_items' ),
            'permission_callback' => $admin_permission,
        ) );

        register_rest_route( 'up/v1', '/queue/deadletter/retry', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'deadletter_retry' ),
            'permission_callback' => $admin_permission,
        ) );

        register_rest_route( 'up/v1', '/queue/deadletter/delete', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'deadletter_delete' ),
            'permission_callback' => $admin_permission,
        ) );

        // Logs endpoint
        register_rest_route( 'up/v1', '/logs', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'logs' ),
            'permission_callback' => $admin_permission,
        ) );

        // Health endpoint (public, no authentication required)
        register_rest_route( 'up/v1', '/health', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'health' ),
            'permission_callback' => '__return_true',
        ) );
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
