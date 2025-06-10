<?php
/**
 * Compatibility functions for deprecated WordPress functions
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Include the debug functions file
if (file_exists(plugin_dir_path(dirname(__FILE__)) . 'debug-functions.php')) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'debug-functions.php';
}

// Include the main compatibility functions file
if (file_exists(plugin_dir_path(dirname(__FILE__)) . 'compatibility-functions.php')) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'compatibility-functions.php';
} else {
    // Log an error if the file can't be found
    error_log('Error: Main compatibility-functions.php file not found');
}

/**
 * Function to suppress WordPress core deprecation warnings
 */
function wcm_suppress_wp_core_deprecation_warnings() {
    // Suppress deprecation warnings for WordPress admin pages
    if (is_admin()) {
        // Get current error reporting level
        $current_level = error_reporting();
        
        // Remove E_DEPRECATED from error reporting
        error_reporting($current_level & ~E_DEPRECATED);
    }
}

// Call the function at plugin initialization
wcm_suppress_wp_core_deprecation_warnings(); 