<?php
/**
 * WP API Protection — Admin Settings Page
 *
 * Renders the full plugin configuration UI with a professional sidebar layout.
 * No external dependencies beyond Google Fonts (loaded by WaP_Admin::enqueue_styles).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-api-protection' ) );
}

// ── Input validation ──────────────────────────────────────────────────────────
$allowed_tabs = array( 'settings', 'logs' );
$active_tab   = ( isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $allowed_tabs, true ) )
	? sanitize_key( $_GET['tab'] )
	: 'settings';

// Section within settings (JS-driven, used for initial highlight only).
$allowed_sections = array( 'firewall', 'ratelimit', 'trollmode', 'messages' );
$active_section   = ( isset( $_GET['section'] ) && in_array( sanitize_key( $_GET['section'] ), $allowed_sections, true ) )
	? sanitize_key( $_GET['section'] )
	: 'firewall';

// ── Status computation ────────────────────────────────────────────────────────
$hard_block    = (bool) get_option( 'wap_hard_block_enabled' );
$troll_mode    = (bool) get_option( 'wap_troll_mode_enabled' );
$headers_on    = (bool) get_option( 'wap_security_headers_enabled', true );

$status_label = $hard_block ? 'Hard Block' : 'Rate Limit Mode';
$status_class = $hard_block ? 'wap-status--green' : 'wap-status--amber';

// ── Stats ─────────────────────────────────────────────────────────────────────
$stat_today = class_exists( 'WaP_Logger' ) ? WaP_Logger::count_today()      : 0;
$stat_week  = class_exists( 'WaP_Logger' ) ? WaP_Logger::count_last_days(7) : 0;
$stat_total = class_exists( 'WaP_Logger' ) ? WaP_Logger::count_total()      : 0;

$whitelist_count = 0;
$whitelist_raw   = get_option( 'wap_whitelist_ips', '' );
if ( ! empty( $whitelist_raw ) ) {
	$whitelist_count = count( array_filter( array_map( 'trim', explode( "\n", $whitelist_raw ) ) ) );
}

$blacklist_count = 0;
$blacklist_raw   = get_option( 'wap_blacklist_ips', '' );
if ( ! empty( $blacklist_raw ) ) {
	$blacklist_count = count( array_filter( array_map( 'trim', explode( "\n", $blacklist_raw ) ) ) );
}

// ── Cleared notice ────────────────────────────────────────────────────────────
$logs_cleared = ( isset( $_GET['wap_cleared'] ) && '1' === $_GET['wap_cleared'] );
?>
<style>
/* ─── Reset & Custom Properties ───────────────────────────────────────────── */
#wap-root *,
#wap-root *::before,
#wap-root *::after {
	box-sizing: border-box;
}

#wap-root {
	--clr-bg:        #f1f5f9;
	--clr-sidebar:   #0f172a;
	--clr-sidebar-h: #1e293b;
	--clr-accent:    #3b82f6;
	--clr-danger:    #ef4444;
	--clr-warning:   #f59e0b;
	--clr-success:   #22c55e;
	--clr-card:      #ffffff;
	--clr-border:    #e2e8f0;
	--clr-text:      #0f172a;
	--clr-muted:     #64748b;
	--radius-sm:     5px;
	--radius-md:     8px;
	--radius-lg:     12px;
	--shadow-sm:     0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
	--shadow-md:     0 4px 12px rgba(0,0,0,.08);
	--font:          'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
	font-family: var(--font);
	color: var(--clr-text);
	background: var(--clr-bg);
}

/* ─── Layout ──────────────────────────────────────────────────────────────── */
#wap-root .wap-layout {
	display: flex;
	min-height: 100vh;
	margin-left: -20px;        /* neutralise WP .wrap padding-left */
	margin-top: -10px;
}

/* ─── Sidebar ─────────────────────────────────────────────────────────────── */
#wap-root .wap-sidebar {
	width: 220px;
	flex-shrink: 0;
	background: var(--clr-sidebar);
	display: flex;
	flex-direction: column;
	position: sticky;
	top: 0;
	height: 100vh;
	overflow-y: auto;
}

#wap-root .wap-brand {
	padding: 24px 20px 20px;
	border-bottom: 1px solid rgba(255,255,255,.07);
}

#wap-root .wap-brand-name {
	font-size: 13px;
	font-weight: 700;
	color: #fff;
	letter-spacing: .5px;
	text-transform: uppercase;
}

#wap-root .wap-brand-ver {
	font-size: 11px;
	color: rgba(255,255,255,.35);
	margin-top: 2px;
}

#wap-root .wap-sidebar-status {
	padding: 10px 20px;
	border-bottom: 1px solid rgba(255,255,255,.07);
}

#wap-root .wap-status-dot {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 11px;
	font-weight: 600;
	color: rgba(255,255,255,.6);
}

#wap-root .wap-status-dot::before {
	content: '';
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: var(--clr-success);
}

#wap-root .wap-status-dot.is-amber::before { background: var(--clr-warning); }

#wap-root .wap-nav {
	padding: 12px 0;
	flex: 1;
}

#wap-root .wap-nav-section {
	padding: 8px 20px 4px;
	font-size: 10px;
	font-weight: 700;
	color: rgba(255,255,255,.25);
	letter-spacing: 1px;
	text-transform: uppercase;
}

#wap-root .wap-nav-item {
	display: flex;
	align-items: center;
	gap: 9px;
	padding: 9px 20px;
	font-size: 13px;
	font-weight: 500;
	color: rgba(255,255,255,.55);
	cursor: pointer;
	border: none;
	background: none;
	width: 100%;
	text-align: left;
	text-decoration: none;
	transition: background .15s, color .15s;
}

#wap-root .wap-nav-item:hover {
	background: var(--clr-sidebar-h);
	color: #fff;
}

#wap-root .wap-nav-item.is-active {
	background: rgba(59,130,246,.15);
	color: #93c5fd;
	font-weight: 600;
}

#wap-root .wap-nav-item .nav-icon {
	width: 15px;
	text-align: center;
	flex-shrink: 0;
	font-style: normal;
}

/* ─── Main Content ────────────────────────────────────────────────────────── */
#wap-root .wap-main {
	flex: 1;
	padding: 28px 32px;
	min-width: 0;
	background: var(--clr-bg);
}

#wap-root .wap-page-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 24px;
}

#wap-root .wap-page-title {
	font-size: 20px;
	font-weight: 700;
	color: var(--clr-text);
	margin: 0;
}

#wap-root .wap-page-sub {
	font-size: 13px;
	color: var(--clr-muted);
	margin: 2px 0 0;
}

/* ─── Stat Cards ──────────────────────────────────────────────────────────── */
#wap-root .wap-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 16px;
	margin-bottom: 28px;
}

#wap-root .wap-stat-card {
	background: var(--clr-card);
	border: 1px solid var(--clr-border);
	border-radius: var(--radius-md);
	padding: 18px 20px;
	box-shadow: var(--shadow-sm);
}

#wap-root .wap-stat-card .sc-num {
	font-size: 2em;
	font-weight: 800;
	color: var(--clr-text);
	line-height: 1;
}

#wap-root .wap-stat-card .sc-lbl {
	font-size: 11px;
	font-weight: 600;
	color: var(--clr-muted);
	text-transform: uppercase;
	letter-spacing: .5px;
	margin-top: 5px;
}

#wap-root .wap-stat-card .sc-accent { color: var(--clr-accent); }
#wap-root .wap-stat-card .sc-danger { color: var(--clr-danger); }
#wap-root .wap-stat-card .sc-success { color: var(--clr-success); }
#wap-root .wap-stat-card .sc-warning { color: var(--clr-warning); }

/* ─── Cards ───────────────────────────────────────────────────────────────── */
#wap-root .wap-card {
	background: var(--clr-card);
	border: 1px solid var(--clr-border);
	border-radius: var(--radius-md);
	box-shadow: var(--shadow-sm);
	margin-bottom: 20px;
	overflow: hidden;
}

#wap-root .wap-card-header {
	padding: 16px 22px;
	border-bottom: 1px solid var(--clr-border);
	display: flex;
	align-items: center;
	gap: 10px;
}

#wap-root .wap-card-header h2 {
	font-size: 14px;
	font-weight: 700;
	color: var(--clr-text);
	margin: 0;
}

#wap-root .wap-card-header .card-badge {
	margin-left: auto;
	font-size: 10px;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 3px;
	text-transform: uppercase;
	letter-spacing: .5px;
}

#wap-root .card-badge--red   { background: #fef2f2; color: #dc2626; }
#wap-root .card-badge--blue  { background: #eff6ff; color: #2563eb; }
#wap-root .card-badge--green { background: #f0fdf4; color: #16a34a; }

#wap-root .wap-card-body {
	padding: 20px 22px;
}

#wap-root .wap-card--accent-red   { border-top: 3px solid var(--clr-danger); }
#wap-root .wap-card--accent-blue  { border-top: 3px solid var(--clr-accent); }
#wap-root .wap-card--accent-green { border-top: 3px solid var(--clr-success); }
#wap-root .wap-card--accent-amber { border-top: 3px solid var(--clr-warning); }

/* ─── Form Elements ───────────────────────────────────────────────────────── */
#wap-root .wap-field {
	margin-bottom: 20px;
}

#wap-root .wap-label {
	display: block;
	font-size: 13px;
	font-weight: 600;
	color: var(--clr-text);
	margin-bottom: 6px;
}

#wap-root .wap-desc {
	font-size: 12px;
	color: var(--clr-muted);
	margin-top: 5px;
	line-height: 1.5;
}

#wap-root textarea.wap-input,
#wap-root input[type="text"].wap-input,
#wap-root input[type="number"].wap-input {
	width: 100%;
	padding: 9px 12px;
	border: 1px solid #cbd5e1;
	border-radius: var(--radius-sm);
	font-size: 13px;
	font-family: var(--font);
	color: var(--clr-text);
	background: #fff;
	transition: border-color .15s, box-shadow .15s;
}

#wap-root textarea.wap-input:focus,
#wap-root input[type="text"].wap-input:focus,
#wap-root input[type="number"].wap-input:focus {
	outline: none;
	border-color: var(--clr-accent);
	box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

#wap-root input[type="number"].wap-input-sm {
	width: 100px;
}

#wap-root textarea.wap-input {
	font-family: 'SFMono-Regular', Menlo, monospace;
	font-size: 12px;
	resize: vertical;
}

/* ─── Toggle Switch ───────────────────────────────────────────────────────── */
#wap-root .wap-toggle-row {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	margin-bottom: 20px;
}

#wap-root .wap-toggle-row + .wap-toggle-row {
	padding-top: 20px;
	border-top: 1px solid var(--clr-border);
}

#wap-root .wap-toggle-label {
	flex: 1;
	font-size: 13px;
	cursor: pointer;
}

#wap-root .wap-toggle-label strong {
	display: block;
	font-size: 13px;
	font-weight: 600;
	color: var(--clr-text);
}

#wap-root .wap-toggle-label .toggle-desc {
	font-size: 12px;
	color: var(--clr-muted);
	margin-top: 3px;
	line-height: 1.5;
}

#wap-root .wap-switch {
	position: relative;
	display: inline-block;
	width: 40px;
	height: 22px;
	flex-shrink: 0;
	margin-top: 1px;
}

#wap-root .wap-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}

#wap-root .wap-switch .slider {
	position: absolute;
	cursor: pointer;
	inset: 0;
	background: #cbd5e1;
	border-radius: 22px;
	transition: background .2s;
}

#wap-root .wap-switch .slider::before {
	content: '';
	position: absolute;
	height: 16px;
	width: 16px;
	left: 3px;
	top: 3px;
	background: #fff;
	border-radius: 50%;
	transition: transform .2s;
	box-shadow: 0 1px 3px rgba(0,0,0,.2);
}

#wap-root .wap-switch input:checked + .slider {
	background: var(--clr-accent);
}

#wap-root .wap-switch input:checked + .slider::before {
	transform: translateX(18px);
}

/* Toggle — danger variant */
#wap-root .wap-switch.is-danger input:checked + .slider {
	background: var(--clr-danger);
}

/* Toggle — success variant */
#wap-root .wap-switch.is-success input:checked + .slider {
	background: var(--clr-success);
}

/* ─── Inline number fields ────────────────────────────────────────────────── */
#wap-root .wap-inline-field {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

#wap-root .wap-inline-field .unit {
	font-size: 12px;
	color: var(--clr-muted);
}

/* ─── Grid for settings columns ───────────────────────────────────────────── */
#wap-root .wap-two-col {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}

/* ─── Divider ─────────────────────────────────────────────────────────────── */
#wap-root .wap-divider {
	border: none;
	border-top: 1px solid var(--clr-border);
	margin: 20px 0;
}

/* ─── Buttons ─────────────────────────────────────────────────────────────── */
#wap-root .wap-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 9px 20px;
	border-radius: var(--radius-sm);
	font-size: 13px;
	font-weight: 600;
	font-family: var(--font);
	cursor: pointer;
	border: 1px solid transparent;
	text-decoration: none;
	transition: background .15s, box-shadow .15s;
}

#wap-root .wap-btn--primary {
	background: var(--clr-accent);
	color: #fff;
	border-color: var(--clr-accent);
}

#wap-root .wap-btn--primary:hover {
	background: #2563eb;
	border-color: #2563eb;
	color: #fff;
}

#wap-root .wap-btn--ghost {
	background: transparent;
	color: var(--clr-muted);
	border-color: var(--clr-border);
}

#wap-root .wap-btn--ghost:hover {
	background: var(--clr-bg);
	color: var(--clr-text);
}

#wap-root .wap-btn--danger {
	background: transparent;
	color: var(--clr-danger);
	border-color: #fca5a5;
}

#wap-root .wap-btn--danger:hover {
	background: #fef2f2;
}

#wap-root .wap-form-actions {
	display: flex;
	align-items: center;
	gap: 12px;
	padding-top: 8px;
}

/* ─── Section panels ──────────────────────────────────────────────────────── */
#wap-root .wap-section {
	display: none;
}

#wap-root .wap-section.is-active {
	display: block;
}

/* ─── Log Table ───────────────────────────────────────────────────────────── */
#wap-root .wap-table-wrap {
	overflow-x: auto;
}

#wap-root .wap-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 12.5px;
}

#wap-root .wap-table th {
	text-align: left;
	padding: 10px 14px;
	background: #f8fafc;
	border-bottom: 2px solid var(--clr-border);
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .5px;
	color: var(--clr-muted);
	white-space: nowrap;
}

#wap-root .wap-table td {
	padding: 10px 14px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: top;
	color: var(--clr-text);
}

#wap-root .wap-table tr:hover td {
	background: #f8fafc;
}

#wap-root .wap-table .ip-cell {
	font-family: 'SFMono-Regular', Menlo, monospace;
	font-size: 12px;
	font-weight: 600;
	color: var(--clr-danger);
}

#wap-root .wap-table .url-cell {
	font-family: 'SFMono-Regular', Menlo, monospace;
	font-size: 11.5px;
	color: var(--clr-muted);
	max-width: 280px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Type badges */
#wap-root .wap-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 10.5px;
	font-weight: 700;
	letter-spacing: .3px;
	white-space: nowrap;
}

#wap-root .wap-badge--block    { background: #fef2f2; color: #dc2626; }
#wap-root .wap-badge--rate     { background: #fff7ed; color: #c2410c; }
#wap-root .wap-badge--geo      { background: #f0fdf4; color: #15803d; }
#wap-root .wap-badge--recon    { background: #faf5ff; color: #7e22ce; }
#wap-root .wap-badge--ns       { background: #eff6ff; color: #1d4ed8; }
#wap-root .wap-badge--default  { background: #f1f5f9; color: #475569; }

/* ─── Notice ──────────────────────────────────────────────────────────────── */
#wap-root .wap-notice {
	padding: 12px 16px;
	border-radius: var(--radius-sm);
	font-size: 13px;
	margin-bottom: 20px;
}

#wap-root .wap-notice--success {
	background: #f0fdf4;
	border: 1px solid #86efac;
	color: #15803d;
}

/* ─── Empty state ─────────────────────────────────────────────────────────── */
#wap-root .wap-empty {
	text-align: center;
	padding: 40px 20px;
	color: var(--clr-muted);
	font-size: 13px;
}

#wap-root .wap-empty strong {
	display: block;
	font-size: 15px;
	color: var(--clr-text);
	margin-bottom: 6px;
}

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
	#wap-root .wap-sidebar { width: 180px; }
	#wap-root .wap-main { padding: 20px 20px; }
	#wap-root .wap-two-col { grid-template-columns: 1fr; }
}

@media (max-width: 720px) {
	#wap-root .wap-layout { flex-direction: column; }
	#wap-root .wap-sidebar { width: 100%; height: auto; position: relative; flex-direction: row; flex-wrap: wrap; }
	#wap-root .wap-nav { display: flex; flex-wrap: wrap; padding: 0; }
}
</style>

<div id="wap-root" class="wrap">
<div class="wap-layout">

	<!-- ── Sidebar ─────────────────────────────────────────────────────── -->
	<aside class="wap-sidebar">
		<div class="wap-brand">
			<div class="wap-brand-name">API Protection</div>
			<div class="wap-brand-ver">v<?php echo esc_html( WAP_VERSION ); ?> &mdash; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
		</div>

		<div class="wap-sidebar-status">
			<div class="wap-status-dot<?php echo $hard_block ? '' : ' is-amber'; ?>">
				<?php echo $hard_block ? 'Hard Block Active' : 'Rate Limit Active'; ?>
			</div>
		</div>

		<nav class="wap-nav" id="wap-nav">
			<div class="wap-nav-section">Settings</div>

			<button class="wap-nav-item is-active" data-target="section-firewall">
				<i class="nav-icon dashicons dashicons-shield-alt"></i> Firewall
			</button>
			<button class="wap-nav-item" data-target="section-ratelimit">
				<i class="nav-icon dashicons dashicons-clock"></i> Rate Limiting
			</button>
			<button class="wap-nav-item" data-target="section-trollmode">
				<i class="nav-icon dashicons dashicons-warning"></i> Troll Mode
			</button>
			<button class="wap-nav-item" data-target="section-messages">
				<i class="nav-icon dashicons dashicons-editor-quote"></i> Messages
			</button>

			<div class="wap-nav-section" style="margin-top:8px">Monitor</div>

			<a class="wap-nav-item<?php echo 'logs' === $active_tab ? ' is-active' : ''; ?>"
			   href="<?php echo esc_url( admin_url( 'admin.php?page=wp-api-protection&tab=logs' ) ); ?>">
				<i class="nav-icon dashicons dashicons-list-view"></i> Intrusion Logs
			</a>
		</nav>
	</aside>

	<!-- ── Main ────────────────────────────────────────────────────────── -->
	<main class="wap-main">

		<?php if ( 'logs' === $active_tab ) : ?>

		<!-- ═══════════════════════════════════════════════════════════════
			 LOGS VIEW
		════════════════════════════════════════════════════════════════ -->
		<div class="wap-page-header">
			<div>
				<h1 class="wap-page-title">Intrusion Logs</h1>
				<p class="wap-page-sub">Last 100 blocked requests — sorted by most recent.</p>
			</div>
			<div style="display:flex;gap:10px;align-items:center">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp-api-protection&tab=logs&wap_action=download_csv' ), 'wap_download_csv' ) ); ?>"
				   class="wap-btn wap-btn--ghost">
					Export CSV
				</a>

				<form method="post" action="" onsubmit="return confirm('Clear all logs? This cannot be undone.');">
					<?php wp_nonce_field( 'wap_clear_logs' ); ?>
					<input type="hidden" name="wap_action" value="clear_logs">
					<button type="submit" class="wap-btn wap-btn--danger">Clear Logs</button>
				</form>
			</div>
		</div>

		<?php if ( $logs_cleared ) : ?>
		<div class="wap-notice wap-notice--success">Log table cleared successfully.</div>
		<?php endif; ?>

		<!-- Stats row -->
		<div class="wap-stats-grid" style="margin-bottom:20px">
			<div class="wap-stat-card">
				<div class="sc-num sc-danger"><?php echo esc_html( $stat_today ); ?></div>
				<div class="sc-lbl">Blocked Today</div>
			</div>
			<div class="wap-stat-card">
				<div class="sc-num sc-warning"><?php echo esc_html( $stat_week ); ?></div>
				<div class="sc-lbl">Last 7 Days</div>
			</div>
			<div class="wap-stat-card">
				<div class="sc-num"><?php echo esc_html( $stat_total ); ?></div>
				<div class="sc-lbl">Total Blocked</div>
			</div>
		</div>

		<div class="wap-card">
			<div class="wap-table-wrap">
			<?php
			$logs = class_exists( 'WaP_Logger' ) ? WaP_Logger::get_logs( 100 ) : array();
			if ( ! empty( $logs ) ) :
			?>
			<table class="wap-table">
				<thead>
					<tr>
						<th>Time</th>
						<th>IP Address</th>
						<th>Type</th>
						<th>Reason</th>
						<th>URL</th>
						<th>User Agent</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $logs as $log ) :
					// Determine badge class by type.
					$type_lower  = strtolower( $log->type );
					$badge_class = 'wap-badge--default';
					if ( false !== strpos( $type_lower, 'block' ) )     { $badge_class = 'wap-badge--block'; }
					elseif ( false !== strpos( $type_lower, 'rate' ) )  { $badge_class = 'wap-badge--rate'; }
					elseif ( false !== strpos( $type_lower, 'geo' ) )   { $badge_class = 'wap-badge--geo'; }
					elseif ( false !== strpos( $type_lower, 'recon' ) ) { $badge_class = 'wap-badge--recon'; }
					elseif ( false !== strpos( $type_lower, 'namespace' ) ) { $badge_class = 'wap-badge--ns'; }
				?>
				<tr>
					<td style="white-space:nowrap;color:var(--clr-muted)"><?php echo esc_html( $log->time ); ?></td>
					<td class="ip-cell"><?php echo esc_html( $log->ip ); ?></td>
					<td><span class="wap-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $log->type ); ?></span></td>
					<td><?php echo esc_html( $log->reason ); ?></td>
					<td class="url-cell" title="<?php echo esc_attr( $log->request_url ); ?>"><?php echo esc_html( $log->request_url ); ?></td>
					<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--clr-muted);font-size:11px"
					    title="<?php echo esc_attr( $log->user_agent ); ?>">
						<?php echo esc_html( $log->user_agent ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<div class="wap-empty">
				<strong>No intrusions recorded</strong>
				The watchmen are silent. All systems nominal.
			</div>
			<?php endif; ?>
			</div>
		</div>

		<?php else : ?>

		<!-- ═══════════════════════════════════════════════════════════════
			 SETTINGS VIEW
		════════════════════════════════════════════════════════════════ -->
		<div class="wap-page-header">
			<div>
				<h1 class="wap-page-title">Security Configuration</h1>
				<p class="wap-page-sub">Configure protection layers, rate limits, and response behaviour.</p>
			</div>
		</div>

		<!-- Stats strip -->
		<div class="wap-stats-grid">
			<div class="wap-stat-card">
				<div class="sc-num sc-danger"><?php echo esc_html( $stat_today ); ?></div>
				<div class="sc-lbl">Blocked Today</div>
			</div>
			<div class="wap-stat-card">
				<div class="sc-num sc-warning"><?php echo esc_html( $stat_week ); ?></div>
				<div class="sc-lbl">Last 7 Days</div>
			</div>
			<div class="wap-stat-card">
				<div class="sc-num"><?php echo esc_html( $stat_total ); ?></div>
				<div class="sc-lbl">Total Blocked</div>
			</div>
			<div class="wap-stat-card">
				<div class="sc-num sc-success"><?php echo esc_html( $whitelist_count ); ?></div>
				<div class="sc-lbl">Whitelisted IPs</div>
			</div>
			<div class="wap-stat-card">
				<div class="sc-num sc-danger"><?php echo esc_html( $blacklist_count ); ?></div>
				<div class="sc-lbl">Blacklisted IPs</div>
			</div>
		</div>

		<form method="post" action="options.php" id="wap-settings-form">
			<?php settings_fields( 'wap_options_group' ); ?>

			<!-- ─── SECTION: Firewall ──────────────────────────────────── -->
			<div class="wap-section is-active" id="section-firewall">

				<div class="wap-card wap-card--accent-red">
					<div class="wap-card-header">
						<span class="dashicons dashicons-shield-alt" style="color:var(--clr-danger)"></span>
						<h2>Access Control</h2>
						<span class="card-badge card-badge--red">Core</span>
					</div>
					<div class="wap-card-body">

						<div class="wap-toggle-row">
							<label class="wap-switch is-danger">
								<input type="checkbox" name="wap_hard_block_enabled" value="1"
								       id="hard_block_toggle"
								       <?php checked( 1, get_option( 'wap_hard_block_enabled' ) ); ?>>
								<span class="slider"></span>
							</label>
							<label for="hard_block_toggle" class="wap-toggle-label">
								<strong>Hard Block Mode — Private Site</strong>
								<span class="toggle-desc">When enabled, only Whitelisted IPs and Admins can reach the REST API. Everyone else receives a 403. This is the strongest protection level.</span>
							</label>
						</div>

						<div class="wap-toggle-row">
							<label class="wap-switch is-success">
								<input type="checkbox" name="wap_security_headers_enabled" value="1"
								       id="headers_toggle"
								       <?php checked( 1, get_option( 'wap_security_headers_enabled', 1 ) ); ?>>
								<span class="slider"></span>
							</label>
							<label for="headers_toggle" class="wap-toggle-label">
								<strong>Security Headers</strong>
								<span class="toggle-desc">Injects <code>X-Content-Type-Options</code>, <code>X-Frame-Options: SAMEORIGIN</code>, <code>X-XSS-Protection</code>, and <code>Referrer-Policy</code> on all REST responses.</span>
							</label>
						</div>

					</div>
				</div>

				<div class="wap-two-col">

					<div class="wap-card wap-card--accent-green">
						<div class="wap-card-header">
							<span class="dashicons dashicons-yes-alt" style="color:var(--clr-success)"></span>
							<h2>IP Whitelist</h2>
						</div>
						<div class="wap-card-body">
							<div class="wap-field">
								<label class="wap-label" for="wap_whitelist_ips">Trusted IP Addresses</label>
								<textarea id="wap_whitelist_ips" name="wap_whitelist_ips"
								          class="wap-input" rows="6"
								          placeholder="203.0.113.1&#10;198.51.100.0&#10;..."><?php echo esc_textarea( get_option( 'wap_whitelist_ips' ) ); ?></textarea>
								<p class="wap-desc">One IPv4 or IPv6 address per line. These IPs bypass all blocks, rate limits, and geo-filters. Only valid IPs are saved.</p>
							</div>
						</div>
					</div>

					<div class="wap-card wap-card--accent-red">
						<div class="wap-card-header">
							<span class="dashicons dashicons-no-alt" style="color:var(--clr-danger)"></span>
							<h2>IP Blacklist</h2>
							<span class="card-badge card-badge--red">New</span>
						</div>
						<div class="wap-card-body">
							<div class="wap-field">
								<label class="wap-label" for="wap_blacklist_ips">Permanently Blocked IPs</label>
								<textarea id="wap_blacklist_ips" name="wap_blacklist_ips"
								          class="wap-input" rows="6"
								          placeholder="1.2.3.4&#10;5.6.7.8&#10;..."><?php echo esc_textarea( get_option( 'wap_blacklist_ips' ) ); ?></textarea>
								<p class="wap-desc">One IP per line. Blacklisted IPs are blocked immediately — before any other rule runs. No exceptions. Only valid IPs are saved.</p>
							</div>
						</div>
					</div>

				</div>

				<div class="wap-card wap-card--accent-blue">
					<div class="wap-card-header">
						<span class="dashicons dashicons-admin-site" style="color:var(--clr-accent)"></span>
						<h2>Geo-Blocking</h2>
						<span class="card-badge card-badge--blue">New</span>
					</div>
					<div class="wap-card-body">
						<div class="wap-field">
							<label class="wap-label" for="wap_blocked_countries">Blocked Countries</label>
							<input type="text" id="wap_blocked_countries" name="wap_blocked_countries"
							       class="wap-input" style="max-width:400px"
							       placeholder="RU, CN, KP, IR"
							       value="<?php echo esc_attr( get_option( 'wap_blocked_countries' ) ); ?>">
							<p class="wap-desc">Comma-separated ISO 3166-1 alpha-2 country codes (e.g. <code>RU, CN, KP</code>). Country lookups are cached for 24 hours via ip-api.com.</p>
						</div>
					</div>
				</div>

				<div class="wap-card wap-card--accent-blue">
					<div class="wap-card-header">
						<span class="dashicons dashicons-shortcode" style="color:var(--clr-accent)"></span>
						<h2>REST Namespace Blocking</h2>
						<span class="card-badge card-badge--blue">New</span>
					</div>
					<div class="wap-card-body">
						<div class="wap-field">
							<label class="wap-label" for="wap_blocked_namespaces">Blocked Namespaces / Routes</label>
							<textarea id="wap_blocked_namespaces" name="wap_blocked_namespaces"
							          class="wap-input" rows="5"
							          placeholder="/wp/v2/users&#10;/wp/v2/comments&#10;/wc/v3&#10;..."><?php echo esc_textarea( get_option( 'wap_blocked_namespaces' ) ); ?></textarea>
							<p class="wap-desc">One namespace prefix per line. Any REST route starting with this prefix returns 403. Use this to hide sensitive endpoints (user data, WooCommerce, etc.). Admins and whitelisted IPs are exempt.</p>
						</div>
					</div>
				</div>

				<div class="wap-card">
					<div class="wap-card-header">
						<span class="dashicons dashicons-bell"></span>
						<h2>Email Alerts</h2>
					</div>
					<div class="wap-card-body">
						<div class="wap-field">
							<label class="wap-label">Alert Threshold</label>
							<div class="wap-inline-field">
								<input type="number" name="wap_alert_threshold" class="wap-input wap-input-sm"
								       min="1" max="999"
								       value="<?php echo esc_attr( get_option( 'wap_alert_threshold', 20 ) ); ?>">
								<span class="unit">blocks within 5 minutes trigger an alert email to <strong><?php echo esc_html( get_option( 'admin_email' ) ); ?></strong></span>
							</div>
							<p class="wap-desc">Alert emails have a 1-hour cooldown to prevent spam.</p>
						</div>
					</div>
				</div>

			</div><!-- /section-firewall -->

			<!-- ─── SECTION: Rate Limiting ────────────────────────────── -->
			<div class="wap-section" id="section-ratelimit">

				<div class="wap-card wap-card--accent-amber">
					<div class="wap-card-header">
						<span class="dashicons dashicons-clock" style="color:var(--clr-warning)"></span>
						<h2>Rate Limiter</h2>
						<span class="card-badge card-badge--blue">Configurable</span>
					</div>
					<div class="wap-card-body">

						<p style="font-size:13px;color:var(--clr-muted);margin:0 0 20px">
							All unauthenticated REST API requests are counted per IP. When the limit is reached within the time window, the IP is locked for the block duration.
						</p>

						<div class="wap-two-col">
							<div class="wap-field">
								<label class="wap-label" for="wap_rate_limit_max">Max Requests</label>
								<div class="wap-inline-field">
									<input type="number" id="wap_rate_limit_max" name="wap_rate_limit_max"
									       class="wap-input wap-input-sm" min="1" max="9999"
									       value="<?php echo esc_attr( get_option( 'wap_rate_limit_max', 30 ) ); ?>">
									<span class="unit">requests</span>
								</div>
								<p class="wap-desc">Number of allowed requests per IP within the time window.</p>
							</div>

							<div class="wap-field">
								<label class="wap-label" for="wap_rate_limit_window">Time Window</label>
								<div class="wap-inline-field">
									<input type="number" id="wap_rate_limit_window" name="wap_rate_limit_window"
									       class="wap-input wap-input-sm" min="10" max="86400"
									       value="<?php echo esc_attr( get_option( 'wap_rate_limit_window', 60 ) ); ?>">
									<span class="unit">seconds</span>
								</div>
								<p class="wap-desc">The rolling window over which requests are counted. 60s = per minute.</p>
							</div>

							<div class="wap-field">
								<label class="wap-label" for="wap_rate_limit_block_duration">Block Duration</label>
								<div class="wap-inline-field">
									<input type="number" id="wap_rate_limit_block_duration" name="wap_rate_limit_block_duration"
									       class="wap-input wap-input-sm" min="60" max="604800"
									       value="<?php echo esc_attr( get_option( 'wap_rate_limit_block_duration', HOUR_IN_SECONDS ) ); ?>">
									<span class="unit">seconds (<?php echo esc_html( HOUR_IN_SECONDS ); ?> = 1 hour)</span>
								</div>
								<p class="wap-desc">How long an IP remains locked after exceeding the limit.</p>
							</div>
						</div>

						<div style="background:#f8fafc;border:1px solid var(--clr-border);border-radius:6px;padding:14px;font-size:12.5px;color:var(--clr-muted);margin-top:8px">
							<strong style="color:var(--clr-text)">Current config:</strong>
							Allow up to <strong><?php echo esc_html( get_option( 'wap_rate_limit_max', 30 ) ); ?> requests</strong>
							per <strong><?php echo esc_html( get_option( 'wap_rate_limit_window', 60 ) ); ?> seconds</strong>,
							then block for <strong><?php echo esc_html( round( get_option( 'wap_rate_limit_block_duration', HOUR_IN_SECONDS ) / 3600, 2 ) ); ?> hour(s)</strong>.
						</div>
					</div>
				</div>

			</div><!-- /section-ratelimit -->

			<!-- ─── SECTION: Troll Mode ───────────────────────────────── -->
			<div class="wap-section" id="section-trollmode">

				<div class="wap-card wap-card--accent-amber">
					<div class="wap-card-header">
						<span class="dashicons dashicons-warning" style="color:var(--clr-warning)"></span>
						<h2>Troll Mode — PsyOps Response</h2>
						<span class="card-badge card-badge--blue">Experimental</span>
					</div>
					<div class="wap-card-body">

						<div class="wap-toggle-row">
							<label class="wap-switch is-danger">
								<input type="checkbox" name="wap_troll_mode_enabled" value="1"
								       id="troll_toggle"
								       <?php checked( 1, get_option( 'wap_troll_mode_enabled' ) ); ?>>
								<span class="slider"></span>
							</label>
							<label for="troll_toggle" class="wap-toggle-label">
								<strong>Enable Troll Mode</strong>
								<span class="toggle-desc">When an IP is blocked (Hard Block or Rate Limit), instead of a plain 403 JSON error, they receive a theatrical response designed to waste their time and deter further attempts.</span>
							</label>
						</div>

						<div style="border-top:1px solid var(--clr-border);margin:4px 0 20px"></div>

						<div class="wap-two-col">
							<div style="background:#0d0d0d;border-radius:8px;padding:18px;font-family:'SFMono-Regular',monospace;font-size:12px;color:#c0c0c0">
								<p style="color:#999;margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px">CLI Response (curl / wget / scanners)</p>
								<pre style="margin:0;color:#c0c0c0;line-height:1.6"> &gt; COUNTER-INTELLIGENCE SCAN...
 &gt; [||||||||||] 100% COMPLETE
 ─────────────────────────────
  INTRUSION ATTEMPT BLOCKED
 ─────────────────────────────
 [+] IP ADDRESS : 1.2.3.4
 [+] ORIGIN     : XX
 [+] OS SYSTEM  : Linux
   /\\_/\
  ( o.o )
   &gt; ^ &lt;   Bye.</pre>
							</div>

							<div>
								<div style="background:#f8fafc;border:1px solid var(--clr-border);border-radius:8px;padding:18px;font-size:12.5px;color:var(--clr-text);margin-bottom:14px">
									<p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--clr-muted)">Browser Response — Theme A (Dark)</p>
									<p style="margin:0;color:var(--clr-muted)">Displays a minimal dark screen with the blocked IP, country, and User-Agent. Looks like a real security intercept.</p>
								</div>
								<div style="background:#000;border:1px solid #1a1a1a;border-radius:8px;padding:18px;font-family:monospace;font-size:12px;color:#0f0">
									<p style="margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#1a5f1a">Browser Response — Theme B (Terminal)</p>
									<p style="margin:0;color:#555">Green terminal with animated progress bar. Switches background to red after 5 seconds.</p>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div><!-- /section-trollmode -->

			<!-- ─── SECTION: Messages ─────────────────────────────────── -->
			<div class="wap-section" id="section-messages">

				<div class="wap-card">
					<div class="wap-card-header">
						<span class="dashicons dashicons-editor-quote"></span>
						<h2>Custom Error Messages</h2>
					</div>
					<div class="wap-card-body">

						<p style="font-size:13px;color:var(--clr-muted);margin:0 0 20px">
							These messages appear in the JSON error response body when a request is blocked. Leave blank to use defaults.
						</p>

						<?php
						$msgs = get_option( 'wap_custom_messages', array() );
						$default_msgs = array(
							'blocked'     => 'Access temporarily suspended. Please try again later.',
							'final_block' => 'Too many requests. Access has been blocked.',
							'grace'       => 'Warning: You have reached the request limit threshold.',
						);
						$fields = array(
							'blocked'     => 'Blocked (Already Locked)',
							'final_block' => 'Final Block (Limit Exceeded)',
							'grace'       => 'Grace Warning (Last Attempt)',
						);
						foreach ( $fields as $key => $label ) :
							$val = isset( $msgs[ $key ] ) ? $msgs[ $key ] : '';
						?>
						<div class="wap-field">
							<label class="wap-label" for="wap_msg_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
							<input type="text" id="wap_msg_<?php echo esc_attr( $key ); ?>"
							       name="wap_custom_messages[<?php echo esc_attr( $key ); ?>]"
							       class="wap-input"
							       value="<?php echo esc_attr( $val ); ?>"
							       placeholder="<?php echo esc_attr( $default_msgs[ $key ] ); ?>">
						</div>
						<?php endforeach; ?>

					</div>
				</div>

			</div><!-- /section-messages -->

			<!-- ─── Form Actions ──────────────────────────────────────── -->
			<div class="wap-form-actions">
				<button type="submit" class="wap-btn wap-btn--primary">Save Settings</button>
				<span style="font-size:12px;color:var(--clr-muted)">Changes apply to all active requests immediately.</span>
			</div>

		</form>

		<?php endif; ?>

	</main>
</div><!-- /.wap-layout -->
</div><!-- /#wap-root -->

<script>
(function() {
	'use strict';

	var navItems  = document.querySelectorAll('#wap-nav [data-target]');
	var sections  = document.querySelectorAll('.wap-section');

	function activate(target) {
		sections.forEach(function(s) { s.classList.remove('is-active'); });
		navItems.forEach(function(n) { n.classList.remove('is-active'); });

		var section = document.getElementById(target);
		if (section) { section.classList.add('is-active'); }

		navItems.forEach(function(n) {
			if (n.getAttribute('data-target') === target) {
				n.classList.add('is-active');
			}
		});
	}

	navItems.forEach(function(item) {
		item.addEventListener('click', function(e) {
			e.preventDefault();
			activate(item.getAttribute('data-target'));
		});
	});

	// Highlight config summary on rate limit blur
	var rlInputs = document.querySelectorAll('#section-ratelimit input[type="number"]');
	function updateSummary() {
		var max    = document.getElementById('wap_rate_limit_max');
		var win    = document.getElementById('wap_rate_limit_window');
		var blk    = document.getElementById('wap_rate_limit_block_duration');
		if (!max || !win || !blk) return;
		var summaryEl = document.querySelector('#section-ratelimit strong[data-summary]');
		// (static summary in PHP is fine for now)
	}
	rlInputs.forEach(function(i) { i.addEventListener('change', updateSummary); });
}());
</script>