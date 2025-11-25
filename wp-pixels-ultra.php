<?php
// phpcs:ignoreFile WordPress.Files.FileName
/**
 * Plugin Name: Ultra Pixels Ultra
 * Description: GTM-first tracking with Meta, TikTok, Google Ads, Snapchat, Pinterest. Features Enhanced Ecommerce, Elementor integration, auto form tracking, and enterprise CAPI queue.
 * Version: 0.4.4
 * Author: anas
 * Text Domain: wp-pixels-ultra
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Tested up to: 6.6
 * GitHub Plugin URI: https://github.com/ANASRHARRASS/wp-pixels-ultra
 * Primary Branch: main
 * Release Asset: false
 * Update URI: https://github.com/ANASRHARRASS/wp-pixels-ultra
 * @package WP_Pixels_Ultra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UP_PLUGIN_FILE', __FILE__ );
define( 'UP_PLUGIN_DIR', plugin_dir_path( UP_PLUGIN_FILE ) );
define( 'UP_PLUGIN_URL', plugin_dir_url( UP_PLUGIN_FILE ) );

if ( ! defined( 'UP_VERSION' ) ) {
	define( 'UP_VERSION', '0.4.4' );
}

require_once UP_PLUGIN_DIR . 'includes/class-up-loader.php';

$optional_includes = array(
	'class-up-admin.php',
	'class-up-front.php',
	'class-up-events.php',
	'class-up-capi.php',
	'class-up-elementor.php',
);
foreach ( $optional_includes as $file ) {
	$inc_path = UP_PLUGIN_DIR . 'includes/' . $file;
	if ( file_exists( $inc_path ) ) {
		require_once $inc_path;
	}
}

/**
 * Class UP_Plugin
 *
 * Lightweight facade for registering and triggering plugin-level events.
 *
 * @package WP_Pixels_Ultra
 */
// Simple facade to register/trigger custom events across the plugin.
class UP_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var UP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Registered event callbacks.
	 *
	 * @var array
	 */
	private $events = array();

	/**
	 * Constructor.
	 *
	 * Initialize plugin hooks.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return UP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a callback for a named custom event.
	 *
	 * @param string   $name     Event name.
	 * @param callable $callback Callable to execute when event is triggered.
	 * @return bool True on success, false on failure.
	 */
	public function register_event( $name, $callback ) {
		$name = (string) $name;
		if ( empty( $name ) || ! is_callable( $callback ) ) {
			return false;
		}
		if ( ! isset( $this->events[ $name ] ) ) {
			$this->events[ $name ] = array();
		}
		$this->events[ $name ][] = $callback;
		return true;
	}

	/**
	 * Trigger a named event with optional data.
	 *
	 * Calls registered callbacks and fires the `up_event_triggered` WP action.
	 *
	 * @param string $name Event name.
	 * @param array  $data Optional event data.
	 * @return void
	 */
	public function trigger_event( $name, $data = array() ) {
		$name = (string) $name;
		if ( isset( $this->events[ $name ] ) && is_array( $this->events[ $name ] ) ) {
			foreach ( $this->events[ $name ] as $cb ) {
				try {
					call_user_func( $cb, $data );
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[UP] event handler error for ' . $name . ': ' . $e->getMessage() );
					}
				}
			}
		}
		do_action( 'up_event_triggered', $name, $data );
	}

	/**
	 * Register REST routes for the plugin.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'up/v1',
			'/trigger',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_trigger' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST callback to trigger a registered event.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
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

// Global helper to register/trigger events from themes/plugins.
// phpcs:ignore
function UP() {
	return UP_Plugin::instance();
}

register_activation_hook(
	UP_PLUGIN_FILE,
	function () {
		// set defaults on activation.
		if ( ! get_option( 'up_settings' ) ) {
			$defaults = array(
				'gtm_container_id' => '',
				'meta_pixel_id'    => '',
				'tiktok_pixel_id'  => '',
				'enable_gtm'       => 'no',
				'enable_meta'      => 'no',
				'enable_tiktok'    => 'no',
				'capi_endpoint'    => '',
				'capi_token'       => '',
			);
			update_option( 'up_settings', $defaults );
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'up_capi_queue';
		$dl_table        = $wpdb->prefix . 'up_capi_deadletter';

		$like = $wpdb->esc_like( $table_name );
		$row  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		if ( $row !== $table_name ) {
			$sql = 'CREATE TABLE ' . $table_name . ' (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform VARCHAR(50) NOT NULL,
            event_name VARCHAR(191) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts SMALLINT NOT NULL DEFAULT 0,
            next_attempt BIGINT(20) DEFAULT 0,
            created_at BIGINT(20) NOT NULL,
            PRIMARY KEY (id),
            KEY next_attempt (next_attempt),
            KEY platform (platform),
            KEY created_at (created_at)
        ) ' . $charset_collate . ';';
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		$like_dl = $wpdb->esc_like( $dl_table );
		$row_dl  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like_dl ) );
		if ( $row_dl !== $dl_table ) {
			$sql = 'CREATE TABLE ' . $dl_table . ' (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform VARCHAR(50) NOT NULL,
            event_name VARCHAR(191) NOT NULL,
            payload LONGTEXT NOT NULL,
            failure_message TEXT DEFAULT NULL,
            failed_at BIGINT(20) NOT NULL,
            PRIMARY KEY (id),
            KEY failed_at (failed_at)
        ) ' . $charset_collate . ';';
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		// Schedule recurring processing as a safety net (hourly).
		if ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'up_capi_process_queue' );
		}
	}
);

register_deactivation_hook(
	UP_PLUGIN_FILE,
	function () {
		// Clear scheduled events on deactivation.
		wp_clear_scheduled_hook( 'up_capi_process_queue' );
	}
);

// initialize loader (keeps backwards compatibility with loader implementation).
UP_Loader::init();

// Register a simple WP-CLI command to process the CAPI queue if WP-CLI is present.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command(
        'up-capi process',
        function ( $args ) {
            if ( ! class_exists( 'UP_CAPI' ) ) {
                WP_CLI::error( 'UP_CAPI class not available' );
                return;
            }
            $limit     = isset( $args[0] ) ? intval( $args[0] ) : 50;
            $processed = UP_CAPI::process_queue( $limit );
            WP_CLI::success( "Processed $processed events." );
        }
    );
}

// initialize facade.
UP();
