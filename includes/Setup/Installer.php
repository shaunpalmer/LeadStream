<?php
namespace LS\Setup;

/**
 * Handles plugin activation, deactivation, and database setup.
 * Implements the Factory pattern for database table creation.
 */
class Installer {

    private const SCHEMA_VERSION_OPTION = 'ls_schema_version';

    /**
     * Hook into plugin activation & deactivation.
     */
    public static function init() {
        // LS_FILE is defined in our main plugin file
        register_activation_hook( LS_FILE, [ __CLASS__, 'activate' ] );
        register_deactivation_hook( LS_FILE, [ __CLASS__, 'deactivate' ] );

        // Keep table schemas up-to-date after plugin updates.
        add_action( 'init', [ __CLASS__, 'maybe_upgrade_schema' ], 1 );
    }

    /**
     * Ensure schemas are upgraded after plugin updates.
     */
    public static function maybe_upgrade_schema(): void {
        if ( ! defined( 'LS_VERSION' ) ) {
            return;
        }

        $stored = get_option( self::SCHEMA_VERSION_OPTION, '0.0.0' );
        if ( is_string( $stored ) && version_compare( $stored, LS_VERSION, '>=' ) && ! self::schema_requires_migration() ) {
            return;
        }

        try {
            self::create_tables();
            self::migrate_existing_tables();
            update_option( self::SCHEMA_VERSION_OPTION, LS_VERSION, true );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'LeadStream: Schema upgrade error - ' . $e->getMessage() );
            }
        }
    }

    /**
     * Determine whether a migration should run even when the stored schema version
     * matches the plugin version.
     */
    private static function schema_requires_migration(): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ls_clicks';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
        if ( $table_exists !== $table_name ) {
            return true;
        }

        $required_columns = array(
            'origin',
            'source',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid',
            'ttclid',
            'ga_client_id',
            'ga_session_id',
            'ga_session_number',
        );

        foreach ( $required_columns as $col ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_name} LIKE %s",
                    $col
                )
            );
            if ( ! $exists ) {
                return true;
            }
        }

        return false;
    }

    /**
     * On activation: create our tables and flush rewrites.
     * Uses the Template Method pattern for consistent setup.
     */
    public static function activate() {
        // Suppress any output during activation
        ob_start();
        
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Installer::activate() called');
            }
            
            self::create_tables();
            self::migrate_existing_tables();
            self::flush_rewrite_rules();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Tables created, migrated, and rewrites flushed');
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Activation error - ' . $e->getMessage());
            }
        }
        
        // Clean any output that might have been generated
        ob_end_clean();
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
        redirect_type VARCHAR(3) NOT NULL DEFAULT '301',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug_unique (slug)
            ) $charset_collate;
        ";

        // Clicks table schema (enhanced for better phone tracking and analytics)
        $sql_clicks = "
            CREATE TABLE {$wpdb->prefix}ls_clicks (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                link_id       BIGINT UNSIGNED NULL,
                link_type     VARCHAR(32) NOT NULL DEFAULT 'link',
                link_key      VARCHAR(255) NOT NULL,
                target_url    TEXT,
                clicked_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                click_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                click_date    DATE,
                click_time    TIME,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_address    VARCHAR(45),
                user_agent    VARCHAR(512),
                user_id       BIGINT UNSIGNED NULL,
                referrer      TEXT,
                page_url      TEXT,
                page_title    VARCHAR(255),
                element_type  VARCHAR(32),
                element_class VARCHAR(255),
                element_id    VARCHAR(255),
                origin       VARCHAR(64) NULL,
                source       VARCHAR(64) NULL,
                utm_source   VARCHAR(128) NULL,
                utm_medium   VARCHAR(128) NULL,
                utm_campaign VARCHAR(191) NULL,
                utm_term     VARCHAR(191) NULL,
                utm_content  VARCHAR(191) NULL,
                gclid        VARCHAR(191) NULL,
                fbclid       VARCHAR(191) NULL,
                msclkid      VARCHAR(191) NULL,
                ttclid       VARCHAR(191) NULL,
                ga_client_id VARCHAR(64) NULL,
                ga_session_id BIGINT UNSIGNED NULL,
                ga_session_number INT UNSIGNED NULL,
                meta_data     TEXT,
                PRIMARY KEY (id),
                KEY link_idx (link_id),
                KEY link_type_idx (link_type),
                KEY link_key_idx (link_key),
                KEY clicked_at_idx (clicked_at),
                KEY click_date_idx (click_date),
                KEY click_datetime_idx (click_datetime),
                KEY origin_idx (origin),
                KEY gclid_idx (gclid),
                KEY utm_campaign_idx (utm_campaign),
                KEY ga_client_id_idx (ga_client_id),
                KEY ga_session_id_idx (ga_session_id),
                CONSTRAINT fk_ls_clicks_link
                    FOREIGN KEY (link_id)
                    REFERENCES {$wpdb->prefix}ls_links(id)
                    ON DELETE CASCADE
            ) $charset_collate;
        ";

        $result1 = dbDelta( $sql_links );
        $result2 = dbDelta( $sql_clicks );
        // Calls table for provider webhooks (Twilio/Telnyx/etc.)
        $sql_calls = "
            CREATE TABLE {$wpdb->prefix}ls_calls (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider          VARCHAR(64) NOT NULL DEFAULT '',
                provider_call_id  VARCHAR(191) NOT NULL,
                from_number       VARCHAR(64) NOT NULL DEFAULT '',
                to_number         VARCHAR(64) NOT NULL DEFAULT '',
                status            VARCHAR(32) NOT NULL DEFAULT '',
                start_time        DATETIME NULL,
                end_time          DATETIME NULL,
                duration          INT UNSIGNED NOT NULL DEFAULT 0,
                recording_url     TEXT,
                click_id          BIGINT UNSIGNED NULL,
                meta_data         LONGTEXT NULL,
                created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY provider_call (provider_call_id),
                KEY status_idx (status),
                KEY start_idx (start_time)
            ) $charset_collate;
        ";
        $result3 = dbDelta( $sql_calls );

        // Events table for storing client-side events captured via AJAX
        $sql_events = "
            CREATE TABLE {$wpdb->prefix}ls_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_name VARCHAR(191) NOT NULL,
                event_type VARCHAR(191) NOT NULL,
                event_params LONGTEXT NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type_idx (event_type),
                KEY created_at_idx (created_at)
            ) $charset_collate;
        ";
        $result4 = dbDelta( $sql_events );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LeadStream: dbDelta results - Events: ' . print_r($result4, true));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LeadStream: dbDelta results - Links: ' . print_r($result1, true));
            error_log('LeadStream: dbDelta results - Clicks: ' . print_r($result2, true));
            error_log('LeadStream: dbDelta results - Calls: ' . print_r($result3, true));
        }
    }

    /**
     * Migrate existing tables to add missing columns for existing installations
     */
    private static function migrate_existing_tables() {
        global $wpdb;
        
    $table_name = $wpdb->prefix . 'ls_clicks';
    $links_table = $wpdb->prefix . 'ls_links';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
    if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: ls_clicks table does not exist, skipping migration');
            }
            return;
        }
        
        // Check if link_type column exists
        $link_type_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'link_type'
        ));
        
        // Check if link_key column exists  
        $link_key_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'link_key'
        ));
        
        // Add missing columns
        if (!$link_type_exists) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN link_type VARCHAR(32) NOT NULL DEFAULT 'utm' AFTER link_id");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Added link_type column');
            }
        }
        
        if (!$link_key_exists) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN link_key VARCHAR(255) NULL AFTER link_type");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Added link_key column');
            }
        }

        // Marketing params + origin/source + GA session keys.
        $columns = array(
            'origin'            => "ALTER TABLE {$table_name} ADD COLUMN origin VARCHAR(64) NULL AFTER element_id",
            'source'            => "ALTER TABLE {$table_name} ADD COLUMN source VARCHAR(64) NULL AFTER origin",
            'utm_source'        => "ALTER TABLE {$table_name} ADD COLUMN utm_source VARCHAR(128) NULL AFTER source",
            'utm_medium'        => "ALTER TABLE {$table_name} ADD COLUMN utm_medium VARCHAR(128) NULL AFTER utm_source",
            'utm_campaign'      => "ALTER TABLE {$table_name} ADD COLUMN utm_campaign VARCHAR(191) NULL AFTER utm_medium",
            'utm_term'          => "ALTER TABLE {$table_name} ADD COLUMN utm_term VARCHAR(191) NULL AFTER utm_campaign",
            'utm_content'       => "ALTER TABLE {$table_name} ADD COLUMN utm_content VARCHAR(191) NULL AFTER utm_term",
            'gclid'             => "ALTER TABLE {$table_name} ADD COLUMN gclid VARCHAR(191) NULL AFTER utm_content",
            'fbclid'            => "ALTER TABLE {$table_name} ADD COLUMN fbclid VARCHAR(191) NULL AFTER gclid",
            'msclkid'           => "ALTER TABLE {$table_name} ADD COLUMN msclkid VARCHAR(191) NULL AFTER fbclid",
            'ttclid'            => "ALTER TABLE {$table_name} ADD COLUMN ttclid VARCHAR(191) NULL AFTER msclkid",
            'ga_client_id'      => "ALTER TABLE {$table_name} ADD COLUMN ga_client_id VARCHAR(64) NULL AFTER ttclid",
            'ga_session_id'     => "ALTER TABLE {$table_name} ADD COLUMN ga_session_id BIGINT UNSIGNED NULL AFTER ga_client_id",
            'ga_session_number' => "ALTER TABLE {$table_name} ADD COLUMN ga_session_number INT UNSIGNED NULL AFTER ga_session_id",
        );
        foreach ( $columns as $column => $alter_sql ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column ) );
            if ( ! $exists ) {
                $wpdb->query( $alter_sql );
            }
        }

        $indexes = array(
            'origin_idx'        => "ALTER TABLE {$table_name} ADD INDEX origin_idx (origin)",
            'gclid_idx'         => "ALTER TABLE {$table_name} ADD INDEX gclid_idx (gclid)",
            'utm_campaign_idx'  => "ALTER TABLE {$table_name} ADD INDEX utm_campaign_idx (utm_campaign)",
            'ga_client_id_idx'  => "ALTER TABLE {$table_name} ADD INDEX ga_client_id_idx (ga_client_id)",
            'ga_session_id_idx' => "ALTER TABLE {$table_name} ADD INDEX ga_session_id_idx (ga_session_id)",
        );
        foreach ( $indexes as $key_name => $create_sql ) {
            $idx = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $key_name ) );
            if ( ! $idx ) {
                $wpdb->query( $create_sql );
            }
        }
        
        // Add indexes for better performance
        if (!$link_type_exists || !$link_key_exists) {
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX link_type_idx (link_type)");
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX link_key_idx (link_key)");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Added indexes for link_type and link_key');
            }
        }
        
        // Migrate existing data to use proper link_type
        $wpdb->query("UPDATE {$table_name} SET link_type = 'link' WHERE link_id IS NOT NULL AND link_type = 'utm'");
        
        // Ensure ls_links has redirect_type column
        $redirect_type_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$links_table} LIKE %s",
            'redirect_type'
        ));
        if (!$redirect_type_exists) {
            $wpdb->query("ALTER TABLE {$links_table} ADD COLUMN redirect_type VARCHAR(3) NOT NULL DEFAULT '301' AFTER target_url");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LeadStream: Added redirect_type column to ls_links');
            }
        }

        // Backfill any NULL/empty redirect_type values to '301'
        $wpdb->query("UPDATE {$links_table} SET redirect_type = '301' WHERE redirect_type IS NULL OR redirect_type = ''");

        // Add indexes for faster filters if not present
        $rt_idx = $wpdb->get_var("SHOW INDEX FROM {$links_table} WHERE Key_name = 'redirect_type_idx'");
        if (!$rt_idx) {
            $wpdb->query("ALTER TABLE {$links_table} ADD INDEX redirect_type_idx (redirect_type)");
        }
        $created_idx = $wpdb->get_var("SHOW INDEX FROM {$links_table} WHERE Key_name = 'created_at_idx'");
        if (!$created_idx) {
            $wpdb->query("ALTER TABLE {$links_table} ADD INDEX created_at_idx (created_at)");
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LeadStream: Migration completed successfully');
        }
    }

    /**
     * Flush rewrite rules to ensure our /l/slug pattern works.
     */
    private static function flush_rewrite_rules() {
    // Ensure rewrite rules for both /l/{slug} and /s/{code} are registered
    \LS\Frontend\RedirectHandler::add_rewrite_rule();
    flush_rewrite_rules();
    }
}

// DEBUG: Adding error log to verify activation
