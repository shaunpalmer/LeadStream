<?php
namespace LS\Updates;

defined('ABSPATH') || exit;

/**
 * Updater - Bootstrap class for GitHub Updates
 * 
 * Initializes the GitHubUpdateManager singleton to handle
 * automatic plugin updates from GitHub releases.
 * 
 * @package LeadStream
 */
final class Updater {
    
    /**
     * Initialize the GitHub updater system
     */
    public static function boot(): void {
        if (!defined('LS_FILE')) {
            return;
        }

        // Initialize on plugins_loaded to ensure WordPress is fully loaded
        add_action('plugins_loaded', [__CLASS__, 'initialize'], 5);
    }

    /**
     * Initialize the GitHubUpdateManager singleton
     */
    public static function initialize(): void {
        // Load the GitHubUpdateManager class
        require_once __DIR__ . '/GitHubUpdateManager.php';

        // Initialize the singleton with our GitHub repository
        $repo_url = 'https://github.com/shaunpalmer/LeadStream/';
        $plugin_file = LS_FILE;
        $slug = 'leadstream-analytics-injector';

        GitHubUpdateManager::get_instance($repo_url, $plugin_file, $slug);
    }
}
