<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WaP_Dashboard
 *
 * Adds a compact security status widget to the WordPress admin dashboard.
 */
class WaP_Dashboard {

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'wap_dashboard_status',
			__( 'API Protection — Security Status', 'wp-api-protection' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 */
	public function render_widget() {
		$today   = class_exists( 'WaP_Logger' ) ? WaP_Logger::count_today() : 0;
		$total   = class_exists( 'WaP_Logger' ) ? WaP_Logger::count_total() : 0;
		$latest  = class_exists( 'WaP_Logger' ) ? WaP_Logger::get_latest() : null;
		$mode    = get_option( 'wap_hard_block_enabled' ) ? __( 'Hard Block', 'wp-api-protection' ) : __( 'Rate Limit', 'wp-api-protection' );
		$mode_color = get_option( 'wap_hard_block_enabled' ) ? '#2563eb' : '#d97706';
		?>
		<style>
			#wap_dashboard_status .wap-widget { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
			#wap_dashboard_status .wap-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 14px; }
			#wap_dashboard_status .wap-stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; }
			#wap_dashboard_status .wap-stat-num { font-size: 1.75em; font-weight: 700; color: #0f172a; line-height: 1; }
			#wap_dashboard_status .wap-stat-lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }
			#wap_dashboard_status .wap-mode-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; margin-bottom: 12px; }
			#wap_dashboard_status .wap-last { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; font-size: 12px; color: #475569; margin-bottom: 12px; }
			#wap_dashboard_status .wap-last strong { color: #ef4444; }
			#wap_dashboard_status .wap-link { display: inline-block; background: #0f172a; color: #fff; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 5px; text-decoration: none; }
			#wap_dashboard_status .wap-link:hover { background: #1e293b; color: #fff; }
		</style>

		<div class="wap-widget">
			<span class="wap-mode-badge" style="background:<?php echo esc_attr( $mode_color ); ?>">
				<?php echo esc_html( $mode ); ?> — <?php esc_html_e( 'Active', 'wp-api-protection' ); ?>
			</span>

			<div class="wap-stats">
				<div class="wap-stat-box">
					<div class="wap-stat-num"><?php echo esc_html( $today ); ?></div>
					<div class="wap-stat-lbl"><?php esc_html_e( 'Blocked Today', 'wp-api-protection' ); ?></div>
				</div>
				<div class="wap-stat-box">
					<div class="wap-stat-num"><?php echo esc_html( $total ); ?></div>
					<div class="wap-stat-lbl"><?php esc_html_e( 'Total Blocked', 'wp-api-protection' ); ?></div>
				</div>
				<div class="wap-stat-box">
					<div class="wap-stat-num"><?php echo esc_html( get_option( 'wap_rate_limit_max', 30 ) ); ?></div>
					<div class="wap-stat-lbl"><?php esc_html_e( 'Rate Limit', 'wp-api-protection' ); ?></div>
				</div>
			</div>

			<?php if ( $latest ) : ?>
			<div class="wap-last">
				<?php esc_html_e( 'Last blocked:', 'wp-api-protection' ); ?>
				<strong><?php echo esc_html( $latest->ip ); ?></strong>
				&mdash; <?php echo esc_html( $latest->reason ); ?>
				<br><span style="color:#94a3b8"><?php echo esc_html( $latest->time ); ?></span>
			</div>
			<?php else : ?>
			<div class="wap-last"><?php esc_html_e( 'No blocks recorded yet.', 'wp-api-protection' ); ?></div>
			<?php endif; ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-api-protection&tab=logs' ) ); ?>" class="wap-link">
				<?php esc_html_e( 'View Full Report', 'wp-api-protection' ); ?>
			</a>
		</div>
		<?php
	}
}
