<?php

class WaP_Logger
{

    private static $table_name;

    public static function init()
    {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'wap_logs';
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
        $url = esc_url_raw("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

        $wpdb->insert(
            self::$table_name,
            array(
                'time' => current_time('mysql'),
                'ip' => $ip,
                'type' => $type,
                'reason' => $reason,
                'request_url' => $url
            )
        );
    }

    /**
     * Determines if client is CLI or Browser for the log.
     */
    private static function get_client_type()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        if (strpos($ua, 'curl') !== false || strpos($ua, 'wget') !== false || strpos($ua, 'python') !== false) {
            return 'CLI/Bot';
        }
        return 'Browser';
    }

    /**
     * Retrieves recent logs.
     */
    public static function get_logs($limit = 50)
    {
        self::init();
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . self::$table_name . " ORDER BY id DESC LIMIT $limit");
    }
}
