<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_Loader {
    public static function init() {
        $inc_dir = UP_PLUGIN_DIR . 'includes/';

        // include if present
<<<<<<< HEAD
        foreach ( array( 'class-up-settings.php', 'class-up-admin.php', 'class-up-front.php', 'class-up-capi.php', 'class-up-events.php', 'class-up-rest.php', 'class-up-elementor.php' ) as $f ) {
=======
        foreach ( array( 'class-up-settings.php', 'class-up-admin.php', 'class-up-front.php', 'class-up-capi.php', 'class-up-events.php', 'class-up-elementor.php', 'class-up-rest-ingest.php' ) as $f ) {
>>>>>>> origin/main
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
                
                // Enqueue GTM forwarder script for client-side GTM integration
                wp_enqueue_script( 'up-gtm-forwarder', UP_PLUGIN_URL . 'assets/up-gtm-forwarder.js', array(), defined( 'UP_VERSION' ) ? UP_VERSION : false, true );
                
                wp_localize_script( 'up-gtm-forwarder', 'UP_CONFIG', array(
                    'ingest_url' => esc_url_raw( rest_url( 'up/v1/ingest' ) ),
                    'wp_nonce'   => wp_create_nonce( 'wp_rest' ),
                    'gtm_id'     => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_container_id', '' ) : '',
                    'gtm_server_url' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_server_url', '' ) : '',
                    'meta_pixel_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'meta_pixel_id', '' ) : '',
                    'tiktok_pixel_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'tiktok_pixel_id', '' ) : '',
                    'google_ads_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'google_ads_id', '' ) : '',
                    'snapchat_pixel_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'snapchat_pixel_id', '' ) : '',
                    'pinterest_tag_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'pinterest_tag_id', '' ) : '',
                    'gtm_manage_pixels' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_manage_pixels', 'no' ) === 'yes' : false,
                    // NOTE: server_secret is intentionally NOT exposed to client-side.
                ) );
            }
        } );

        // events/CAPI initialization
        if ( class_exists( 'UP_Events' ) ) {
            // UP_Events::init will add its own hooks
            UP_Events::init();
        }
        
        // Elementor integration
        if ( class_exists( 'UP_Elementor' ) ) {
            UP_Elementor::init();
        }

        // register WP REST routes if a REST helper exists
        if ( class_exists( 'UP_REST' ) ) {
            add_action( 'rest_api_init', array( 'UP_REST', 'register_routes' ) );
        }
        // schedule/process CAPI queue via WP-Cron
        if ( class_exists( 'UP_CAPI' ) ) {
            add_action( 'up_capi_process_queue', array( 'UP_CAPI', 'process_queue' ) );
            // ensure a recurring schedule exists as a safety net
            if ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
                wp_schedule_event( time() + 300, 'hourly', 'up_capi_process_queue' );
            }
        }

        // WP-CLI command for manual processing: `wp up-capi process [--limit=50]`
        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
            if ( class_exists( 'UP_CAPI' ) ) {
                WP_CLI::add_command( 'up-capi', function( $args, $assoc_args ) {
                    $limit = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 50;
                    $processed = UP_CAPI::process_queue( $limit );
                    WP_CLI::success( "Processed {$processed} items (limit={$limit})" );
                } );
            }
        }
    }
}
