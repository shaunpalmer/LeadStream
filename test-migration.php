<?php
/**
 * Test script for LeadStream database migration
 * Run this to test the option prefix migration from ays_ to leadstream_
 */

// Load WordPress
require_once dirname(__DIR__) . '/wp-load.php';

echo "=== LeadStream Database Migration Test ===\n\n";

// Test the upgrader
require_once __DIR__ . '/includes/upgrades/class-ls-upgrader.php';

$upgrader = new \LeadStream\Upgrades\Upgrader();

// Check current migration status
echo "Migration Status:\n";
echo "- Is migrated: " . ($upgrader->is_migrated() ? 'YES' : 'NO') . "\n";
echo "- Migration time: " . ($upgrader->get_migration_time() ?: 'Never') . "\n\n";

// Check for options with old prefix
global $wpdb;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('ays_') . '%'
));

echo "Options with 'ays_' prefix:\n";
if ($rows) {
    foreach ($rows as $row) {
        echo "- {$row->option_name}\n";
    }
} else {
    echo "- None found\n";
}
echo "\n";

// Check for already migrated options
$migrated_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like('leadstream_') . '%'
));

echo "Options with 'leadstream_' prefix:\n";
if ($migrated_rows) {
    foreach ($migrated_rows as $row) {
        echo "- {$row->option_name}\n";
    }
} else {
    echo "- None found\n";
}
echo "\n";

// Run migration if not already done
if (!$upgrader->is_migrated()) {
    echo "Running migration...\n";
    $upgrader->maybe_migrate_options();
    echo "Migration completed!\n";
    echo "- New migration status: " . ($upgrader->is_migrated() ? 'YES' : 'NO') . "\n";
} else {
    echo "Migration already completed, skipping.\n";
}

echo "\n=== Test Complete ===\n";
