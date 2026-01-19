<?php

class WaP_Admin
{

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'WP API Protection',
            'API Protection',
            'manage_options',
            'wp-api-protection',
            array($this, 'display_plugin_setup_page'),
            'dashicons-shield',
            100
        );
    }

    public function display_plugin_setup_page()
    {
        include_once WAP_PLUGIN_DIR . 'admin/views/html-settings.php';
    }

    public function register_settings()
    {
        register_setting('wap_options_group', 'wap_whitelist_ips');
        register_setting('wap_options_group', 'wap_rate_limit_max');
        register_setting('wap_options_group', 'wap_hard_block_enabled');
        register_setting('wap_options_group', 'wap_custom_messages');
    }

    public function enqueue_styles()
    {
        // Enqueue styles if we had a CSS file.
        // wp_enqueue_style( 'wap-admin-style', WAP_PLUGIN_URL . 'admin/css/wap-admin.css', array(), WAP_VERSION, 'all' );
    }
}
