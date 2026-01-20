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
                // 'request_type' => $type_check 
            )
        );

        // --- LÓGICA DE ALERTA ---
        self::check_attack_threshold();
    }

    private static function check_attack_threshold()
    {
        // 1. Obtener contador actual
        $attacks = get_transient('wap_attack_counter') ?: 0;
        $attacks++;
        set_transient('wap_attack_counter', $attacks, 300); // Expira en 5 minutos

        // 2. Verificar si cruzamos la línea roja (20 ataques)
        // Usar opción por si queremos configurar esto luego
        $threshold = get_option('wap_alert_threshold', 20);

        if ($attacks > $threshold) {
            // Verificar si ya enviamos alerta recientemente (Cooldown de 1 hora)
            if (!get_transient('wap_alert_cooldown')) {

                $admin_email = get_option('admin_email');
                $subject = '🚨 ALERTA CRÍTICA: Ataque Masivo en Curso - ' . get_bloginfo('name');
                $message = "El sistema ha detectado más de $threshold intentos de intrusión en los últimos 5 minutos.\n\n";
                $message .= "Revise los logs inmediatamente en el panel de WP API Protection.\n";
                $message .= "Modo de Defensa: ACTIVO.";

                wp_mail($admin_email, $subject, $message);

                // Activar Cooldown para no spamear al admin
                set_transient('wap_alert_cooldown', true, HOUR_IN_SECONDS);
            }
        }
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
