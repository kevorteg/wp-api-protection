<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Core
 *
 * Bootstraps the plugin: loads dependencies, registers all WordPress hooks,
 * and wires together the protection, rate-limit, logging, and admin layers.
 */
class WaP_Core {

	/**
	 * Load dependencies and register all hooks.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_public_hooks();
		$this->define_admin_hooks();
	}

	/**
	 * Include all required class files.
	 *
	 * WaP_Helper must be loaded before any other class that calls WaP_Helper::*.
	 * WaP_Logger::maybe_upgrade() is called here directly — NOT via add_action,
	 * because by the time this constructor runs (inside a plugins_loaded callback),
	 * the plugins_loaded hook has already fired; any add_action for it would be a no-op.
	 */
	private function load_dependencies() {
		require_once WAP_PLUGIN_DIR . 'includes/class-wap-helper.php';
		require_once WAP_PLUGIN_DIR . 'includes/class-wap-logger.php';
		require_once WAP_PLUGIN_DIR . 'includes/class-wap-protection.php';
		require_once WAP_PLUGIN_DIR . 'includes/class-wap-rate-limit.php';

		if ( is_admin() ) {
			require_once WAP_PLUGIN_DIR . 'includes/class-wap-dashboard.php';
			require_once WAP_PLUGIN_DIR . 'admin/class-wap-admin.php';
		}

		// Run DB schema upgrade check on every load (uses option comparison — cheap).
		WaP_Logger::maybe_upgrade();
	}

	/**
	 * Register all public-facing / REST API hooks.
	 */
	private function define_public_hooks() {
		$protection  = new WaP_Protection();
		$rate_limiter = new WaP_Rate_Limit();

		// ── Access control (rest_authentication_errors) ────────────────────────
		// Priority 5  — Blacklist / Geo / Whitelist / Hard Block (runs first)
		add_filter( 'rest_authentication_errors', array( $protection, 'check_access_rules' ), 5 );

		// Priority 10 — Rate limiter (runs after access rules)
		add_filter( 'rest_authentication_errors', array( $rate_limiter, 'check_rate_limit' ), 10 );

		// ── REST namespace / route blocking ────────────────────────────────────
		// Uses rest_pre_dispatch to access the actual route being requested.
		add_filter( 'rest_pre_dispatch', array( $protection, 'block_rest_namespaces' ), 10, 3 );

		// ── Security headers on REST responses ─────────────────────────────────
		add_filter( 'rest_post_dispatch', function( $response ) use ( $protection ) {
			$headers = $response->get_headers();
			$headers = $protection->add_security_headers( $headers );
			foreach ( $headers as $key => $value ) {
				$response->header( $key, $value );
			}
			return $response;
		}, 10 );

		// ── User enumeration blocking ──────────────────────────────────────────
		// Priority 1 to run before WP canonical redirects.
		add_action( 'template_redirect', array( $protection, 'block_author_scanning' ), 1 );

		// ── WordPress version fingerprint removal ──────────────────────────────
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'style_loader_src', array( $this, 'remove_wp_version_strings' ), 10, 2 );
		add_filter( 'script_loader_src', array( $this, 'remove_wp_version_strings' ), 10, 2 );
	}

	/**
	 * Register admin-area hooks and handle special admin actions.
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		$plugin_admin = new WaP_Admin();
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );

		// Dashboard widget.
		new WaP_Dashboard();

		// ── Admin-action handlers ──────────────────────────────────────────────
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
	}

	/**
	 * Handle special admin page actions (CSV download, clear logs).
	 */
	public function handle_admin_actions() {
		if ( ! isset( $_GET['page'] ) || 'wp-api-protection' !== $_GET['page'] ) {
			return;
		}

		// CSV Download.
		if ( isset( $_GET['wap_action'] ) && 'download_csv' === $_GET['wap_action'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'wp-api-protection' ) );
			}

			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wap_download_csv' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wp-api-protection' ) );
			}

			require_once WAP_PLUGIN_DIR . 'includes/class-wap-logger.php';
			WaP_Logger::download_csv();
			exit;
		}

		// Clear Logs.
		if ( isset( $_POST['wap_action'] ) && 'clear_logs' === $_POST['wap_action'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'wp-api-protection' ) );
			}

			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wap_clear_logs' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wp-api-protection' ) );
			}

			require_once WAP_PLUGIN_DIR . 'includes/class-wap-logger.php';
			WaP_Logger::clear_logs();
			wp_safe_redirect( admin_url( 'admin.php?page=wp-api-protection&tab=logs&wap_cleared=1' ) );
			exit;
		}
	}

	/**
	 * Remove the ?ver=X.X query string from enqueued scripts and styles.
	 *
	 * @param string $src Original source URL.
	 * @return string URL without WP version query arg.
	 */
	public function remove_wp_version_strings( $src ) {
		if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Entry point — called from add_action('plugins_loaded').
	 * Hooks are registered in the constructor; this method exists
	 * for semantic clarity and future extensibility.
	 */
	public function run() {}
}
