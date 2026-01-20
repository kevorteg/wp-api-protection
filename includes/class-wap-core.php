<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class WaP_Core
{

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     */
    protected $loader;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies()
    {
        // Protection Logic (The Guard)
        require_once WAP_PLUGIN_DIR . 'includes/class-wap-protection.php';
        require_once WAP_PLUGIN_DIR . 'includes/class-wap-rate-limit.php';
        require_once WAP_PLUGIN_DIR . 'includes/class-wap-logger.php';

        // Admin Interface
        if (is_admin()) {
            require_once WAP_PLUGIN_DIR . 'admin/class-wap-admin.php';
        }
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private function set_locale()
    {
        // load_plugin_textdomain( 'wp-api-protection', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
    }

    /**
     * Register authentication hooks.
     * This is where the magic happens.
     */
    private function define_public_hooks()
    {
        // Initialize Rate Limiter
        $rate_limiter = new WaP_Rate_Limit();
        add_filter('rest_authentication_errors', array($rate_limiter, 'check_rate_limit'), 10);

        // Initialize Hard Protection (Whitelist/Roles)
        // Priority 20 to run AFTER rate limit checks (or before depending on strategy, but usually rate limit first to save resources, or whitelist first to bypass rate limit)
        // Strategy: Whitelist bypasses everything. Blocked roles get blocked immediately.
        $protection = new WaP_Protection();
        add_filter('rest_authentication_errors', array($protection, 'check_access_rules'), 5);

        // Block Author Enumeration (Priority 1 to run before WP Canonical Redirects)
        add_action('template_redirect', array($protection, 'block_author_scanning'), 1);

        // Hide WordPress Version (Generator meta)
        add_filter('the_generator', '__return_empty_string');

        // Hide WordPress Version (Scripts & Styles)
        add_filter('style_loader_src', array($this, 'remove_wp_version_strings'), 10, 2);
        add_filter('script_loader_src', array($this, 'remove_wp_version_strings'), 10, 2);
    }

    /**
     * Removes the ?ver=X.X.X string from URLs.
     */
    public function remove_wp_version_strings($src)
    {
        if (strpos($src, 'ver=' . get_bloginfo('version')))
            $src = remove_query_arg('ver', $src);
        return $src;
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks()
    {
        if (is_admin()) {
            $plugin_admin = new WaP_Admin();
            add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
            add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
            add_action('admin_init', array($plugin_admin, 'register_settings'));

            // Load Dashboard Widget
            require_once WAP_PLUGIN_DIR . 'includes/class-wap-dashboard.php';
            new WaP_Dashboard();
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run()
    {
        // Hooks are identified in constructor
    }
}
