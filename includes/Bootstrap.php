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
     *
     * TODO [BOOT-001]: 'Events/class-ls-events' and 'Events/class-ls-form-events' use
     * legacy hyphenated filenames. load_component() builds the class-existence check as
     * 'LS\Events\class-ls-events' — a PHP-invalid class name that will ALWAYS evaluate
     * false, so the guard never fires. The files ARE loaded via the file_exists fallback,
     * but the duplicate-load protection is broken for these two entries. Consider renaming
     * the files to 'LS_Events.php' / 'LS_Form_Events.php' (PSR-4) or using the plain
     * class name as the key (e.g. 'Events/LS_Events').
     */
    private static array $core_components = [
        'Utils',
        'Admin/Assets',
    'Events/class-ls-events',       // TODO [BOOT-001] hyphenated name breaks class_exists guard — see above
    'Events/class-ls-form-events',  // TODO [BOOT-001] same issue
        'Setup/Installer',
        'Frontend/Injector',
        'Frontend/RedirectHandler',
    ];

    /**
     * Admin-only components
     *
     * TODO [BOOT-002]: 'Admin/Health', 'Repository/LinksRepository', and 'Export/Exporters'
     * are loaded here but NEVER initialized in initialize_components(). They have no
     * standalone init() / boot() methods, and no class_exists() call for them exists in
     * initialize_components(). They are only useful if other code calls their static methods
     * directly after the class has been loaded — which works, but it means the load here is
     * implicit and easy to miss. At minimum, document the intent or add explicit init stubs.
     *
     * TODO [BOOT-003]: 'AJAX/DashboardHandler' (includes/AJAX/DashboardHandler.php) is NOT
     * listed here and is therefore never loaded or initialized. The class registers an AJAX
     * action (wp_ajax_leadstream_dashboard_data) in its init() method. Currently that AJAX
     * endpoint is dead/unreachable.
     */
    private static array $admin_components = [
        'Admin/Health',               // TODO [BOOT-002] loaded but never init()-ed
        'Admin/Settings',
    'Admin/DashboardAdmin',
        'Admin/LinksDashboard',
        'Repository/Db',
        'Repository/LinksRepository', // TODO [BOOT-002] loaded but never init()-ed
        'Repository/ClicksRepositoryInterface',
        'Repository/ClicksRepository',
        'Export/Exporters',           // TODO [BOOT-002] loaded but never init()-ed
        'AJAX/UTMHandler',
        'AJAX/PhoneHandler',
        'AJAX/DashboardHandler',
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
     *
     * TODO [BOOT-004]: load_legacy_components() is NEVER CALLED from init().
     * LS_Callbar.php is therefore never require_once'd via this path.
     * initialize_components() checks class_exists('LS\\LS_Callbar') before calling
     * init() — but since the file was never loaded, the class does not exist, and the
     * call bar is silently skipped on every page load. The call bar will NOT render.
     * Fix: add  self::load_legacy_components();  inside init() before
     * initialize_components() is called, or move 'LS_Callbar' into $core_components.
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

        self::load_legacy_components();

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
     *
     * TODO [BOOT-005]: The duplicate-load guard uses the class name derived from
     * $component_path (e.g. 'LS\Events\class-ls-events'), which may NOT match the
     * actual class declared inside the file (e.g. 'LS\Events\LS_Events').
     * For hyphenated paths such as 'Events/class-ls-events', the derived name is a
     * PHP-invalid class name, so class_exists() always returns false and the file
     * is required unconditionally on every call. This is harmless at present (all
     * callers use require_once so the file is only parsed once), but the guard
     * should be fixed to use the real class name, or the component keys in
     * $core_components should be updated to match the actual class names.
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

            // TODO [BOOT-002]: LS\Admin\Health is loaded in $admin_components but
            // has no init() / boot() method and is never explicitly initialized here.
            // It works only because Settings.php calls Health::render_phone_panel()
            // directly. This is fine but worth noting — if Health ever gains hooks
            // it will need an init() call added here.

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

            if (class_exists('LS\\AJAX\\DashboardHandler')) {
                \LS\AJAX\DashboardHandler::init();
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
