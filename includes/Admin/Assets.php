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
}
