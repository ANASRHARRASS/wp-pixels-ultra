<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class UP_Loader {
	public static function init() {
		$inc_dir = UP_PLUGIN_DIR . 'includes/';

		// union of possible includes across branches — require if present
		$candidates = array(
			'class-up-settings.php',
			'class-up-admin.php',
			'class-up-front.php',
			'class-up-capi.php',
			'class-up-events.php',
			'class-up-rest.php',
			'class-up-elementor.php',
			'class-up-rest-ingest.php',
			// auxiliary helpers added on release branch
			'settings.php',
			'api-providers.php',
			'ingest-handler.php',
		);

		foreach ( $candidates as $f ) {
			$path = $inc_dir . $f;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		// admin hooks
		if ( is_admin() && class_exists( 'UP_Admin' ) ) {
			add_action( 'admin_menu', array( 'UP_Admin', 'register_menu' ) );
			add_action( 'admin_init', array( 'UP_Admin', 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( 'UP_Admin', 'enqueue_assets' ) );
		}

		// frontend hooks
		if ( class_exists( 'UP_Front' ) ) {
			add_action( 'wp_head', array( 'UP_Front', 'output_head' ), 1 );
			add_action( 'wp_body_open', array( 'UP_Front', 'output_body' ), 1 );
		}

		// enqueue frontend pixel loader and localize configuration
		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( ! defined( 'UP_PLUGIN_URL' ) ) {
					return;
				}

				// pixel loader is canonical
				wp_enqueue_script( 'up-pixel-loader', UP_PLUGIN_URL . 'assets/pixel-loader.js', array(), defined( 'UP_VERSION' ) ? UP_VERSION : false, true );

				// GTM forwarder is optional — only enqueue if present and configured
				$forwarder_path = UP_PLUGIN_DIR . 'assets/up-gtm-forwarder.js';
				if ( file_exists( $forwarder_path ) ) {
					wp_enqueue_script( 'up-gtm-forwarder', UP_PLUGIN_URL . 'assets/up-gtm-forwarder.js', array(), defined( 'UP_VERSION' ) ? UP_VERSION : false, true );

					// Localize configuration for the forwarder (safe: no server_secret)
					wp_localize_script(
						'up-gtm-forwarder',
						'UP_CONFIG',
						array(
							'ingest_url'        => esc_url_raw( rest_url( 'up/v1/ingest' ) ),
							'wp_nonce'          => wp_create_nonce( 'wp_rest' ),
							'gtm_id'            => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_container_id', '' ) : '',
							'gtm_server_url'    => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_server_url', '' ) : '',
							'meta_pixel_id'     => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'meta_pixel_id', '' ) : '',
							'tiktok_pixel_id'   => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'tiktok_pixel_id', '' ) : '',
							'google_ads_id'     => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'google_ads_id', '' ) : '',
							'snapchat_pixel_id' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'snapchat_pixel_id', '' ) : '',
							'pinterest_tag_id'  => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'pinterest_tag_id', '' ) : '',
							'gtm_manage_pixels' => class_exists( 'UP_Settings' ) ? UP_Settings::get( 'gtm_manage_pixels', 'no' ) === 'yes' : false,
						)
					);
				}
			}
		);

		// events/CAPI initialization
		if ( class_exists( 'UP_Events' ) ) {
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
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) && class_exists( 'UP_CAPI' ) ) {
			WP_CLI::add_command(
				'up-capi',
				function ( $args, $assoc_args ) {
					$limit     = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 50;
					$processed = UP_CAPI::process_queue( $limit );
					WP_CLI::success( "Processed {$processed} items (limit={$limit})" );
				}
			);
		}
	}
}
