<?php
/*
Plugin Name: LeadStream: Advanced Analytics Injector
Description: Professional JavaScript injection for advanced lead tracking. Custom event handling for Meta Pixel, Google Analytics (GA4), TikTok Pixel, Triple Whale, and any analytics platform. Built for agencies and marketers who need precise conversion tracking.
Version: 2.10.0
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
    require_once plugin_dir_path(__FILE__) . 'includes/Admin/LinksDashboard.php';
    require_once plugin_dir_path(__FILE__) . 'includes/Repository/LinksRepository.php';
    require_once plugin_dir_path(__FILE__) . 'includes/Export/Exporters.php';
    require_once plugin_dir_path(__FILE__) . 'includes/AJAX/UTMHandler.php';
    require_once plugin_dir_path(__FILE__) . 'includes/AJAX/PhoneHandler.php';
    \LS\Admin\Assets::init();
    \LS\Admin\Settings::init(); // ACTIVATED: New modular settings handler
    \LS\Admin\LinksDashboard::init(); // ACTIVATED: Pretty Links management
    \LS\AJAX\UTMHandler::init(); // ACTIVATED: UTM Builder AJAX handler
    \LS\AJAX\PhoneHandler::init(); // ACTIVATED: Phone tracking AJAX handler
} else {
    // Frontend-only includes
    require_once plugin_dir_path(__FILE__) . 'includes/AJAX/PhoneHandler.php';
    \LS\AJAX\PhoneHandler::init(); // ACTIVATED: Phone tracking AJAX handler (frontend)
}

// Define this constant so Installer can hook into activation
define( 'LS_FILE', __FILE__ );

// Load Setup and Frontend components
require_once plugin_dir_path(__FILE__) . 'includes/Setup/Installer.php';
require_once plugin_dir_path(__FILE__) . 'includes/Frontend/Injector.php';
require_once plugin_dir_path(__FILE__) . 'includes/Frontend/RedirectHandler.php';

// Initialize components
\LS\Setup\Installer::init();
\LS\Frontend\Injector::init();
\LS\Frontend\RedirectHandler::init();

// === CLEAN MODULAR ARCHITECTURE ===
// All legacy functions have been successfully migrated to modular classes





