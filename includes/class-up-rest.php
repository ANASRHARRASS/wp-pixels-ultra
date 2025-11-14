<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_REST {
    public static function register_routes() {
        register_rest_route( 'up/v1', '/ingest', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'ingest' ),
            'permission_callback' => '__return_true',
        ) );

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
    }

    public static function ingest( WP_REST_Request $request ) {
        $headers = $request->get_headers();
        $incoming_secret = isset( $headers['x-up-secret'] ) ? ( is_array( $headers['x-up-secret'] ) ? $headers['x-up-secret'][0] : $headers['x-up-secret'] ) : '';
        $incoming_nonce  = isset( $headers['x-wp-nonce'] ) ? ( is_array( $headers['x-wp-nonce'] ) ? $headers['x-wp-nonce'][0] : $headers['x-wp-nonce'] ) : '';

        $stored = defined( 'UP_SERVER_SECRET' ) ? UP_SERVER_SECRET : UP_Settings::get( 'server_secret', '' );

        // Accept either a configured server secret (X-UP-SECRET) OR a valid WP REST nonce
        $authorized = false;
        if ( ! empty( $stored ) && ! empty( $incoming_secret ) && hash_equals( (string) $stored, (string) $incoming_secret ) ) {
            $authorized = true;
        } elseif ( ! empty( $incoming_nonce ) && wp_verify_nonce( $incoming_nonce, 'wp_rest' ) ) {
            $authorized = true;
        }

        if ( ! $authorized ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $payload = $request->get_json_params();
        if ( empty( $payload ) ) {
            return new WP_REST_Response( array( 'error' => 'Empty payload' ), 400 );
        }

        // If UP_CAPI exists, try sending to configured CAPI endpoint. Otherwise return payload for inspection.
        $results = array();
        if ( class_exists( 'UP_CAPI' ) ) {
            // attempt a best-effort send; if payload contains platform/metadata use them, otherwise send generic
            $platform = isset( $payload['platform'] ) ? sanitize_text_field( $payload['platform'] ) : 'generic';
            $event_name = isset( $payload['event_name'] ) ? sanitize_text_field( $payload['event_name'] ) : ( isset( $payload['event'] ) ? sanitize_text_field( $payload['event'] ) : 'event' );
            if ( method_exists( 'UP_CAPI', 'enqueue_event' ) ) {
                UP_CAPI::enqueue_event( $platform, $event_name, $payload );
                $results[] = array( 'queued' => true );
            } else {
                $results[] = UP_CAPI::send_event( $platform, $event_name, $payload, false );
            }
        }

        return new WP_REST_Response( array( 'ok' => true, 'results' => $results ), 200 );
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
}
