<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Rate_Limit
 *
 * Implements a sliding-window rate limiter for unauthenticated REST API requests.
 *
 * Settings:
 *   wap_rate_limit_max            — Max requests per window (default: 30)
 *   wap_rate_limit_window         — Window duration in seconds (default: 60)
 *   wap_rate_limit_block_duration — Lock duration in seconds  (default: 3600 = 1h)
 */
class WaP_Rate_Limit {

	/**
	 * Run the rate limit check on every unauthenticated REST request.
	 *
	 * @param WP_Error|null|bool $result Current authentication result.
	 * @return WP_Error|null|bool
	 */
	public function check_rate_limit( $result ) {
		// Respect earlier errors.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Authenticated (logged-in) users are not rate-limited.
		if ( is_user_logged_in() ) {
			return $result;
		}

		$ip = WaP_Helper::get_real_ip();

		// Derive stable transient keys from the IP.
		$hash              = md5( $ip );
		$lock_key          = 'wap_lock_' . $hash;
		$count_key         = 'wap_count_' . $hash;

		$limit            = max( 1, (int) get_option( 'wap_rate_limit_max', 30 ) );
		$window           = max( 10, (int) get_option( 'wap_rate_limit_window', 60 ) );
		$block_duration   = max( 60, (int) get_option( 'wap_rate_limit_block_duration', HOUR_IN_SECONDS ) );

		// ── 1. Already locked? ────────────────────────────────────────────────
		if ( get_transient( $lock_key ) ) {
			return new WP_Error(
				'wap_rate_limit_exceeded',
				$this->get_message( 'blocked' ),
				array( 'status' => 429 )
			);
		}

		// ── 2. Increment request counter ──────────────────────────────────────
		$attempts = (int) get_transient( $count_key );
		$attempts++;
		set_transient( $count_key, $attempts, $window );

		// ── 3. Check against limit ────────────────────────────────────────────
		if ( $attempts >= $limit ) {
			// Issue the lock and clear the counter.
			set_transient( $lock_key, true, $block_duration );
			delete_transient( $count_key );

			if ( class_exists( 'WaP_Logger' ) ) {
				WaP_Logger::log( $ip, 'Rate Limit', 'Exceeded ' . $limit . ' requests in ' . $window . 's window' );
			}

			// Troll response if enabled.
			if ( get_option( 'wap_troll_mode_enabled', false ) && class_exists( 'WaP_Protection' ) ) {
				( new WaP_Protection() )->serve_troll_response();
			}

			return new WP_Error(
				'wap_rate_limit_final',
				$this->get_message( 'final_block' ),
				array( 'status' => 429 )
			);
		}

		// ── 4. Grace warning on the request that just hit the limit ──────────
		if ( $attempts === $limit ) {
			return new WP_Error(
				'wap_grace_attempt',
				$this->get_message( 'grace' ),
				array( 'status' => 429 )
			);
		}

		return $result;
	}

	/**
	 * Retrieve the configured or default message for a rate-limit event.
	 *
	 * @param string $type 'blocked' | 'final_block' | 'grace'
	 * @return string
	 */
	private function get_message( $type ) {
		$custom = get_option( 'wap_custom_messages', array() );

		$defaults = array(
			'blocked'     => __( 'Access temporarily suspended. Please try again later.', 'wp-api-protection' ),
			'final_block' => __( 'Too many requests. Access has been blocked.', 'wp-api-protection' ),
			'grace'       => __( 'Warning: You have reached the request limit threshold.', 'wp-api-protection' ),
		);

		if ( ! empty( $custom[ $type ] ) ) {
			return $custom[ $type ];
		}

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : __( 'Access Denied.', 'wp-api-protection' );
	}
}
