<?php
/**
 * Plugin Name: REST API Protection
 * Description: Professional multi-layer security suite for the WordPress REST API. Firewall, rate limiting, geo-blocking, IP blacklist/whitelist, namespace blocking, and security headers.
 * Version:     3.0.0
 * Author:      Kevin Ortega
 * Text Domain: rest-api-protection
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ── Plugin Constants ──────────────────────────────────────────────────────────
define( 'WAP_VERSION',    '3.0.0' );
define( 'WAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'wap_activate_plugin' );

/**
 * Plugin activation:
 * 1. Create / upgrade the log table.
 * 2. Set sensible, secure defaults for every option (only if not already set).
 */
function wap_activate_plugin() {
	// Logger requires the helper for no reason at install time, but load both
	// to keep the dependency chain consistent.
	require_once WAP_PLUGIN_DIR . 'includes/class-wap-helper.php';
	require_once WAP_PLUGIN_DIR . 'includes/class-wap-logger.php';
	WaP_Logger::install();

	// ── Firewall defaults ─────────────────────────────────────────────────────
	// Hard Block ON: safest default — only admins and whitelisted IPs can reach the API.
	if ( false === get_option( 'wap_hard_block_enabled' ) ) {
		update_option( 'wap_hard_block_enabled', 1 );
	}
	// Security Headers ON by default.
	if ( false === get_option( 'wap_security_headers_enabled' ) ) {
		update_option( 'wap_security_headers_enabled', 1 );
	}

	// ── Troll Mode ────────────────────────────────────────────────────────────
	if ( false === get_option( 'wap_troll_mode_enabled' ) ) {
		update_option( 'wap_troll_mode_enabled', 1 );
	}

	// ── Rate Limiter defaults ─────────────────────────────────────────────────
	if ( false === get_option( 'wap_rate_limit_max' ) ) {
		update_option( 'wap_rate_limit_max', 30 );             // 30 requests…
	}
	if ( false === get_option( 'wap_rate_limit_window' ) ) {
		update_option( 'wap_rate_limit_window', 60 );          // …per 60 seconds…
	}
	if ( false === get_option( 'wap_rate_limit_block_duration' ) ) {
		update_option( 'wap_rate_limit_block_duration', 3600 ); // …then block for 1 hour.
	}

	// ── Alert threshold ───────────────────────────────────────────────────────
	if ( false === get_option( 'wap_alert_threshold' ) ) {
		update_option( 'wap_alert_threshold', 20 );
	}
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
// Load the Core class on demand (not on every page load before plugins_loaded).
if ( ! class_exists( 'WaP_Core' ) ) {
	require_once WAP_PLUGIN_DIR . 'includes/class-wap-core.php';
}

/**
 * Instantiate and run the plugin after all plugins are loaded,
 * ensuring all WordPress hooks are available.
 */
function wap_init() {
	$plugin = new WaP_Core();
	$plugin->run();
}
add_action( 'plugins_loaded', 'wap_init' );
