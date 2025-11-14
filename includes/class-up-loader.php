<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_Loader {
    public static function init() {
        $inc_dir = UP_PLUGIN_DIR . 'includes/';

        // core files (safe include)
        $files = array(
            'file' => 'class-up-settings.php',
            'file' => 'class-up-admin.php',
            'file' => 'class-up-front.php',
            'file' => 'class-up-capi.php',
            'file' => 'class-up-events.php',
        );
        // include if present
        foreach ( array( 'class-up-settings.php', 'class-up-admin.php', 'class-up-front.php', 'class-up-capi.php', 'class-up-events.php' ) as $f ) {
            $path = $inc_dir . $f;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        // admin hooks
        if ( is_admin() ) {
            if ( class_exists( 'UP_Admin' ) ) {
                add_action( 'admin_menu', array( 'UP_Admin', 'register_menu' ) );
                add_action( 'admin_init', array( 'UP_Admin', 'register_settings' ) );
                add_action( 'admin_enqueue_scripts', array( 'UP_Admin', 'enqueue_assets' ) );
            }
        }

        // frontend hooks
        if ( class_exists( 'UP_Front' ) ) {
            add_action( 'wp_head', array( 'UP_Front', 'output_head' ), 1 );
            add_action( 'wp_body_open', array( 'UP_Front', 'output_body' ), 1 );
        }

        // enqueue frontend pixel loader and localize configuration
        add_action( 'wp_enqueue_scripts', function() {
            if ( defined( 'UP_PLUGIN_URL' ) ) {
                // use the canonical assets path inside the plugin
                wp_enqueue_script( 'up-pixel-loader', UP_PLUGIN_URL . 'assets/pixel-loader.js', array(), defined( 'UP_VERSION' ) ? UP_VERSION : false, true );
                wp_localize_script( 'up-pixel-loader', 'UP_CONFIG', array(
                    'ingest_url' => esc_url_raw( rest_url( 'up/v1/ingest' ) ),
                    'nonce'      => wp_create_nonce( 'wp_rest' ),
                    'gtm_id'     => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_container_id', '' ) : '',
                    'meta_pixel_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'meta_pixel_id', '' ) : '',
                    'tiktok_pixel_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'tiktok_pixel_id', '' ) : '',
                    // NOTE: server_secret is intentionally NOT exposed to client-side.
                ) );
            }
        } );

        // events/CAPI initialization
        if ( class_exists( 'UP_Events' ) ) {
            // UP_Events::init will add its own hooks
            UP_Events::init();
        }

        // register WP REST routes if a REST helper exists
        if ( class_exists( 'UP_REST' ) ) {
            add_action( 'rest_api_init', array( 'UP_REST', 'register_routes' ) );
        }
        // schedule/process CAPI queue via WP-Cron
        if ( class_exists( 'UP_CAPI' ) ) {
            add_action( 'up_capi_process_queue', array( 'UP_CAPI', 'process_queue' ) );
            // ensure the hook is registered for future scheduling
        }
    }
}
