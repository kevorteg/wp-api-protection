<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Admin
 *
 * Registers the plugin admin menu, settings, and enqueue assets.
 */
class WaP_Admin {

	/**
	 * Add the top-level admin menu page.
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'WP API Protection', 'wp-api-protection' ),
			__( 'API Protection', 'wp-api-protection' ),
			'manage_options',
			'wp-api-protection',
			array( $this, 'display_plugin_setup_page' ),
			'dashicons-shield',
			75
		);
	}

	/**
	 * Render the settings page by including the view template.
	 */
	public function display_plugin_setup_page() {
		include_once WAP_PLUGIN_DIR . 'admin/views/html-settings.php';
	}

	/**
	 * Register all plugin options with the WordPress Settings API.
	 * Sanitization callbacks prevent raw user input from being stored.
	 */
	public function register_settings() {
		$group = 'wap_options_group';

		// Firewall.
		register_setting( $group, 'wap_hard_block_enabled',      array( 'sanitize_callback' => 'absint' ) );
		register_setting( $group, 'wap_whitelist_ips',           array( 'sanitize_callback' => array( $this, 'sanitize_ip_list' ) ) );
		register_setting( $group, 'wap_blacklist_ips',           array( 'sanitize_callback' => array( $this, 'sanitize_ip_list' ) ) );
		register_setting( $group, 'wap_blocked_namespaces',      array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( $group, 'wap_blocked_countries',       array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( $group, 'wap_security_headers_enabled', array( 'sanitize_callback' => 'absint' ) );

		// Rate Limiting.
		register_setting( $group, 'wap_rate_limit_max',             array( 'sanitize_callback' => 'absint' ) );
		register_setting( $group, 'wap_rate_limit_window',          array( 'sanitize_callback' => 'absint' ) );
		register_setting( $group, 'wap_rate_limit_block_duration',  array( 'sanitize_callback' => 'absint' ) );

		// Alerts.
		register_setting( $group, 'wap_alert_threshold', array( 'sanitize_callback' => 'absint' ) );

		// Troll Mode.
		register_setting( $group, 'wap_troll_mode_enabled', array( 'sanitize_callback' => 'absint' ) );

		// Custom Messages.
		register_setting( $group, 'wap_custom_messages', array( 'sanitize_callback' => array( $this, 'sanitize_messages' ) ) );
	}

	/**
	 * Sanitize a newline-delimited list of IP addresses.
	 * Strips any entries that are not valid IPs.
	 *
	 * @param string $raw Raw textarea value.
	 * @return string Sanitized IP list.
	 */
	public function sanitize_ip_list( $raw ) {
		$lines  = array_map( 'trim', explode( "\n", sanitize_textarea_field( $raw ) ) );
		$valid  = array_filter( $lines, function( $ip ) {
			return ! empty( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP );
		} );
		return implode( "\n", $valid );
	}

	/**
	 * Sanitize the custom messages array.
	 *
	 * @param mixed $raw Input value.
	 * @return array Sanitized messages.
	 */
	public function sanitize_messages( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		$keys  = array( 'blocked', 'final_block', 'grace' );

		foreach ( $keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( $raw[ $key ] );
			}
		}

		return $clean;
	}

	/**
	 * Enqueue admin-specific styles (if any).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		// Only load on our own admin page.
		if ( false === strpos( $hook_suffix, 'wp-api-protection' ) ) {
			return;
		}

		// Removed Google Fonts to comply with WP Plugin Directory privacy/GDPR requirements.
		// The active UI uses a native system font stack: -apple-system, BlinkMacSystemFont, etc.
	}
}
