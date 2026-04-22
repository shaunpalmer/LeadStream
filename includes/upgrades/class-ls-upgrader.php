<?php
/**
 * LeadStream Database Upgrader
 *
 * Handles one-time migrations when upgrading from free version to pro version.
 * Migrates option prefixes from 'ays_' to 'leadstream_' to maintain user settings.
 */

namespace LeadStream\Upgrades;

class Upgrader
{
    /**
     * Run option prefix migration if not already completed
     *
     * Migrates all WordPress options from 'ays_' prefix (free version)
     * to 'leadstream_' prefix (pro version) to preserve user settings.
     */
    public function maybe_migrate_options(): void
    {
        // Check if migration already completed
        $done = get_option('leadstream_prefix_migrated', 0);
        if ($done) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream Migration: Already completed on ' . date('Y-m-d H:i:s', $done));
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LeadStream Migration: Starting option prefix migration from ays_ to leadstream_');
        }

        global $wpdb;

        // Find all options with old 'ays_' prefix
        $like = $wpdb->esc_like('ays_') . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like
        ));

        $migrated_count = 0;
        if ($rows) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream Migration: Found ' . count($rows) . ' options with ays_ prefix');
            }

            foreach ($rows as $row) {
                // Create new option name with 'leadstream_' prefix
                $new_name = preg_replace('/^ays_/', 'leadstream_', $row->option_name, 1);

                // Only migrate if new option doesn't already exist
                if ($new_name && $new_name !== $row->option_name && get_option($new_name, null) === null) {
                    // Preserve serialized data structure
                    $value = maybe_unserialize($row->option_value);
                    add_option($new_name, $value);
                    $migrated_count++;

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            'LeadStream Migration: Migrated %s → %s',
                            $row->option_name,
                            $new_name
                        ));
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            'LeadStream Migration: Skipped %s (target %s already exists or invalid)',
                            $row->option_name,
                            $new_name
                        ));
                    }
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream Migration: No options with ays_ prefix found');
            }
        }

        // Mark migration as complete with timestamp
        update_option('leadstream_prefix_migrated', time());

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'LeadStream Migration: Completed successfully. Migrated %d options.',
                $migrated_count
            ));
        }
    }

    /**
     * Check if migration has been completed
     */
    public function is_migrated(): bool
    {
        return (bool) get_option('leadstream_prefix_migrated', 0);
    }

    /**
     * Get migration timestamp (0 if not migrated)
     */
    public function get_migration_time(): int
    {
        return (int) get_option('leadstream_prefix_migrated', 0);
    }
}