<?php
/**
 * Debug script for Search and Replace functionality
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    define('WPINC', 'direct_access');
    
    // Try to load WordPress
    $wp_load_paths = array(
        dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php'
    );
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Create a log directory if it doesn't exist
$log_dir = dirname(dirname(dirname(__FILE__))) . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log debug information
$log_file = $log_dir . '/search-replace-debug.log';
$log_message = date('Y-m-d H:i:s') . " - Debug script executed\n";
$log_message .= "WPINC defined: " . (defined('WPINC') ? 'Yes' : 'No') . "\n";
$log_message .= "Function wp_content_migrator_process_search_replace exists: " . (function_exists('wp_content_migrator_process_search_replace') ? 'Yes' : 'No') . "\n";
$log_message .= "Function wp_content_migrator_handle_search_replace exists: " . (function_exists('wp_content_migrator_handle_search_replace') ? 'Yes' : 'No') . "\n";
$log_message .= "Class WP_Content_Migrator_Search_Replace exists: " . (class_exists('WP_Content_Migrator_Search_Replace') ? 'Yes' : 'No') . "\n";

// Include handler file
$handler_file = dirname(__FILE__) . '/handler.php';
$log_message .= "Handler file exists: " . (file_exists($handler_file) ? 'Yes' : 'No') . "\n";

if (file_exists($handler_file)) {
    // Include the handler file
    include_once $handler_file;
    
    // Check if functions now exist
    $log_message .= "After inclusion - Function wp_content_migrator_process_search_replace exists: " . (function_exists('wp_content_migrator_process_search_replace') ? 'Yes' : 'No') . "\n";
    $log_message .= "After inclusion - Function wp_content_migrator_handle_search_replace exists: " . (function_exists('wp_content_migrator_handle_search_replace') ? 'Yes' : 'No') . "\n";
}

// Write to log file
file_put_contents($log_file, $log_message, FILE_APPEND);

// If accessed directly, show debug info
if (defined('WPINC') && WPINC === 'direct_access') {
    header('Content-Type: text/plain');
    echo $log_message;
    echo "\nLog file written to: $log_file";
} 