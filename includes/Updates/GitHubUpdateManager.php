<?php
/**
 * GitHub Update Manager (Singleton)
 * 
 * Purpose: Handles self-updates for LeadStream via GitHub.
 * Uses the Plugin Update Checker library to enable automatic updates
 * directly from GitHub releases, bypassing the WordPress.org review queue.
 * 
 * @package LeadStream
 */

namespace LS\Updates;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * GitHubUpdateManager - Singleton for handling GitHub-based plugin updates
 */
class GitHubUpdateManager {

    /**
     * @var GitHubUpdateManager The single instance of the class
     */
    private static $instance = null;

    /**
     * @var object The PUC update checker instance
     */
    private $updateChecker;

    /**
     * @var string Plugin slug for storing options
     */
    private $slug;

    /**
     * Get the singleton instance
     * 
     * @param string $repo_url    GitHub repository URL
     * @param string $plugin_file Full path to main plugin file
     * @param string $slug        Plugin slug
     * @return GitHubUpdateManager
     */
    public static function get_instance($repo_url = '', $plugin_file = '', $slug = '') {
        if (self::$instance === null) {
            self::$instance = new self($repo_url, $plugin_file, $slug);
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent multiple instantiations
     * 
     * @param string $repo_url    GitHub repository URL
     * @param string $plugin_file Full path to main plugin file
     * @param string $slug        Plugin slug
     */
    private function __construct($repo_url, $plugin_file, $slug) {
        $this->slug = $slug;

        // Ensure the library exists before trying to load
        $lib_path = plugin_dir_path($plugin_file) . 'plugin-update-checker/plugin-update-checker.php';
        
        if (file_exists($lib_path)) {
            require_once $lib_path;

            // Build the update checker
            $this->updateChecker = PucFactory::buildUpdateChecker(
                $repo_url,
                $plugin_file,
                $slug
            );

            // Tell PUC to check for GitHub "Releases" instead of just tags
            // This is better for production stability
            if (method_exists($this->updateChecker, 'getVcsApi')) {
                $vcs = $this->updateChecker->getVcsApi();
                if (method_exists($vcs, 'enableReleaseAssets')) {
                    $vcs->enableReleaseAssets();
                }
            }
        }
    }

    /**
     * Helper to set a private token if the repo is not public
     * 
     * @param string $token GitHub personal access token
     * @return GitHubUpdateManager Returns $this for method chaining
     */
    public function set_auth_token($token) {
        if ($this->updateChecker && method_exists($this->updateChecker, 'setAuthentication')) {
            $this->updateChecker->setAuthentication($token);
        }
        return $this;
    }

    /**
     * Manually triggers the update check and records the time
     * 
     * @return bool True if check was performed, false otherwise
     */
    public function check_now() {
        if ($this->updateChecker && method_exists($this->updateChecker, 'checkForUpdates')) {
            $this->updateChecker->checkForUpdates();
            
            // Save the current time (Y-m-d H:i:s) to the WP options table
            update_option($this->slug . '_last_check', current_time('mysql'));
            
            return true;
        }
        return false;
    }

    /**
     * Retrieves the last checked time in a human-readable format
     * 
     * @return string Human-readable time string (e.g., "2 hours ago")
     */
    public function get_last_checked_time() {
        $last_check = get_option($this->slug . '_last_check');
        
        if (!$last_check) {
            return 'Never';
        }

        // Convert to a "2 hours ago" format
        return human_time_diff(strtotime($last_check), current_time('timestamp')) . ' ago';
    }

    /**
     * Get the update checker instance
     * 
     * @return object|null The PUC instance or null
     */
    public function get_checker() {
        return $this->updateChecker;
    }

    // Prevent cloning and unsafe waking up of the singleton
    private function __clone() {}
    
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}
