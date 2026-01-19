<?php
/**
 * Plugin Name: WP API Protection
 * Description: Professional hybrid security system for WordPress REST API. Includes Whitelist, Role-based protection, and Biblical Rate Limiting.
 * Version: 2.0.0
 * Author: Kevin Ortega
 * Text Domain: wp-api-protection
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('WAP_VERSION', '2.0.0');
define('WAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WAP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the Core Class
if (!class_exists('WaP_Core')) {
    require_once WAP_PLUGIN_DIR . 'includes/class-wap-core.php';
}

// Activation Hook
register_activation_hook(__FILE__, 'wap_activate_plugin');

function wap_activate_plugin()
{
    // 1. Install Logger Table
    require_once WAP_PLUGIN_DIR . 'includes/class-wap-logger.php';
    WaP_Logger::install();

    // 2. Set Default Options (Security Hardening by Default)
    if (false === get_option('wap_hard_block_enabled')) {
        update_option('wap_hard_block_enabled', 1);
    }
    if (false === get_option('wap_troll_mode_enabled')) {
        update_option('wap_troll_mode_enabled', 1);
    }
}

// Initialize the Plugin
function wap_init()
{
    $plugin = new WaP_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'wap_init');
