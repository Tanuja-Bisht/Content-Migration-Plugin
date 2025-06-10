<?php
/**
 * Debug Script for WordPress Content Migrator
 */

// Include WordPress core
require_once('../../../wp-load.php');

// Check if user is logged in and has necessary permissions
if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
    die('WordPress functions not available.');
}

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('You do not have sufficient permissions to access this page.');
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if wp_content_migrator_search_replace_page function exists
echo "<h2>Checking function availability:</h2>";
$function_exists = function_exists('wp_content_migrator_search_replace_page');
echo "wp_content_migrator_search_replace_page exists: " . ($function_exists ? 'Yes' : 'No') . "<br>";

// Display the location of the admin-page.php file
$admin_page_file = dirname(__FILE__) . '/includes/search-replace/admin-page.php';
echo "Admin page file path: " . $admin_page_file . "<br>";
echo "Admin page file exists: " . (file_exists($admin_page_file) ? 'Yes' : 'No') . "<br>";

// Check current hooks
echo "<h2>Current Admin Menu Hooks:</h2>";
global $wp_filter;
if (isset($wp_filter['admin_menu'])) {
    foreach ($wp_filter['admin_menu']->callbacks as $priority => $callbacks) {
        echo "Priority $priority:<br>";
        foreach ($callbacks as $key => $callback) {
            if (is_array($callback['function'])) {
                echo "- " . (is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0]) . "->" . $callback['function'][1] . "<br>";
            } else {
                echo "- " . (is_string($callback['function']) ? $callback['function'] : 'Closure') . "<br>";
            }
        }
    }
} else {
    echo "No admin_menu hooks registered.";
}

// Try to include the admin page file directly
echo "<h2>Attempting to include admin-page.php:</h2>";
try {
    require_once($admin_page_file);
    echo "File included successfully.<br>";
    echo "wp_content_migrator_search_replace_page exists after include: " . (function_exists('wp_content_migrator_search_replace_page') ? 'Yes' : 'No') . "<br>";
} catch (Exception $e) {
    echo "Error including file: " . $e->getMessage() . "<br>";
}

// Print phpinfo for debugging
echo "<h2>PHP Information:</h2>";
phpinfo(INFO_MODULES);
?> 