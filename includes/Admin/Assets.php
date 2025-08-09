<?php
/**
 * LeadStream Admin Assets Handler
 * Handles CodeMirror integration, admin styles, and asset enqueuing
 */

namespace LS\Admin;

defined('ABSPATH') || exit;

class Assets {
    
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_code_editor']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_styles']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_utm_builder']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_pretty_links']);
        
        // Frontend phone tracking
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_phone_tracking']);
    }
    
    /**
     * Smart CodeMirror with switch - CDN default, WordPress fallback
     */
    public static function enqueue_code_editor($hook_suffix) {
        // Only load on our plugin's settings page
        if ('toplevel_page_leadstream-analytics-injector' !== $hook_suffix) {
            return;
        }

        // CDN mode: reliable CodeMirror that actually works (WordPress fails silently)
        $codemirror_source = get_option('leadstream_codemirror_source', 'cdn');
        
        switch ($codemirror_source) {
            case 'auto':
                // Try WordPress native first (more WordPress-y)
                $editor_settings = wp_enqueue_code_editor([
                    'type' => 'text/javascript',
                    'codemirror' => [
                        'mode' => 'javascript',
                        'lineNumbers' => true,
                        'indentUnit' => 2,
                        'lineWrapping' => true,
                        'matchBrackets' => true,
                        'autoCloseBrackets' => true,
                    ]
                ]);
                
                if (false !== $editor_settings) {
                    wp_enqueue_script('wp-code-editor');
                    wp_enqueue_style('wp-codemirror');
                    $init_script = sprintf(
                        'jQuery(function($){ 
                            console.log("LeadStream: Using WordPress CodeMirror (auto mode)");
                            if ($("#custom_header_js").length) {
                                wp.codeEditor.initialize($("#custom_header_js"), %s);
                            }
                            if ($("#custom_footer_js").length) {
                                wp.codeEditor.initialize($("#custom_footer_js"), %s);
                            }
                        });',
                        wp_json_encode($editor_settings),
                        wp_json_encode($editor_settings)
                    );
                    wp_add_inline_script('wp-code-editor', $init_script);
                    break; // Success with WordPress
                }
                // WordPress failed, fallback to CDN
                
            case 'cdn':
            default:
                // CloudFlare CDN - reliable and fast (bulletproof default)
                wp_enqueue_style('leadstream-codemirror-css',
                    'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css',
                    [], '5.65.5'
                );
                wp_enqueue_script('leadstream-codemirror-js',
                    'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js',
                    [], '5.65.5', true
                );
                wp_enqueue_script('leadstream-codemirror-mode-js',
                    'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js',
                    ['leadstream-codemirror-js'], '5.65.5', true
                );

                // Initialize CodeMirror on both textareas
                $inline = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
  console.log("LeadStream: Using CDN CodeMirror (reliable)");
  
  if (typeof CodeMirror !== 'undefined') {
    // Header editor
    var headerTextarea = document.getElementById('custom_header_js');
    if (headerTextarea) {
      console.log("LeadStream: Creating header editor");
      CodeMirror.fromTextArea(headerTextarea, {
        mode: 'javascript',
        lineNumbers: true,
        indentUnit: 2,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        theme: 'default'
      });
    }
    
    // Footer editor
    var footerTextarea = document.getElementById('custom_footer_js');
    if (footerTextarea) {
      console.log("LeadStream: Creating footer editor");
      CodeMirror.fromTextArea(footerTextarea, {
        mode: 'javascript',
        lineNumbers: true,
        indentUnit: 2,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        theme: 'default'
      });
    }
    
    console.log("LeadStream: CodeMirror setup complete");
  } else {
    console.error("LeadStream: CodeMirror failed to load");
  }
});
JS;
                wp_add_inline_script('leadstream-codemirror-mode-js', $inline);
                break;
                
            case 'wordpress':
                // Force WordPress only (no CDN fallback)
                $editor_settings = wp_enqueue_code_editor([
                    'type' => 'text/javascript',
                    'codemirror' => [
                        'mode' => 'javascript',
                        'lineNumbers' => true,
                        'indentUnit' => 2,
                        'lineWrapping' => true,
                        'matchBrackets' => true,
                        'autoCloseBrackets' => true,
                    ]
                ]);
                
                if (false !== $editor_settings) {
                    wp_enqueue_script('wp-code-editor');
                    wp_enqueue_style('wp-codemirror');
                    $init_script = sprintf(
                        'jQuery(function($){ 
                            console.log("LeadStream: Using WordPress CodeMirror (forced)");
                            if ($("#custom_header_js").length) {
                                wp.codeEditor.initialize($("#custom_header_js"), %s);
                            }
                            if ($("#custom_footer_js").length) {
                                wp.codeEditor.initialize($("#custom_footer_js"), %s);
                            }
                        });',
                        wp_json_encode($editor_settings),
                        wp_json_encode($editor_settings)
                    );
                    wp_add_inline_script('wp-code-editor', $init_script);
                }
                break;
        }
    }
    
    /**
     * Enqueue admin styles for proper WordPress form layout
     */
    public static function enqueue_admin_styles($hook) {
        if ('toplevel_page_leadstream-analytics-injector' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'leadstream-admin-css',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/leadstream-admin.css',
            ['wp-admin'],
            '2.5.5'
        );
        
        // Add CodeMirror styling that works with both WP and CDN versions
        $codemirror_css = '
        .CodeMirror {
            border: 1px solid #ddd !important;
            font-family: Consolas, Monaco, "Courier New", monospace !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            max-width: 800px !important;
            width: 100% !important;
        }
        .CodeMirror-scroll {
            min-height: 400px !important;
        }
        .CodeMirror-linenumber {
            color: #999 !important;
            padding: 0 8px 0 5px !important;
        }
        .CodeMirror-gutters {
            border-right: 1px solid #ddd !important;
            background-color: #f7f7f7 !important;
        }
        ';
        wp_add_inline_style('leadstream-admin-css', $codemirror_css);
    }
    
    /**
     * Enqueue admin JavaScript for settings functionality
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on our plugin's settings page
        if ('toplevel_page_leadstream-analytics-injector' !== $hook) {
            return;
        }
        
        // Enqueue our admin settings JavaScript
        wp_enqueue_script(
            'leadstream-admin-settings',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/leadstream-admin-settings.js',
            ['jquery'], // Dependencies: jQuery for FAQ functionality
            '2.6.4-' . time(), // Force cache refresh with timestamp
            true // Load in footer
        );
    }

    /**
     * Enqueue UTM builder scripts on the UTM tab
     */
    public static function enqueue_utm_builder($hook) {
        // Only on the LeadStream settings page
        if ($hook !== 'toplevel_page_leadstream-analytics-injector') {
            return;
        }

        // Only if we're on the UTM tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'utm') {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        
        wp_enqueue_script(
            'leadstream-utm-builder',
            $plugin_url . 'assets/js/utm-builder.js',
            ['jquery'],
            filemtime($plugin_dir . 'assets/js/utm-builder.js'),
            true
        );

        // Add localized data for AJAX
        wp_localize_script('leadstream-utm-builder', 'leadstream_utm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leadstream_utm_nonce')
        ]);
    }

    /**
     * Enqueue Pretty Links dashboard assets
     */
    public static function enqueue_pretty_links($hook) {
        // Only on the LeadStream settings page
        if ($hook !== 'toplevel_page_leadstream-analytics-injector') {
            return;
        }

        // Only if we're on the links tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'links') {
            return;
        }

        // Enqueue WordPress list table styles (ensures proper styling)
        wp_enqueue_style('list-tables');
        
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        
        // Create the JS file if it doesn't exist
        $js_file = $plugin_dir . 'assets/js/pretty-links.js';
        if (!file_exists($js_file)) {
            self::create_pretty_links_js($js_file);
        }
        
        wp_enqueue_script(
            'leadstream-pretty-links',
            $plugin_url . 'assets/js/pretty-links.js',
            ['jquery'],
            filemtime($js_file),
            true
        );

        // Add localized data for AJAX
        wp_localize_script('leadstream-pretty-links', 'leadstream_links_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leadstream_links_nonce')
        ]);
    }

    /**
     * Create Pretty Links JavaScript file
     */
    private static function create_pretty_links_js($file_path) {
        $js_content = <<<'JS'
jQuery(document).ready(function($) {
    'use strict';

    // Copy link functionality
    $('.copy-link-btn').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                alert('Link copied to clipboard!');
            }).catch(function() {
                // Fallback
                copyToClipboardFallback(url);
            });
        } else {
            copyToClipboardFallback(url);
        }
    });

    // Fallback copy method
    function copyToClipboardFallback(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        } catch (err) {
            console.error('Could not copy text: ', err);
            alert('Could not copy link. Please copy manually: ' + text);
        }
        
        document.body.removeChild(textArea);
    }

    // Test link functionality
    $('.test-link-btn').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        window.open(url, '_blank');
    });

    // Delete confirmation
    $('.delete-link-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this link? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});
JS;

        // Ensure the directory exists
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        file_put_contents($file_path, $js_content);
    }
    
    /**
     * Enqueue phone tracking script on frontend
     */
    public static function enqueue_phone_tracking() {
        // Only enqueue if phone tracking is enabled and numbers are configured
        $phone_enabled = get_option('leadstream_phone_enabled', 1);
        $phone_numbers = get_option('leadstream_phone_numbers', array());
        
        if (!$phone_enabled || empty($phone_numbers)) {
            return;
        }
        
        wp_enqueue_script(
            'leadstream-phone-tracking',
            plugin_dir_url(dirname(__DIR__)) . 'assets/js/phone-tracking.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Get phone selectors
        $phone_selectors = get_option('leadstream_phone_selectors', '');
        
        // Get GA4 ID if available
        $ga_id = get_option('leadstream_gtm_id', ''); // You might want to add a dedicated GA4 ID option
        
        wp_localize_script('leadstream-phone-tracking', 'LeadStreamPhone', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'numbers' => $phone_numbers,
            'selectors' => $phone_selectors,
            'nonce' => wp_create_nonce('leadstream_phone_click'),
            'ga_id' => $ga_id,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'show_feedback' => true
        ]);
    }
}
