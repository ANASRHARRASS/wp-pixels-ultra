<?php
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
    $path = UP_PLUGIN_DIR . 'includes/' . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// rest of file unchanged (kept as-is)