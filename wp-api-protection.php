<?php
/**
 * Plugin Name: WP API Protection
 * Description: Professional hybrid security system for WordPress REST API. Includes Whitelist, Role-based protection, and Biblical Rate Limiting.
 * Version: 2.0.0
 * Author: Kevin Ortega
 * Text Domain: wp-api-protection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'WAP_VERSION', '2.0.0' );
define( 'WAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the Core Class
if ( ! class_exists( 'WaP_Core' ) ) {
    require_once WAP_PLUGIN_DIR . 'includes/class-wap-core.php';
}

// Initialize the Plugin
function wap_init() {
    $plugin = new WaP_Core();
    $plugin->run();
}
add_action( 'plugins_loaded', 'wap_init' );
