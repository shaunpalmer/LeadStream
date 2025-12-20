<?php
/*
Plugin Name: LeadStream Pro: Advanced Analytics Injector
Description: Professional JavaScript injection for advanced lead tracking. Custom event handling for Meta Pixel, Google Analytics (GA4), TikTok Pixel, Triple Whale, and any analytics platform. Built for agencies and marketers who need precise conversion tracking.
Version: 2.12.2
Author: shaun palmer
Text Domain: leadstream-analytics
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define core constants
define('LS_FILE', __FILE__);
define('LS_VERSION', '2.12.2');
define('LS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load and initialize the plugin using the Bootstrap system
require_once LS_PLUGIN_DIR . 'includes/Bootstrap.php';
\LeadStream\Bootstrap::init();



