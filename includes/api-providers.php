<?php
/**
 * includes/api-providers.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface WPU_Provider_Interface {
    public function fetch( array $params );
}

class WPU_Provider_Manager {
    public static function load_providers() {
        $list = get_option( 'wpu_providers_config', [] );
        $out = [];
        foreach ( $list as $id => $cfg ) {
            if ( empty( $cfg['enabled'] ) ) continue;
            $out[ $id ] = [ 'id' => $id, 'cfg' => $cfg ];
        }
        return apply_filters( 'wpu_providers_loaded', $out );
    }

    public static function fetch_from_provider( $id, $params = [] ) {
        $providers = self::load_providers();
        if ( empty( $providers[ $id ] ) ) {
            return new WP_Error( 'no_provider', 'Provider not found: ' . esc_html( $id ) );
        }
        $cfg = $providers[ $id ]['cfg'];
        $secret = WPU_Provider_Settings::resolve_secret( $cfg );
        if ( empty( $secret ) ) {
            return new WP_Error( 'no_secret', 'No API secret configured for provider ' . $id );
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
        $limit_ppm = max( 1, intval( $cfg['rate_limit_ppm'] ?? 60 ) );
        $key = "wpu_rate_{$id}16_" . md5( $ip );
        $entry = get_transient( $key );
        if ( $entry ) {
            if ( $entry['window_start'] + 60 > time() ) {
                if ( $entry['count'] >= $limit_ppm ) {
                    return new WP_Error( 'rate_limited', 'Rate limit exceeded for provider ' . $id );
                }
                $entry['count'] += 1;
            } else {
                $entry = [ 'count' => 1, 'window_start' => time() ];
            }
        } else {
            $entry = [ 'count' => 1, 'window_start' => time() ];
        }
        set_transient( $key, $entry, 70 );

        // Sanitize params to avoid injection via add_query_arg and for consistent caching keys
        $safe_params = [];
        if ( is_array( $params ) ) {
            foreach ( $params as $k => $v ) {
                // only allow simple keys
                $k = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $k );
                if ( $k === '' ) continue;
                if ( is_array( $v ) || is_object( $v ) ) continue;
                if ( is_string( $v ) ) {
                    $v = trim( $v );
                    $v = sanitize_text_field( $v );
                } else {
                    $v = (string) $v;
                }
                // normalize email if present
                if ( strtolower( $k ) === 'email' ) {
                    $email = sanitize_email( $v );
                    if ( $email ) $v = strtolower( $email );
                }
                $safe_params[ $k ] = $v;
            }
        }

        $cache_key = 'wpu_prov_' . $id . '_' . md5( wp_json_encode( $safe_params ) );
        $cached = get_transient( $cache_key );
        if ( $cached ) return $cached;

        $endpoint = $cfg['endpoint'] ?? '';
        if ( empty( $endpoint ) ) {
            return new WP_Error( 'no_endpoint', 'No endpoint configured for provider ' . $id );
        }

        $args = [
            'headers' => [ 'Authorization' => 'Bearer ' . $secret, 'Accept' => 'application/json' ],
            'timeout' => 12,
        ];

        // Build URL with sanitized params only
        $url = empty( $safe_params ) ? $endpoint : add_query_arg( $safe_params, $endpoint );
        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) return $response;

        $code = intval( wp_remote_retrieve_response_code( $response ) );
        $body = wp_remote_retrieve_body( $response );
        if ( $code >= 400 ) {
            return new WP_Error( 'remote_error', 'Provider returned HTTP ' . $code, [ 'body' => $body ] );
        }

        $data = json_decode( $body, true );
        set_transient( $cache_key, $data, 30 );
        return $data;
    }
}
