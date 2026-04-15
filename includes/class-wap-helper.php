<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Helper
 *
 * Static utility class providing shared functions across the plugin:
 * - Real IP resolution (proxy/CDN-aware)
 * - IP validation
 * - GeoIP lookup (cached)
 * - CLI/bot detection
 * - OS detection for troll mode
 */
class WaP_Helper {

	/**
	 * Get the real client IP address.
	 *
	 * Handles Cloudflare (HTTP_CF_CONNECTING_IP) and trusted reverse proxies
	 * (HTTP_X_REAL_IP). We deliberately do NOT trust HTTP_X_FORWARDED_FOR
	 * because it is trivially spoofable by any client.
	 *
	 * @return string A validated IP address string.
	 */
	public static function get_real_ip() {
		// Cloudflare sends the original visitor IP in this header.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		// nginx and some load balancers set X-Real-IP to the real client IP.
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		// Fall back to the direct connection IP.
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	/**
	 * Validate an IPv4 or IPv6 address.
	 *
	 * @param string $ip The IP to validate.
	 * @return bool
	 */
	public static function is_valid_ip( $ip ) {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Detect whether the current request originates from a CLI tool or known scanner.
	 *
	 * @return bool True if the User-Agent matches a known CLI/scanner tool.
	 */
	public static function is_cli_request() {
		$ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		$cli_tools = array(
			'curl', 'wget', 'python', 'nmap', 'sqlmap', 'nikto',
			'gobuster', 'hydra', 'masscan', 'httpclient', 'go-http-client',
			'dirbuster', 'wfuzz', 'nuclei', 'zap',
		);

		foreach ( $cli_tools as $tool ) {
			if ( strpos( $ua, $tool ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Look up the ISO 3166-1 alpha-2 country code for an IP address.
	 * Results are cached for 24 hours via WordPress transients.
	 *
	 * Uses ip-api.com (free tier, ~45 req/min, no API key required).
	 *
	 * @param string $ip The IP address to look up.
	 * @return string 2-letter country code (e.g. "US") or "XX" on failure.
	 */
	public static function get_country_code( $ip ) {
		if ( ! self::is_valid_ip( $ip ) ) {
			return 'XX';
		}

		$cache_key = 'wap_geo_' . md5( $ip );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode',
			array(
				'timeout'    => 3,
				'user-agent' => 'WP-API-Protection/' . WAP_VERSION,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return 'XX';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = ( isset( $body['countryCode'] ) && ! empty( $body['countryCode'] ) )
			? strtoupper( sanitize_text_field( $body['countryCode'] ) )
			: 'XX';

		set_transient( $cache_key, $code, DAY_IN_SECONDS );

		return $code;
	}

	/**
	 * Guess the client OS from the User-Agent string.
	 * Used for troll mode display only — not security-critical.
	 *
	 * @return string OS name string.
	 */
	public static function get_client_os() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( strpos( $ua, 'Windows' ) !== false ) {
			return 'Windows';
		}
		if ( strpos( $ua, 'Android' ) !== false ) {
			return 'Android';
		}
		if ( strpos( $ua, 'iPhone' ) !== false || strpos( $ua, 'iPad' ) !== false ) {
			return 'iOS';
		}
		if ( strpos( $ua, 'Mac OS' ) !== false ) {
			return 'macOS';
		}
		if ( strpos( $ua, 'Linux' ) !== false ) {
			return 'Linux';
		}

		return 'Unknown';
	}
}
