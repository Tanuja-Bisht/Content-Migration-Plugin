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

// Disable PHP deprecation warnings - only in admin
if (is_admin()) {
    $current_error_level = error_reporting();
    // Remove E_DEPRECATED from error reporting
    error_reporting($current_error_level & ~E_DEPRECATED);
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
define('WCM_VERSION', '1.0.0');  // Used by batch processing
define('WCM_PLUGIN_FILE', __FILE__);  // Used by batch processing

// Include common debug functions first (to prevent duplication)
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/debug-functions.php';

// Include required files
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/compatibility-functions.php';
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-excel-processor.php';
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-content-migrator.php';
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-batch-integration.php';
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/improved-meta-description.php';

// Load Search and Replace module
if (file_exists(CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/search-replace/init.php')) {
    require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/search-replace/init.php';
}

// Load display fixes to improve UI
require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/load-fixes.php';

// Initialize the plugin
function run_content_migrator() {
    if (content_migrator_check_requirements()) {
        $plugin = new Content_Migrator();
        $plugin->run();
        
        // Initialize batch processing
        new Batch_Integration();
    }
}
run_content_migrator();

// Register Search and Replace as a submenu of Content Migrator (not hidden)
add_action('admin_menu', function() {
    add_submenu_page(
        'content-migrator',                     // Parent slug - the Content Migrator menu
        'Search and Replace',                   // Page title (appears at top of page)
        'Search and Replace',                   // Menu label (shows in sidebar under Content Migrator)
        'manage_options',                       // Capability required
        'custom-search-replace-migrator',       // Slug used in URL
        function() {                            // Function to render the page
            include_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/search-replace/admin-page.php';
            wp_content_migrator_search_replace_page(); // Call the actual render function
        }
    );
});

// Add CSS to fix admin spacing issues - inline for immediate effect
add_action('admin_head', function() {
    // Only apply these styles on our plugin's pages
    $screen = get_current_screen();
    if (!$screen || 
        $screen->id !== 'admin_page_custom-search-replace-migrator') {
        return;
    }
    
    echo '<style>
        /* Critical CSS fixes for admin menu overlap */
        body.wp-admin {
            box-sizing: border-box;
        }
        #adminmenuwrap, #adminmenuback {
            position: fixed !important;
        }
        .wrap.search-replace-admin-page {
            margin-right: 20px;
            clear: both;
            position: relative;
        }
        #wpcontent {
            padding-left: 20px;
        }
    </style>';
});

// Register admin styles
add_action('admin_enqueue_scripts', function($hook) {
    // Only load on our plugin's pages
    if ($hook !== 'admin_page_custom-search-replace-migrator') {
        return;
    }
    
    // Enqueue proper admin CSS
    wp_enqueue_style(
        'content-migrator-admin-fixes',
        CONTENT_MIGRATOR_PLUGIN_URL . 'admin/css/admin-fixes.css',
        array(),
        CONTENT_MIGRATOR_VERSION
    );
});

// Create admin CSS directory and file if it doesn't exist
add_action('admin_init', function() {
    $css_dir = CONTENT_MIGRATOR_PLUGIN_DIR . 'admin/css';
    $css_file = $css_dir . '/admin-fixes.css';
    
    // Create directories if they don't exist
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    
    // Create CSS file if it doesn't exist
    if (!file_exists($css_file)) {
        $css_content = "
/* Admin fixes for Content Migrator plugin */
#adminmenuwrap {
    position: fixed !important;
    z-index: 9990;
}
#adminmenuback {
    position: fixed !important;
    z-index: 9989;
}
#wpcontent, #wpfooter {
    margin-left: 160px;
}
.auto-fold #wpcontent, .auto-fold #wpfooter {
    margin-left: 36px;
}
@media screen and (max-width: 782px) {
    .auto-fold #wpcontent, .auto-fold #wpfooter {
        margin-left: 0;
    }
}
.php-error {
    margin-left: 0 !important;
    margin-right: 0 !important;
}
";
        file_put_contents($css_file, $css_content);
    }
});

// Add admin body class for our pages
add_filter('admin_body_class', function($classes) {
    $screen = get_current_screen();
    
    // Add class for Search & Replace page
    if ($screen && $screen->id === 'admin_page_custom-search-replace-migrator') {
        $classes .= ' admin-page-custom-search-replace-migrator';
    }
    
    return $classes;
});