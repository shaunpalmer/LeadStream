<?php
/**
 * LeadStream Plugin Bootstrap
 *
 * Centralized loading and initialization system
 * Handles autoloading, component registration, and WordPress hooks
 */

namespace LeadStream;

class Bootstrap
{
    /**
     * Core components that must be loaded early
     */
    private static array $core_components = [
        'Utils',
        'Admin/Assets',
    'Events/class-ls-events',
    'Events/class-ls-form-events',
        'Setup/Installer',
        'Frontend/Injector',
        'Frontend/RedirectHandler',
    ];

    /**
     * Admin-only components
     */
    private static array $admin_components = [
        'Admin/Health',
        'Admin/Settings',
    'Admin/DashboardAdmin',
        'Admin/LinksDashboard',
        'Repository/LinksRepository',
        'Repository/ClicksRepositoryInterface',
        'Repository/ClicksRepository',
        'Export/Exporters',
        'AJAX/UTMHandler',
        'AJAX/PhoneHandler',
        'REST/CallsWebhook',
    ];

    /**
     * Frontend-only components
     */
    private static array $frontend_components = [
        'AJAX/PhoneHandler',
        'REST/CallsWebhook',
    ];

    /**
     * Optional licensing components (loaded conditionally)
     */
    private static array $licensing_components = [
        'License/ApiClient',
        'License/Manager',
        'License/AdminTab',
    ];

    /**
     * Legacy components (keep for backward compatibility)
     */
    private static array $legacy_components = [
        'LS_Callbar',
    ];

    /**
     * Initialize the plugin
     */
    public static function init(): void
    {
        // Load autoloader first
        self::load_autoloader();

        // Register activation hook
        register_activation_hook(LS_FILE, [self::class, 'activate']);

        // Initialize core components
        self::load_core_components();

        // Load context-specific components
        if (is_admin()) {
            self::load_admin_components();
            self::load_licensing_components();
        } else {
            self::load_frontend_components();
        }

        // Initialize all loaded components
        self::initialize_components();

        // Register WordPress hooks
        self::register_hooks();
    }

    /**
     * Load the autoloader
     */
    private static function load_autoloader(): void
    {
        require_once plugin_dir_path(LS_FILE) . 'includes/autoload.php';
    }

    /**
     * Load core components required for both admin and frontend
     */
    private static function load_core_components(): void
    {
        foreach (self::$core_components as $component) {
            self::load_component($component);
        }
    }

    /**
     * Load admin-specific components
     */
    private static function load_admin_components(): void
    {
        foreach (self::$admin_components as $component) {
            self::load_component($component);
        }
    }

    /**
     * Load frontend-specific components
     */
    private static function load_frontend_components(): void
    {
        foreach (self::$frontend_components as $component) {
            self::load_component($component);
        }
    }

    /**
     * Load licensing components if available
     */
    private static function load_licensing_components(): void
    {
        $plugin_dir = plugin_dir_path(LS_FILE);

        foreach (self::$licensing_components as $component) {
            $file_path = $plugin_dir . 'includes/' . str_replace('/', DIRECTORY_SEPARATOR, $component) . '.php';
            if (file_exists($file_path)) {
                self::load_component($component);
            }
        }
    }

    /**
     * Load legacy components
     */
    private static function load_legacy_components(): void
    {
        foreach (self::$legacy_components as $component) {
            self::load_component($component);
        }
    }

    /**
     * Load a single component by class path
     */
    private static function load_component(string $component_path): void
    {
        $class_name = 'LS\\' . str_replace('/', '\\', $component_path);

        // Try PSR-4 first, then legacy loading
        if (!class_exists($class_name)) {
            $file_path = plugin_dir_path(LS_FILE) . 'includes/' . str_replace('/', DIRECTORY_SEPARATOR, $component_path) . '.php';
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize all loaded components
     */
    private static function initialize_components(): void
    {
        // Initialize core components
        if (class_exists('LS\\Setup\\Installer')) {
            \LS\Setup\Installer::init();
        }

        if (class_exists('LS\\Frontend\\Injector')) {
            \LS\Frontend\Injector::init();
        }

        if (class_exists('LS\\Frontend\\RedirectHandler')) {
            \LS\Frontend\RedirectHandler::init();
        }

        if (class_exists('LS\\Admin\\Assets')) {
            \LS\Admin\Assets::init();
        }

        if (class_exists('LS\\Admin\\DashboardAdmin')) {
            \LS\Admin\DashboardAdmin::init();
        }

        if (class_exists('LS\\Events\\LS_Events')) {
            \LS\Events\LS_Events::init();
        }
        if (class_exists('LS\\Events\\LS_Form_Events')) {
            \LS\Events\LS_Form_Events::init();
        }

        // Initialize admin components
        if (is_admin()) {
            if (class_exists('LS\\Admin\\Settings')) {
                \LS\Admin\Settings::init();
            }

            if (class_exists('LS\\Admin\\LinksDashboard')) {
                \LS\Admin\LinksDashboard::init();
            }

            if (class_exists('LS\\AJAX\\UTMHandler')) {
                \LS\AJAX\UTMHandler::init();
            }

            if (class_exists('LS\\AJAX\\PhoneHandler')) {
                \LS\AJAX\PhoneHandler::init();
            }

            if (class_exists('LS\\REST\\CallsWebhook')) {
                \LS\REST\CallsWebhook::init();
            }

            // Initialize licensing if available
            if (class_exists('LS\\License\\AdminTab')) {
                \LS\License\AdminTab::boot();
            }
        } else {
            // Initialize frontend components
            if (class_exists('LS\\AJAX\\PhoneHandler')) {
                \LS\AJAX\PhoneHandler::init();
            }

            if (class_exists('LS\\REST\\CallsWebhook')) {
                \LS\REST\CallsWebhook::init();
            }
        }

        // Initialize legacy components
        if (class_exists('LS\\LS_Callbar')) {
            \LS\LS_Callbar::init();
        }
    }

    /**
     * Register WordPress hooks
     */
    private static function register_hooks(): void
    {
        // Plugin upgrade/migration hook
        add_action('plugins_loaded', [self::class, 'handle_upgrade']);

        // Free edition filter
        add_filter('ls_is_pro', [self::class, 'is_pro_filter']);
    }

    /**
     * Handle plugin upgrades and migrations
     */
    public static function handle_upgrade(): void
    {
        // Load upgrader class
        $upgrader_class = 'LeadStream\\Upgrades\\Upgrader';
        if (!class_exists($upgrader_class)) {
            require_once plugin_dir_path(LS_FILE) . 'includes/upgrades/class-ls-upgrader.php';
        }

        if (class_exists($upgrader_class)) {
            (new $upgrader_class())->maybe_migrate_options();
        }
    }

    /**
     * Plugin activation handler
     */
    public static function activate(): void
    {
        // Set default options if needed
        if (!get_option('leadstream_version')) {
            update_option('leadstream_version', '2.12.2');
        }
    }

    /**
     * Filter for pro edition check (always false for free)
     */
    public static function is_pro_filter($val = false): bool
    {
        return false;
    }
}
