<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Protection
 *
 * Handles all request-level access control:
 * - Manual IP blacklist (hard block, before everything else)
 * - Geo-blocking by country code
 * - IP whitelist (bypass all checks)
 * - Admin/role bypass
 * - Hard Block (private site) mode
 * - REST namespace blocking
 * - Author/user enumeration blocking
 * - Troll mode responses
 */
class WaP_Protection {

	/**
	 * Main access control filter for rest_authentication_errors.
	 * Priority 5 — runs before rate limit (priority 10).
	 *
	 * @param WP_Error|null|bool $result Current authentication result.
	 * @return WP_Error|null|true
	 */
	public function check_access_rules( $result ) {
		// Respect errors from earlier hooks.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$ip = WaP_Helper::get_real_ip();

		// ── 1. Manual IP Blacklist ─────────────────────────────────────────────
		// Always checked first — no exceptions, no bypass.
		if ( $this->is_ip_blacklisted( $ip ) ) {
			if ( class_exists( 'WaP_Logger' ) ) {
				WaP_Logger::log( $ip, 'Blacklist', 'Matched manual IP blacklist' );
			}

			return new WP_Error(
				'wap_blacklisted',
				__( 'Access Denied.', 'wp-api-protection' ),
				array( 'status' => 403 )
			);
		}

		// ── 2. Geo-Block ───────────────────────────────────────────────────────
		$blocked_countries_raw = get_option( 'wap_blocked_countries', '' );
		if ( ! empty( $blocked_countries_raw ) ) {
			$blocked_list = array_filter( array_map( 'trim', explode( ',', strtoupper( $blocked_countries_raw ) ) ) );

			if ( ! empty( $blocked_list ) ) {
				$country_code = WaP_Helper::get_country_code( $ip );

				if ( in_array( $country_code, $blocked_list, true ) ) {
					if ( class_exists( 'WaP_Logger' ) ) {
						WaP_Logger::log( $ip, 'Geo-Block', 'Country blocked: ' . $country_code );
					}

					return new WP_Error(
						'wap_geo_blocked',
						__( 'Access Denied from your region.', 'wp-api-protection' ),
						array( 'status' => 403 )
					);
				}
			}
		}

		// ── 3. IP Whitelist ────────────────────────────────────────────────────
		// Whitelisted IPs pass through all remaining checks.
		if ( $this->is_ip_whitelisted( $ip ) ) {
			return $result;
		}

		// ── 4. WordPress Admin/Super Admin bypass ──────────────────────────────
		if ( current_user_can( 'manage_options' ) ) {
			return $result;
		}

		// ── 5. Hard Block (Private Site) Mode ─────────────────────────────────
		if ( get_option( 'wap_hard_block_enabled', false ) ) {
			if ( class_exists( 'WaP_Logger' ) ) {
				WaP_Logger::log( $ip, 'Hard Block', 'Private Site Mode — non-admin access denied' );
			}

			if ( get_option( 'wap_troll_mode_enabled', false ) ) {
				$this->serve_troll_response();
			}

			return new WP_Error(
				'wap_restricted_access',
				__( 'API access is restricted.', 'wp-api-protection' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/**
	 * Blocks specific REST API namespaces/routes.
	 * Hooked to rest_pre_dispatch so we have access to the full request object.
	 *
	 * @param mixed            $result  Current result (null if not dispatched yet).
	 * @param WP_REST_Server   $server  REST server instance.
	 * @param WP_REST_Request  $request Current request.
	 * @return mixed WP_Error if blocked, original $result otherwise.
	 */
	public function block_rest_namespaces( $result, $server, $request ) {
		$blocked_raw = get_option( 'wap_blocked_namespaces', '' );
		if ( empty( $blocked_raw ) ) {
			return $result;
		}

		// Admins and whitelisted IPs bypass namespace blocking.
		if ( current_user_can( 'manage_options' ) ) {
			return $result;
		}

		$ip                  = WaP_Helper::get_real_ip();
		$blocked_namespaces  = array_filter( array_map( 'trim', explode( "\n", $blocked_raw ) ) );
		$current_route       = $request->get_route();

		foreach ( $blocked_namespaces as $namespace ) {
			if ( empty( $namespace ) ) {
				continue;
			}

			// Match if the route starts with /namespace or /namespace/
			$pattern = '/' . ltrim( $namespace, '/' );
			if ( strpos( $current_route, $pattern ) === 0 ) {
				if ( class_exists( 'WaP_Logger' ) ) {
					WaP_Logger::log( $ip, 'Namespace Block', 'Route blocked: ' . esc_html( $current_route ) );
				}

				return new WP_Error(
					'wap_endpoint_blocked',
					__( 'This API endpoint is not available.', 'wp-api-protection' ),
					array( 'status' => 403 )
				);
			}
		}

		return $result;
	}

	/**
	 * Send security hardening headers on every REST response.
	 *
	 * @param array $headers Existing headers array.
	 * @return array Modified headers.
	 */
	public function add_security_headers( $headers ) {
		if ( ! get_option( 'wap_security_headers_enabled', true ) ) {
			return $headers;
		}

		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['X-Frame-Options']        = 'SAMEORIGIN';
		$headers['X-XSS-Protection']       = '1; mode=block';
		$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';

		return $headers;
	}

	/**
	 * Prevents user enumeration via ?author=N query and /author/ archives.
	 */
	public function block_author_scanning() {
		// Admins can see author archives.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( is_author() || isset( $_GET['author'] ) ) {
			$ip = WaP_Helper::get_real_ip();

			if ( class_exists( 'WaP_Logger' ) ) {
				WaP_Logger::log( $ip, 'Recon Block', 'User enumeration attempt via author query' );
			}

			if ( get_option( 'wap_troll_mode_enabled', false ) ) {
				$this->serve_troll_response();
			}

			wp_redirect( home_url() );
			exit;
		}
	}

	// ── Private helpers ────────────────────────────────────────────────────────

	/**
	 * Check if an IP is in the manual whitelist.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	private function is_ip_whitelisted( $ip ) {
		$raw = get_option( 'wap_whitelist_ips', '' );
		if ( empty( $raw ) ) {
			return false;
		}

		$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		return in_array( $ip, $list, true );
	}

	/**
	 * Check if an IP is in the manual blacklist.
	 *
	 * @param string $ip Client IP.
	 * @return bool
	 */
	private function is_ip_blacklisted( $ip ) {
		$raw = get_option( 'wap_blacklist_ips', '' );
		if ( empty( $raw ) ) {
			return false;
		}

		$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		return in_array( $ip, $list, true );
	}

	// ── Troll Mode ─────────────────────────────────────────────────────────────

	/**
	 * Serve a troll response for CLI tools or browsers.
	 * Terminates execution (die) after output.
	 */
	public function serve_troll_response() {
		$ip      = WaP_Helper::get_real_ip();
		$country = WaP_Helper::get_country_code( $ip );

		if ( WaP_Helper::is_cli_request() ) {
			$this->serve_cli_troll( $ip, $country );
		} else {
			$this->serve_browser_troll( $ip, $country );
		}
	}

	/** @internal */
	private function serve_cli_troll( $ip, $country ) {
		if ( function_exists( 'apache_setenv' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@apache_setenv( 'no-gzip', 1 );
		}

		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
		@ini_set( 'output_buffering', 'off' );
		@ini_set( 'zlib.output_compression', false );
		// phpcs:enable

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'X-Firewall: WP-API-Protection' );
		header( 'Content-Type: text/plain; charset=utf-8' );

		$port     = isset( $_SERVER['REMOTE_PORT'] ) ? (int) $_SERVER['REMOTE_PORT'] : 0;
		$os       = WaP_Helper::get_client_os();
		$protocol = isset( $_SERVER['SERVER_PROTOCOL'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) ) : 'HTTP';

		$lines = array(
			'',
			' > INITIATING COUNTER-INTELLIGENCE SCAN...',
			' > [|||||     ] 33%',
			' > [||||||||| ] 89%',
			' > [||||||||||] 100% COMPLETE',
			'',
			' ------------------------------------------------',
			'  INTRUSION ATTEMPT BLOCKED — ACCESS DENIED',
			' ------------------------------------------------',
			'',
			' > TARGET TELEMETRY:',
			'   [+] IP ADDRESS : ' . $ip,
			'   [+] ORIGIN     : ' . $country,
			'   [+] OS SYSTEM  : ' . $os,
			'   [+] SOURCE PORT: ' . $port,
			'   [+] PROTOCOL   : ' . $protocol,
			'',
			' > ANALYSIS:',
			'   This attempt has been logged and flagged.',
			'',
			'   /\\_/\\  ',
			'  ( o.o ) ',
			'   > ^ <  ',
			'   Bye.',
			'',
		);

		foreach ( $lines as $line ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $line . "\n";
			flush();
			usleep( 150000 );
		}

		die();
	}

	/** @internal */
	private function serve_browser_troll( $ip, $country ) {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( rand( 0, 1 ) === 0 ) {
			// Theme A — Minimal / Dark
			?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Access Denied</title>
	<style>
		*{box-sizing:border-box;margin:0;padding:0}
		body{background:#0d0d0d;color:#c0c0c0;font-family:'Georgia',serif;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center}
		.container{max-width:560px;padding:40px;border:1px solid #222;box-shadow:0 0 40px rgba(255,255,255,.04)}
		h1{font-size:1.8em;color:#fff;letter-spacing:6px;margin-bottom:24px;text-transform:uppercase;font-weight:normal}
		p{font-size:.95em;line-height:1.7;color:#666;margin-bottom:12px}
		.data{margin-top:28px;text-align:left;background:#111;padding:20px;border-left:3px solid #333;font-family:'Courier New',monospace;font-size:.85em}
		.lbl{color:#444;font-weight:bold}
		.val{color:#999}
		.status{color:#c0392b}
	</style>
</head>
<body>
	<div class="container">
		<h1>Access Denied</h1>
		<p>"For nothing is hidden that will not be made manifest." — Mark 4:22</p>
		<p>This intrusion attempt has been recorded.</p>
		<div class="data">
			<div><span class="lbl">IP&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</span> <span class="val"><?php echo esc_html( $ip ); ?></span></div>
			<div><span class="lbl">Country :</span> <span class="val"><?php echo esc_html( $country ); ?></span></div>
			<div><span class="lbl">Agent   :</span> <span class="val"><?php echo esc_html( substr( $ua, 0, 60 ) ); ?>…</span></div>
			<div><span class="lbl">Status  :</span> <span class="status">LOGGED</span></div>
		</div>
	</div>
</body>
</html>
			<?php
		} else {
			// Theme B — Terminal / Cyber
			?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>System Failure</title>
	<style>
		*{box-sizing:border-box;margin:0;padding:0}
		body{background:#000;color:#0f0;font-family:'Courier New',monospace;display:flex;align-items:center;justify-content:center;height:100vh;overflow:hidden}
		.term{width:80%;max-width:800px}
		h1{color:#f00;font-size:2.2em;animation:blink 1s infinite;margin-bottom:16px}
		p{margin:4px 0;font-size:1.1em}
		@keyframes blink{50%{opacity:0}}
		.bar-wrap{width:100%;background:#1a1a1a;height:16px;margin:20px 0;border:1px solid #0f0}
		.bar{width:0%;height:100%;background:#0f0;animation:load 4s forwards}
		@keyframes load{100%{width:100%}}
		#msg{margin-top:20px;color:#ff0}
	</style>
</head>
<body>
	<div class="term">
		<h1>SECURITY BREACH DETECTED</h1>
		<p>> TRACING SOURCE IP .............. [DONE]</p>
		<p>> TARGET: <strong style="color:#f00;font-size:1.3em"><?php echo esc_html( $ip ); ?> | <?php echo esc_html( $country ); ?></strong></p>
		<p>> INITIATING COUNTER-MEASURES .... [ACTIVE]</p>
		<p>> UPLOADING LOGS TO ADMIN ........ [DONE]</p>
		<div class="bar-wrap"><div class="bar"></div></div>
		<p id="msg"></p>
		<script>
			setTimeout(function(){document.getElementById('msg').innerText='> WARNING: ISP REPORT FILED IN 5 SECONDS.';},2500);
			setTimeout(function(){document.body.style.background='#8b0000';document.body.style.color='#fff';},5000);
		</script>
	</div>
</body>
</html>
			<?php
		}

		die();
	}
}
