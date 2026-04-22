<?php
/**
 * Test script for the new Bootstrap loading system
 */

// Define constants for testing
define('LS_FILE', __DIR__ . '/leadstream-analytics-injector.php');
define('LS_VERSION', '2.12.2');
define('LS_PLUGIN_DIR', __DIR__ . '/');
define('LS_PLUGIN_URL', 'http://localhost/wp-content/plugins/Lead-stream-pro/');

// Mock WordPress functions for testing
if (!function_exists('is_admin')) {
    function is_admin() { return true; }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        echo "Activation hook registered for: $file\n";
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        echo "Action registered: $hook\n";
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback) {
        echo "Filter registered: $hook\n";
    }
}

// Test the Bootstrap system
echo "=== Testing LeadStream Bootstrap System ===\n\n";

try {
    require_once __DIR__ . '/includes/Bootstrap.php';
    echo "✓ Bootstrap class loaded successfully\n";

    // Test initialization (without actually running it to avoid WordPress dependencies)
    $bootstrap = new ReflectionClass('LeadStream\\Bootstrap');
    echo "✓ Bootstrap class structure is valid\n";

    $methods = $bootstrap->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);
    echo "✓ Available public static methods:\n";
    foreach ($methods as $method) {
        echo "  - {$method->getName()}()\n";
    }

    echo "\n✓ Bootstrap system test completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
