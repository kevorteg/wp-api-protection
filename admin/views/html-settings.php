<?php
if (!defined('ABSPATH'))
    exit;

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';

// Status Logic
$status_color = '#00a32a';
$status_text = 'Protected';
$status_icon = 'dashicons-shield-alt';

$hard_block = get_option('wap_hard_block_enabled');
$troll_mode = get_option('wap_troll_mode_enabled');
$whitelist = get_option('wap_whitelist_ips');

// If hard block is OFF, we are in "Monitoring" or "Weak" mode unless relying only on rate limits.
if (!$hard_block) {
    $status_color = '#dba617'; // Orange/Warning
    $status_text = 'Monitoring / Low Protection';
    $status_icon = 'dashicons-warning';

    // If rate limit is also very high or disabled? (Rate limit is always on currently).
}

// If everything feels "off" (implementation defined).
// Actually, if Hard Block is OFF, we are just Rate Limiting. That's "Partial".
// If whitelist is empty and Hard Block is ON, we are blocking EVERYONE (High Protection).
?>

<style>
    .wap-dashboard {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        max-width: 1200px;
        margin: 20px 0;
    }

    /* Tabs */
    .nav-tab-wrapper {
        margin-bottom: 20px;
    }

    .wap-header {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .wap-title h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #1d2327;
    }

    .wap-badge {
        background: #2271b1;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }

    .wap-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }

    .wap-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        padding: 20px;
        margin-bottom: 20px;
    }

    .wap-card h2 {
        margin-top: 0;
        border-bottom: 1px solid #f0f0f1;
        padding-bottom: 15px;
        font-size: 18px;
    }

    .wap-form-group {
        margin-bottom: 20px;
    }

    .wap-form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .wap-form-group textarea,
    .wap-form-group input[type="text"],
    .wap-form-group input[type="number"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #dcdcde;
        border-radius: 4px;
    }

    .wap-stat {
        font-size: 2em;
        font-weight: bold;
        color: #2271b1;
    }

    .wap-stat-label {
        color: #646970;
    }

    .wap-log-table {
        width: 100%;
        border-collapse: collapse;
    }

    .wap-log-table th,
    .wap-log-table td {
        text-align: left;
        padding: 10px;
        border-bottom: 1px solid #f0f0f1;
    }

    .wap-log-table th {
        font-weight: 600;
    }

    .wap-log-table tr:hover {
        background: #fafafa;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 15px;
        color: white;
        font-weight: 600;
    }

    .wap-submit {
        margin-top: 20px;
    }
</style>

<div class="wrap wap-dashboard">
    <div class="wap-header">
        <div class="wap-title">
            <h1>🛡️ WP API Protection <span class="wap-badge">v2.1</span></h1>
            <p>Monitor and configure your API security layers.</p>
        </div>
        <div>
            <!-- Status Indicator -->
            <div class="status-badge" style="background-color: <?php echo $status_color; ?>;">
                <span class="dashicons <?php echo $status_icon; ?>"></span> <?php echo $status_text; ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=wp-api-protection&tab=settings"
            class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">⚙️ Configuration</a>
        <a href="?page=wp-api-protection&tab=logs"
            class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">📊 Intrusion Reports</a>
    </h2>

    <?php if ($active_tab == 'settings'): ?>
        <form method="post" action="options.php">
            <?php settings_fields('wap_options_group'); ?>
            <?php do_settings_sections('wap_options_group'); ?>

            <div class="wap-grid">
                <!-- Left Column: Settings -->
                <div class="wap-column">

                    <div class="wap-card">
                        <h2>⚙️ Configuration</h2>

                        <div class="wap-form-group">
                            <label>Security Mode</label>
                            <label style="font-weight: normal;">
                                <input type="checkbox" name="wap_hard_block_enabled" value="1" <?php checked(1, get_option('wap_hard_block_enabled'), true); ?>>
                                <strong>Enable Hard Block Mode</strong> (Private Site)
                            </label>
                            <p class="description">If enabled, ONLY Whitelisted IPs and Admins can access the API. Everyone
                                else meets a 403 wall.</p>
                        </div>

                        <div class="wap-form-group">
                            <label>PsyOps / Troll Mode</label>
                            <label style="font-weight: normal; color: #d63638;">
                                <input type="checkbox" name="wap_troll_mode_enabled" value="1" <?php checked(1, get_option('wap_troll_mode_enabled'), true); ?>>
                                <strong>Enable Troll Mode</strong> (Experimental)
                            </label>
                            <p class="description">If enabled, blocked users will see a "Hacked" screen (Browsers) or ASCII
                                Art (Terminal). Fun, but use with caution.</p>
                        </div>

                        <div class="wap-form-group">
                            <label for="wap_whitelist">Whitelist IPs (One per line)</label>
                            <textarea id="wap_whitelist" name="wap_whitelist_ips"
                                rows="5"><?php echo esc_textarea(get_option('wap_whitelist_ips')); ?></textarea>
                            <p class="description">These IPs bypass all rate limits and checks.</p>
                        </div>

                        <div class="wap-form-group">
                            <label for="wap_limit">Rate Limit Threshold</label>
                            <input type="number" id="wap_limit" name="wap_rate_limit_max"
                                value="<?php echo esc_attr(get_option('wap_rate_limit_max', 5)); ?>">
                            <p class="description">Number of attempts allowed before temporary block.</p>
                        </div>
                    </div>

                    <div class="wap-card">
                        <h2>📜 Custom Messages (Biblical)</h2>
                        <?php
                        $messages = get_option('wap_custom_messages', array());
                        $defaults = array(
                            'blocked' => '🚫 Has sido sellado fuera por un tiempo...',
                            'final_block' => '🔒 Has agotado la gracia...',
                            'grace' => '🙏 Has llegado al umbral...'
                        );
                        ?>
                        <div class="wap-form-group">
                            <label>Blocked Message</label>
                            <input type="text" name="wap_custom_messages[blocked]"
                                value="<?php echo esc_attr(isset($messages['blocked']) ? $messages['blocked'] : $defaults['blocked']); ?>">
                        </div>
                        <div class="wap-form-group">
                            <label>Final Block Message</label>
                            <input type="text" name="wap_custom_messages[final_block]"
                                value="<?php echo esc_attr(isset($messages['final_block']) ? $messages['final_block'] : $defaults['final_block']); ?>">
                        </div>
                        <div class="wap-form-group">
                            <label>Grace Warning Message</label>
                            <input type="text" name="wap_custom_messages[grace]"
                                value="<?php echo esc_attr(isset($messages['grace']) ? $messages['grace'] : $defaults['grace']); ?>">
                        </div>
                    </div>

                    <div class="wap-submit">
                        <?php submit_button('Save Security Settings', 'primary large'); ?>
                    </div>

                </div>

                <!-- Right Column: Stats -->
                <div class="wap-column">
                    <div class="wap-card">
                        <h2>📊 System Status</h2>
                        <div style="text-align: center; padding: 20px;">
                            <div class="wap-stat"><?php echo $hard_block ? 'Hard Block' : 'Rate Limit'; ?></div>
                            <div class="wap-stat-label">Active Mode</div>
                        </div>
                        <hr>
                        <div style="text-align: center; padding: 20px;">
                            <div class="wap-stat">
                                <?php echo count(explode("\n", get_option('wap_whitelist_ips', ''))) - (empty(get_option('wap_whitelist_ips')) ? 1 : 0); ?>
                            </div>
                            <div class="wap-stat-label">Whitelisted IPs</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    <?php else: ?>

        <div class="wap-card">
            <h2>🚨 Intrusion Attempts (Last 50)</h2>
            <?php
            if (class_exists('WaP_Logger')) {
                $logs = WaP_Logger::get_logs();
                if ($logs) {
                    echo '<table class="wap-log-table">';
                    echo '<thead><tr><th>Time</th><th>IP</th><th>Type</th><th>Target URL</th><th>Reason</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($logs as $log) {
                        echo '<tr>';
                        echo '<td>' . $log->time . '</td>';
                        echo '<td><strong style="color: #d63638;">' . $log->ip . '</strong></td>';
                        echo '<td>' . $log->type . '</td>';
                        echo '<td><code>' . (isset($log->request_url) ? $log->request_url : 'N/A') . '</code></td>';
                        echo '<td>' . $log->reason . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>No intrusions detected yet. The watchmen are silent.</p>';
                }
            } else {
                echo '<p>Logger not installed.</p>';
            }
            ?>
        </div>

    <?php endif; ?>
</div>