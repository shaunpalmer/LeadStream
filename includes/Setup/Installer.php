<?php
namespace LS\Setup;

/**
 * Handles plugin activation, deactivation, and database setup.
 * Implements the Factory pattern for database table creation.
 */
class Installer {

    /**
     * Hook into plugin activation & deactivation.
     */
    public static function init() {
        // LS_FILE is defined in our main plugin file
        register_activation_hook( LS_FILE, [ __CLASS__, 'activate' ] );
        register_deactivation_hook( LS_FILE, [ __CLASS__, 'deactivate' ] );
    }

    /**
     * On activation: create our tables and flush rewrites.
     * Uses the Template Method pattern for consistent setup.
     */
    public static function activate() {
        error_log('LeadStream: Installer::activate() called');
        self::create_tables();
        self::flush_rewrite_rules();
        error_log('LeadStream: Tables created and rewrites flushed');
    }

    /**
     * On deactivation: just flush rewrite rules.
     */
    public static function deactivate() {
        self::flush_rewrite_rules();
    }

    /**
     * Create database tables using dbDelta for safe execution.
     * Factory pattern for table creation.
     */
    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Links table schema
        $sql_links = "
            CREATE TABLE {$wpdb->prefix}ls_links (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug       VARCHAR(64) NOT NULL UNIQUE,
                target_url TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug_unique (slug)
            ) $charset_collate;
        ";

        // Clicks table schema
        $sql_clicks = "
            CREATE TABLE {$wpdb->prefix}ls_clicks (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                link_id     BIGINT UNSIGNED NOT NULL,
                clicked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_address  VARCHAR(45),
                user_agent  VARCHAR(255),
                PRIMARY KEY (id),
                KEY link_idx (link_id),
                CONSTRAINT fk_ls_clicks_link
                    FOREIGN KEY (link_id)
                    REFERENCES {$wpdb->prefix}ls_links(id)
                    ON DELETE CASCADE
            ) $charset_collate;
        ";

        error_log('LeadStream: About to create tables...');
        $result1 = dbDelta( $sql_links );
        $result2 = dbDelta( $sql_clicks );
        error_log('LeadStream: dbDelta results - Links: ' . print_r($result1, true));
        error_log('LeadStream: dbDelta results - Clicks: ' . print_r($result2, true));
    }

    /**
     * Flush rewrite rules to ensure our /l/slug pattern works.
     */
    private static function flush_rewrite_rules() {
        flush_rewrite_rules();
    }
}

// DEBUG: Adding error log to verify activation
