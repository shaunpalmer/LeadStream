<?php
/*
Plugin Name: LeadStream: Advanced Analytics Injector
Description: Professional JavaScript injection for advanced lead tracking. Custom event handling for Meta Pixel, Google Analytics (GA4), TikTok Pixel, Triple Whale, and any analytics platform. Built for agencies and marketers who need precise conversion tracking.
Version: 2.6.5
Author: shaun palmer
Text Domain: leadstream-analytics
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// === MODULAR ARCHITECTURE ===
// Load modular components
require_once plugin_dir_path(__FILE__) . 'includes/Utils.php';

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/Admin/Assets.php';
    require_once plugin_dir_path(__FILE__) . 'includes/Admin/Settings.php';
    \LS\Admin\Assets::init();
    \LS\Admin\Settings::init(); // ACTIVATED: New modular settings handler
}

require_once plugin_dir_path(__FILE__) . 'includes/Frontend/Injector.php';
\LS\Frontend\Injector::init();

// === CLEAN MODULAR ARCHITECTURE ===
// All legacy functions have been successfully migrated to modular classes





