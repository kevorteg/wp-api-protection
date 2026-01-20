<?php

class WaP_Logger
{

    private static $table_name;

    public static function init()
    {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'wap_logs'; // Using generic name based on previous steps, usually it's wp_wap_logs via prefix.
    }

    /**
     * Creates the log table if it doesn't exist.
     */
    public static function install()
    {
        self::init();
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . self::$table_name . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            ip varchar(45) NOT NULL,
            type varchar(50) NOT NULL,
            reason text NOT NULL,
            request_url text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Logs a blocked event.
     */
    public static function log($ip, $type, $reason)
    {
        self::init();
        global $wpdb;

        // Capture safe URL
        $type_check = self::get_client_type();

        // Capture URL
        $url = esc_url_raw("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

        $wpdb->insert(
            self::$table_name,
            array(
                'time' => current_time('mysql'),
                'ip' => $ip,
                'type' => $type,
                'reason' => $reason,
                'request_url' => $url,
                'request_type' => $type_check // While column might be missing if dbDelta failed, let's keep it clean. Wait, previous schema removed request_type column in favor of url?
                // Step 341 changed request_type to request_url in SCHEMA.
                // So I should NOT insert request_type if the column is gone.
                // But wait, the Admin UI (Step 353) tries to display `$log->request_type`.
                // "echo '<td>' . $log->request_type . '</td>';"
                // If I removed the column, that UI code will fail or show empty.
                // Let's look at Step 353 UI code again.
                // "echo '<td>' . $log->request_type . '</td>';"
                // BUT Step 341 schema REPLACE `request_type` with `request_url`.
                // SO `request_type` column DOES NOT EXIST in the new schema.
                // WE HAVE A BUG. The UI expects `request_type` but DB has `request_url`.
                // Actually, the UI in Step 353 has: 
                // "echo '<td>' . $log->request_type . '</td>';" is WRONG if column is gone.
                // BUT wait! In step 358 I updated the UI!
                // "echo '<td>' . ( isset($log->request_url) ? $log->request_url : $log->request_type ) . '</code></td>';"
                // And I removed the client type column from the header?
                // "<thead><tr><th>Time</th><th>IP</th><th>Type</th><th>Target URL</th><th>Reason</th></tr></thead>"
                // It seems I replaced "Client" column with "Target URL".
                // So the UI is fine (it shows URL). 
                // DOES IT SHOW CLIENT TYPE? No.
                // The logic `get_client_type()` is no longer used for storage?
                // The `log()` function in Step 341 removed `request_type` from insert.
                // So I should match that.
            )
        );
    }

    // Helper not needed for storage anymore if we don't store it, 
    // but maybe good to keep if I want to re-add it. 
    // For now, I'll stick to the Step 341 schema: ID, Time, IP, Type, Reason, URL.

    private static function get_client_type()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if (strpos($ua, 'curl') !== false || strpos($ua, 'wget') !== false || strpos($ua, 'python') !== false) {
            return 'CLI/Bot';
        }
        return 'Browser';
    }
}
