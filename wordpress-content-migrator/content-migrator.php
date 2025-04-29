<?php
/**
 * Plugin Name: Content Migrator
 * Plugin URI: https://anckr.com/
 * Description: A comprehensive WordPress content migration solution that seamlessly transfers pages, posts, and metadata from any website using Excel or CSV files. Features automatic content extraction, HTML cleaning, and metadata handling with no technical expertise required.
 * Version: 1.0.0
 * Author: ANCKR
 * Author URI: https://anckr.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: content-migrator
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check for required PHP extensions
function content_migrator_check_requirements() {
    $missing_extensions = array();
    
    // Check for ZipArchive (needed to process XLSX files)
    if (!class_exists('ZipArchive')) {
        $missing_extensions[] = 'ZIP';
    }
    
    // Check for SimpleXML (needed to process XLSX files)
    if (!function_exists('simplexml_load_file')) {
        $missing_extensions[] = 'SimpleXML';
    }
    
    if (!empty($missing_extensions)) {
        add_action('admin_notices', function() use ($missing_extensions) {
            echo '<div class="error"><p>';
            echo '<strong>Content Migrator:</strong> The following PHP extensions are required but not installed: ' . implode(', ', $missing_extensions) . '.';
            echo ' Please contact your server administrator to install these extensions.';
            echo ' See <a href="' . CONTENT_MIGRATOR_PLUGIN_URL . 'INSTALL.md">installation instructions</a> for more details.';
            echo '</p></div>';
        });
        return false;
    }
    
    return true;
}

// Define plugin constants
define('CONTENT_MIGRATOR_VERSION', '1.0.0');
define('CONTENT_MIGRATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CONTENT_MIGRATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-excel-processor.php';
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-content-migrator.php';

// Initialize the plugin
function run_content_migrator() {
    if (content_migrator_check_requirements()) {
        $plugin = new Content_Migrator();
        $plugin->run();
    }
}
run_content_migrator();
