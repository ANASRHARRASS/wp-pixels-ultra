<?php
/**
 * includes/ingest-handler.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'up/v1', '/ingest', [
        'methods' => 'POST',
        'callback' => 'wpu_ingest_handler',
        'permission_callback' => function () {
            if ( ! isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) return false;
            return wp_verify_nonce( sanitize_text_field( $_SERVER['HTTP_X_WP_NONCE'] ), 'wp_rest' );
        },
    ] );
} );

function wpu_ingest_handler( $request ) {
    $body = $request->get_json_params();
    $provider_id = sanitize_text_field( $body['provider_id'] ?? '' );
    $params = is_array( $body['params'] ) ? $body['params'] : [];

    // Normalize and sanitize incoming params: only accept scalar values.
    $clean = [];
    foreach ( $params as $k => $v ) {
        // only allow simple keys (alphanumeric + _-)
        $k = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $k );
        if ( $k === '' ) continue;

        if ( is_array( $v ) || is_object( $v ) ) {
            // skip complex structures
            continue;
        }

        // sanitize scalar values
        if ( is_string( $v ) ) {
            $v = trim( $v );
            $v = sanitize_text_field( $v );
        } elseif ( is_bool( $v ) ) {
            $v = $v ? '1' : '0';
        } else {
            // numbers and other scalars
            $v = (string) $v;
        }

        $clean[ $k ] = $v;
    }
    $params = $clean;

    if ( empty( $provider_id ) ) {
        return new WP_REST_Response( [ 'error' => 'provider_id is required' ], 400 );
    }

    if ( isset( $params['email'] ) ) {
        $email = sanitize_email( $params['email'] );
        if ( $email ) {
            $email = strtolower( $email );
            $params['email_hash'] = hash( 'sha256', $email );
        }
        unset( $params['email'] );
    }

    if ( isset( $params['phone'] ) ) {
        $phone_norm = preg_replace( '/[^0-9+]/', '', $params['phone'] );
        if ( $phone_norm ) {
            $params['phone_hash'] = hash( 'sha256', $phone_norm );
        }
        unset( $params['phone'] );
    }

    if ( ! class_exists( 'WPU_Provider_Manager' ) ) {
        return new WP_REST_Response( [ 'error' => 'provider manager not available' ], 500 );
    }

    $result = WPU_Provider_Manager::fetch_from_provider( $provider_id, $params );
    if ( is_wp_error( $result ) ) {
        $code = 502;
        if ( in_array( $result->get_error_code(), [ 'rate_limited' ], true ) ) $code = 429;
        return new WP_REST_Response( [ 'error' => $result->get_error_message(), 'details' => $result->get_error_data() ], $code );
    }

    return rest_ensure_response( [ 'ok' => true, 'provider' => $provider_id, 'data' => $result ] );
}