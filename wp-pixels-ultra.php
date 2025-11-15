<?php
/**
 * Plugin Name: Ultra Pixels Ultra
 * Description: Production-ready GTM / Meta / TikTok / CAPI tracking & server-side forwarding with queue, dead-letter, rate-limiting, and adapters.
 * Version: 0.3.0
 * Author: anas
 * Text Domain: ultra-pixels-ultra
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Tested up to: 6.6
 * GitHub Plugin URI: https://github.com/ANASRHARRASS/wp-pixels-ultra
 * Primary Branch: main
 * Release Asset: true
 * Update URI: anasrharrass/wp-pixels-ultra
define( 'UP_PLUGIN_FILE', __FILE__ );
define( 'UP_PLUGIN_DIR', plugin_dir_path( UP_PLUGIN_FILE ) );
define( 'UP_PLUGIN_URL', plugin_dir_url( UP_PLUGIN_FILE ) );

// version constant for asset busting and compatibility checks
if ( ! defined( 'UP_VERSION' ) ) {
    define( 'UP_VERSION', '0.3.0' );
}

// always load the loader if present (it wires admin/front)
require_once UP_PLUGIN_DIR . 'includes/class-up-loader.php';

// Safely load optional modules if they exist to avoid fatal errors while you iterate
$optional_includes = array(
    'class-up-admin.php',
    'class-up-front.php',
    'class-up-events.php',
    'class-up-capi.php',
);
foreach ( $optional_includes as $file ) {
    $path = UP_PLUGIN_DIR . 'includes/' . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// Simple facade to register/trigger custom events across the plugin
class UP_Plugin {
    private static $instance = null;
    private $events = array();

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // register a callback for a named custom event
    public function register_event( $name, $callback ) {
        $name = (string) $name;
        if ( empty( $name ) || ! is_callable( $callback ) ) return false;
        if ( ! isset( $this->events[ $name ] ) ) $this->events[ $name ] = array();
        $this->events[ $name ][] = $callback;
        return true;
    }

    // trigger a named event with optional data (calls registered callbacks and fires WP action)
    public function trigger_event( $name, $data = array() ) {
        $name = (string) $name;
        if ( isset( $this->events[ $name ] ) && is_array( $this->events[ $name ] ) ) {
            foreach ( $this->events[ $name ] as $cb ) {
                try {
                    call_user_func( $cb, $data );
                } catch ( Exception $e ) {
                    error_log( '[UP] event handler error for ' . $name . ': ' . $e->getMessage() );
                }
            }
        }
        /**
         * Hook fired for any plugin consumer who wants to react to events.
         * do_action( 'up_event_triggered', $name, $data );
         */
        do_action( 'up_event_triggered', $name, $data );
    }

    // REST route for testing/triggerring events (admin only)
    public function register_rest_routes() {
        register_rest_route( 'up/v1', '/trigger', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_trigger' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    public function rest_trigger( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $event  = isset( $params['event'] ) ? sanitize_text_field( $params['event'] ) : '';
        $data   = isset( $params['data'] ) ? $params['data'] : array();
        if ( empty( $event ) ) {
            return new WP_REST_Response( array( 'error' => 'event required' ), 400 );
        }
        $this->trigger_event( $event, $data );
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }
}

// Global helper to register/trigger events from themes/plugins
function UP() {
    return UP_Plugin::instance();
}

register_activation_hook( UP_PLUGIN_FILE, function() {
    // set defaults on activation
    if ( ! get_option( 'up_settings' ) ) {
        $defaults = array(
            'gtm_container_id'  => '',
            'meta_pixel_id'     => '',
            'tiktok_pixel_id'   => '',
            'enable_gtm'        => 'no',
            'enable_meta'       => 'no',
            'enable_tiktok'     => 'no',
            'capi_endpoint'     => '',
            'capi_token'        => '',
        );
        update_option( 'up_settings', $defaults );
    }
    // Create DB tables for CAPI queue and dead-letter storage
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'up_capi_queue';
    $dl_table = $wpdb->prefix . 'up_capi_deadletter';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table_name ) . "'" ) !== $table_name ) {
        $sql = "CREATE TABLE " . $table_name . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform VARCHAR(50) NOT NULL,
            event_name VARCHAR(191) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts SMALLINT NOT NULL DEFAULT 0,
            next_attempt BIGINT(20) DEFAULT 0,
            created_at BIGINT(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY next_attempt (next_attempt),
            KEY platform (platform),
            KEY created_at (created_at)
        ) " . $charset_collate . ";";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $dl_table ) . "'" ) !== $dl_table ) {
        $sql = "CREATE TABLE " . $dl_table . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform VARCHAR(50) NOT NULL,
            event_name VARCHAR(191) NOT NULL,
            payload LONGTEXT NOT NULL,
            failure_message TEXT DEFAULT NULL,
            failed_at BIGINT(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY failed_at (failed_at)
        ) " . $charset_collate . ";";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // Schedule recurring processing as a safety net (hourly)
    if ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
        wp_schedule_event( time() + 300, 'hourly', 'up_capi_process_queue' );
    }
} );

register_deactivation_hook( UP_PLUGIN_FILE, function() {
    // Clear scheduled events on deactivation
    wp_clear_scheduled_hook( 'up_capi_process_queue' );
} );

// initialize loader (keeps backwards compatibility with loader implementation)
UP_Loader::init();

// Register a simple WP-CLI command to process the CAPI queue if WP-CLI is present
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'up-capi process', function( $args, $assoc ) {
        if ( ! class_exists( 'UP_CAPI' ) ) {
            WP_CLI::error( 'UP_CAPI class not available' );
            return;
        }
        $limit = isset( $args[0] ) ? intval( $args[0] ) : 50;
        $processed = UP_CAPI::process_queue( $limit );
        WP_CLI::success( "Processed $processed events." );
    } );
}

// initialize facade
UP();
