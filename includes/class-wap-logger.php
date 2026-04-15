<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Logger
 *
 * Manages the intrusion log table (wp_wap_logs):
 * - Schema install / upgrade via dbDelta
 * - Logging blocked events
 * - Attack-threshold alert emails
 * - Log retrieval and CSV export
 * - Log clearing
 */
class WaP_Logger {

	/** Current DB schema version. Bump when adding/changing columns. */
	const DB_VERSION = '2.0';

	/** @var string Table name (set in init()). */
	private static $table_name = '';

	/**
	 * Initialise table name from $wpdb prefix.
	 */
	public static function init() {
		global $wpdb;
		if ( empty( self::$table_name ) ) {
			self::$table_name = $wpdb->prefix . 'wap_logs';
		}
	}

	/**
	 * Create or upgrade the log table.
	 * Called on plugin activation and when DB version changes.
	 */
	public static function install() {
		self::init();
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta handles ADD COLUMN if the table already exists with an older schema.
		$sql = "CREATE TABLE " . self::$table_name . " (
			id         mediumint(9)  NOT NULL AUTO_INCREMENT,
			time       datetime      DEFAULT '0000-00-00 00:00:00' NOT NULL,
			ip         varchar(45)   NOT NULL DEFAULT '',
			type       varchar(50)   NOT NULL DEFAULT '',
			reason     text          NOT NULL,
			request_url text         NOT NULL,
			user_agent varchar(255)  NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY time (time),
			KEY ip (ip)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wap_db_version', self::DB_VERSION );
	}

	/**
	 * Check if the DB schema needs upgrading (e.g. after plugin update).
	 * Call this on plugins_loaded.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'wap_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Insert a blocked-event record.
	 *
	 * @param string $ip     Client IP (already resolved via WaP_Helper::get_real_ip()).
	 * @param string $type   Event type label (e.g. 'Hard Block', 'Rate Limit').
	 * @param string $reason Human-readable description.
	 */
	public static function log( $ip, $type, $reason ) {
		self::init();
		global $wpdb;

		$host       = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$url        = esc_url_raw( 'https://' . $host . $request_uri );

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
			: '';

		$wpdb->insert(
			self::$table_name,
			array(
				'time'        => current_time( 'mysql' ),
				'ip'          => sanitize_text_field( $ip ),
				'type'        => sanitize_text_field( $type ),
				'reason'      => sanitize_text_field( $reason ),
				'request_url' => $url,
				'user_agent'  => $user_agent,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		self::check_attack_threshold();
	}

	/**
	 * Send an alert email if attack count exceeds the threshold in a 5-minute window.
	 */
	private static function check_attack_threshold() {
		$attacks = (int) ( get_transient( 'wap_attack_counter' ) ?: 0 );
		$attacks++;
		set_transient( 'wap_attack_counter', $attacks, 5 * MINUTE_IN_SECONDS );

		$threshold = (int) get_option( 'wap_alert_threshold', 20 );

		if ( $attacks > $threshold && ! get_transient( 'wap_alert_cooldown' ) ) {
			$admin_email = get_option( 'admin_email' );
			$site_name   = get_bloginfo( 'name' );

			$subject = sprintf(
				/* translators: %s: site name */
				__( '[ALERT] Mass intrusion detected — %s', 'wp-api-protection' ),
				$site_name
			);

			$message = sprintf(
				/* translators: 1: threshold count, 2: site admin URL */
				__( "WP API Protection has detected more than %1\$d blocked requests in the last 5 minutes on %2\$s.\n\nPlease review the security logs immediately:\n%3\$s\n\nDefense mode: ACTIVE.", 'wp-api-protection' ),
				$threshold,
				$site_name,
				admin_url( 'admin.php?page=wp-api-protection&tab=logs' )
			);

			wp_mail( $admin_email, $subject, $message );

			// One-hour cooldown to prevent email flooding.
			set_transient( 'wap_alert_cooldown', true, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Retrieve recent log entries.
	 *
	 * @param int $limit Number of rows to return. Defaults to 100.
	 * @return array Array of stdClass log row objects.
	 */
	public static function get_logs( $limit = 100 ) {
		self::init();
		global $wpdb;

		// Use prepare() to safely bind the LIMIT value — fixes SQL injection risk.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . self::$table_name . '` ORDER BY id DESC LIMIT %d',
				(int) $limit
			)
		);
	}

	/**
	 * Count today's log entries.
	 *
	 * @return int
	 */
	public static function count_today() {
		self::init();
		global $wpdb;

		return (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM `' . self::$table_name . '` WHERE time >= CURDATE()'
		);
	}

	/**
	 * Count all log entries.
	 *
	 * @return int
	 */
	public static function count_total() {
		self::init();
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . self::$table_name . '`' );
	}

	/**
	 * Count log entries in the last N days.
	 *
	 * @param int $days Number of days.
	 * @return int
	 */
	public static function count_last_days( $days = 7 ) {
		self::init();
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . self::$table_name . '` WHERE time >= DATE_SUB(NOW(), INTERVAL %d DAY)',
				(int) $days
			)
		);
	}

	/**
	 * Get the most recent log entry.
	 *
	 * @return stdClass|null
	 */
	public static function get_latest() {
		self::init();
		global $wpdb;

		return $wpdb->get_row(
			'SELECT * FROM `' . self::$table_name . '` ORDER BY id DESC LIMIT 1'
		);
	}

	/**
	 * Delete all log entries.
	 */
	public static function clear_logs() {
		self::init();
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE `' . self::$table_name . '`' );
	}

	/**
	 * Output all logs as a downloadable CSV file.
	 * Exits after sending the file.
	 */
	public static function download_csv() {
		$logs = self::get_logs( 5000 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wap-logs-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array( 'ID', 'Time', 'IP', 'Type', 'Reason', 'URL', 'User-Agent' ) );

		foreach ( $logs as $log ) {
			fputcsv( $output, array(
				$log->id,
				$log->time,
				$log->ip,
				$log->type,
				$log->reason,
				$log->request_url,
				$log->user_agent,
			) );
		}

		fclose( $output );
		exit;
	}
}
