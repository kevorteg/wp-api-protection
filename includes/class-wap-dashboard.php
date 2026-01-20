<?php
class WaP_Dashboard
{

    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    public function add_dashboard_widget()
    {
        wp_add_dashboard_widget(
            'wap_dashboard_status',
            '🛡️ Estado de Seguridad (WP API Protection)',
            [$this, 'render_widget']
        );
    }

    public function render_widget()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wap_logs'; // Using generic name based on previous steps

        // Contar ataques de HOY
        // NOTE: "time" column is datetime. 
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE time >= CURDATE()");

        // Último ataque
        $last = $wpdb->get_row("SELECT * FROM $table ORDER BY time DESC LIMIT 1");

        echo '<div style="text-align:center; padding:10px;">';
        echo '<h2 style="font-size: 40px; color: #46b450; margin:0;">' . esc_html($count) . '</h2>';
        echo '<p style="color:#666; text-transform:uppercase; font-size:11px; font-weight:bold;">Amenazas Bloqueadas Hoy</p>';
        echo '<hr>';

        if ($last) {
            echo '<p><strong>Último bloqueo:</strong> ' . esc_html($last->ip) . '</p>';
            echo '<p style="color:#d63638; font-size:12px;">' . esc_html($last->reason) . '</p>';
        } else {
            echo '<p>Todo tranquilo por ahora. 👮‍♂️</p>';
        }

        echo '<br><a href="options-general.php?page=wp-api-protection&tab=logs" class="button">Ver Reporte Completo</a>';
        echo '</div>';
    }
}
