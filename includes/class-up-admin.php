<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class UP_Admin {
	public static function register_menu() {
		add_menu_page(
			__( 'Ultra Pixels', 'ultra-pixels-ultra' ),
			__( 'Ultra Pixels', 'ultra-pixels-ultra' ),
			'manage_options',
			'up-settings',
			array( 'UP_Settings', 'render_page' ),
			'dashicons-admin-site',
			81
		);
	}

	public static function register_settings() {
		if ( class_exists( 'UP_Settings' ) ) {
			UP_Settings::init();
		}
	}

	public static function enqueue_assets( $hook ) {
		// Only load assets on our settings page
		if ( strpos( $hook, 'up-settings' ) === false && $hook !== 'toplevel_page_up-settings' ) {
			return;
		}
		wp_enqueue_style( 'up-admin', UP_PLUGIN_URL . 'assets/admin.css', array(), UP_VERSION );
		wp_enqueue_script( 'up-admin', UP_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), UP_VERSION, true );
		// Localize admin endpoints + nonce for admin interactions
		wp_localize_script(
			'up-admin',
			'UPAdmin',
			array(
				'nonce'                 => wp_create_nonce( 'wp_rest' ),
				'test_url'              => esc_url_raw( rest_url( 'up/v1/test' ) ),
				'process_url'           => esc_url_raw( rest_url( 'up/v1/process-queue' ) ),
				'status_url'            => esc_url_raw( rest_url( 'up/v1/queue/status' ) ),
				'items_url'             => esc_url_raw( rest_url( 'up/v1/queue/items' ) ),
				'retry_url'             => esc_url_raw( rest_url( 'up/v1/queue/retry' ) ),
				'delete_url'            => esc_url_raw( rest_url( 'up/v1/queue/delete' ) ),
				'deadletter_url'        => esc_url_raw( rest_url( 'up/v1/queue/deadletter' ) ),
				'deadletter_retry_url'  => esc_url_raw( rest_url( 'up/v1/queue/deadletter/retry' ) ),
				'deadletter_delete_url' => esc_url_raw( rest_url( 'up/v1/queue/deadletter/delete' ) ),
				'logs_url'              => esc_url_raw( rest_url( 'up/v1/logs' ) ),
			)
		);
	}
}
