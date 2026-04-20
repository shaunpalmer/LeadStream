<?php
\spl_autoload_register(function ($class) {
    // PSR-4 for our namespace
    $prefix = 'LeadStream\\';

    // Prefer WP helper when available: pass the main plugin file so plugin_dir_path
    // returns the plugin root. If the main plugin file is missing or helper
    // isn't available, fall back to dirname(__DIR__). This avoids includes/includes
    // duplication when called from inside includes/.
    $main_plugin_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'leadstream-analytics-injector.php';
    if (function_exists('plugin_dir_path') && file_exists($main_plugin_file)) {
        $plugin_root = plugin_dir_path($main_plugin_file);
    } else {
        $plugin_root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }

    // Base includes directory (normalized)
    $base = $plugin_root . 'includes' . DIRECTORY_SEPARATOR;
    $base_real = realpath($base);
    if ($base_real !== false) {
        // ensure trailing separator and consistent directory separators
        $base = rtrim($base_real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $rel = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', '/', $rel) . '.php';
        if (is_readable($file)) {
            require $file;
            return;
        }
    }

    // Fallback: allow namespaced LeadStream\... short class names to map to
    // legacy files located in known directories (helps when namespaced
    // files exist as legacy class-ls-*.php files under includes/Admin/...)
    $parts = explode('\\', $class);
    $short = end($parts);
    if ($short && strpos($class, $prefix) === 0) {
        // parent segment (if any) — e.g. Admin\Dashboard -> 'dashboard'
        $parent = count($parts) >= 2 ? $parts[count($parts) - 2] : '';
        $parent_l = strtolower($parent);
        // Convert CamelCase short name to kebab-case (LinksDashboard -> links-dashboard)
        $short_kebab = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $short));

        // If parent is 'Dashboard' and parent != short, map to class-ls-dashboard-{component}.php
        if ($parent_l === 'dashboard' && strtolower($short) !== $parent_l) {
            $slug = 'class-ls-dashboard-' . $short_kebab . '.php';
        } else {
            // Default: class-ls-{short}.php
            $slug = 'class-ls-' . $short_kebab . '.php';
        }

        // Use directory names that match repository casing so this works on case-sensitive filesystems
        $legacyDirs = [
            $base,
            $base . 'Admin' . DIRECTORY_SEPARATOR,
            $base . 'Admin' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR,
            $base . 'Frontend' . DIRECTORY_SEPARATOR,
            $base . 'AJAX' . DIRECTORY_SEPARATOR,
            $base . 'Repository' . DIRECTORY_SEPARATOR,
            $base . 'REST' . DIRECTORY_SEPARATOR,
            $base . 'Setup' . DIRECTORY_SEPARATOR,
            $base . 'Updates' . DIRECTORY_SEPARATOR,
        ];
        foreach ($legacyDirs as $p) {
            $f = $p . $slug;
            if (is_readable($f)) {
                require $f;
                return;
            }
        }
    }

    // Classic WP style: LS_* maps to class-ls-*.php in subfolders we know
    // TODO [AUTO-001]: Case-sensitivity hazard — the autoloader builds the slug with
    // strtolower(), so 'LS_Cookies' becomes 'class-ls-cookies.php'. On a Linux
    // (case-sensitive) filesystem the actual files are named 'class-LS-Cookies.php'
    // (uppercase LS). They will NEVER be found by this autoloader. LS_Cookies must
    // either be renamed to 'class-ls-cookies.php' (lowercase) OR be loaded with an
    // explicit require_once before the class is first used.
    //
    // TODO [AUTO-002]: Even if the filename case issue is fixed, both
    // includes/class-LS-Cookies.php AND includes/Tracking/class-LS-Cookies.php define
    // the same global class 'LS_Cookies'. If both are somehow loaded, PHP will throw a
    // fatal "Cannot declare class LS_Cookies" redeclaration error. Only one copy should
    // exist. The root copy (includes/class-LS-Cookies.php) is also truncated — see
    // TODO [COOKIE-001] in that file.
    if (strpos($class, 'LS_') === 0) {
        $slug = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';
        $paths = [
            $base,
            $base . 'Admin' . DIRECTORY_SEPARATOR,
            $base . 'Admin' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR,
            $base . 'AJAX' . DIRECTORY_SEPARATOR,
            $base . 'Export' . DIRECTORY_SEPARATOR,
            $base . 'Frontend' . DIRECTORY_SEPARATOR,
            $base . 'License' . DIRECTORY_SEPARATOR,
            $base . 'Repository' . DIRECTORY_SEPARATOR,
            $base . 'REST' . DIRECTORY_SEPARATOR,
            $base . 'Setup' . DIRECTORY_SEPARATOR,
            $base . 'Tracking' . DIRECTORY_SEPARATOR,
            $base . 'Updates' . DIRECTORY_SEPARATOR,
        ];
        foreach ($paths as $p) {
            $f = $p . $slug;
            if (is_readable($f)) {
                require $f;
                return;
            }
        }
    }

    // Handle direct class names without namespace (legacy support)
    $class_name = basename(str_replace('\\', '/', $class));
    $legacy_paths = [
        $base . $class_name . '.php',
        $base . 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php',
    ];

    foreach ($legacy_paths as $path) {
        if (is_readable($path)) {
            require $path;
            return;
        }
    }
});

// Legacy shims: keep AYS_* working temporarily
$compat_file = dirname(__DIR__) . '/includes/compat/class-ls-legacy-shims.php';
if (file_exists($compat_file)) {
    require_once $compat_file;
}