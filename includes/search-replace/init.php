<?php
/**
 * Initialize Search and Replace Module
 * 
 * Loads all components of the Search and Replace functionality.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Debug: Log that this file is loaded
error_log('Search and Replace init.php is being loaded');

// Include necessary files
$base_path = plugin_dir_path(__FILE__);
require_once $base_path . 'compat-functions.php';
require_once $base_path . 'class-search-replace.php';
require_once $base_path . 'class-csv-validator.php';
require_once $base_path . 'class-logger.php';
require_once $base_path . 'admin-page.php';
require_once $base_path . 'handler.php';

/**
 * Add submenu item for Search and Replace
 * Commented out because we're now registering this in the main plugin file for better compatibility
 */
/*
function wp_content_migrator_add_search_replace_menu_init() {
    // Add debug statement to verify this function is called
    error_log('Adding search and replace menu from init.php');
    
    add_submenu_page(
        'tools.php',                                  // Parent menu slug
        'Search and Replace',                         // Page title
        'Content Search & Replace',                   // Menu title
        'manage_options',                             // Capability
        'wp-content-migrator-search-replace',         // Menu slug
        'wp_content_migrator_search_replace_page'     // Callback function
    );
}
// Adding with a high priority to ensure it runs later
add_action('admin_menu', 'wp_content_migrator_add_search_replace_menu_init', 100);
*/

/**
 * Enqueue admin assets for Search and Replace
 */
function wp_content_migrator_search_replace_admin_scripts($hook) {
    if ('admin_page_custom-search-replace-migrator' !== $hook && 'content-migrator_page_custom-search-replace-migrator' !== $hook) {
        return;
    }
    
    wp_enqueue_style(
        'wp-content-migrator-search-replace-style',
        plugin_dir_url(__FILE__) . '../../admin/css/wp-content-migrator-admin.css',
        array(),
        CONTENT_MIGRATOR_VERSION
    );
    
    wp_enqueue_style(
        'content-migrator-admin-fixes',
        plugin_dir_url(__FILE__) . '../../admin/css/admin-fixes.css',
        array(),
        CONTENT_MIGRATOR_VERSION
    );
    
    wp_enqueue_script(
        'wp-content-migrator-search-replace-script',
        plugin_dir_url(__FILE__) . '../../admin/js/wp-content-migrator-admin.js',
        array('jquery'),
        CONTENT_MIGRATOR_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'wp_content_migrator_search_replace_admin_scripts');

/**
 * Add settings link on plugin page
 */
function wp_content_migrator_search_replace_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=custom-search-replace-migrator') . '">Search & Replace</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Add filter for plugin action links
$plugin_file = plugin_basename(CONTENT_MIGRATOR_PLUGIN_DIR . 'content-migrator.php');
add_filter('plugin_action_links_' . $plugin_file, 'wp_content_migrator_search_replace_settings_link');

/**
 * Update the admin body class for our page
 */
function wp_content_migrator_search_replace_body_class($classes) {
    global $hook_suffix;
    
    // Add class for the Search and Replace page whether accessed directly or as submenu
    if ($hook_suffix === 'admin_page_custom-search-replace-migrator' || 
        $hook_suffix === 'content-migrator_page_custom-search-replace-migrator') {
        return $classes . ' admin-page-custom-search-replace-migrator';
    }
    
    return $classes;
}
add_filter('admin_body_class', 'wp_content_migrator_search_replace_body_class'); 