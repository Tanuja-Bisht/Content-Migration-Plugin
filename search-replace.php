<?php
/**
 * Search and Replace Tool
 * 
 * Redirect users to the WordPress admin integrated version instead of using this standalone file
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to find wp-load.php
$wp_load_path = '';
$search_paths = array(
    dirname(dirname(dirname(__FILE__))) . '/wp-load.php',  // Standard path
    '../../../wp-load.php',                               // Relative path
    dirname(__FILE__) . '/../../../wp-load.php',          // Another relative path
);

// Also try to find wp-load.php by traversing up the directory tree
$path = dirname(__FILE__);
for ($i = 0; $i < 5; $i++) {  // Limit search depth to 5 levels
    $search_paths[] = $path . '/wp-load.php';
    $path = dirname($path);
}

// Try each path
foreach ($search_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

// Try to load WordPress if found
$wp_loaded = false;
if ($wp_load_path) {
    try {
        require_once($wp_load_path);
        $wp_loaded = true;
    } catch (Exception $e) {
        // Failed to load WordPress
    }
}

// If WordPress loaded and user is logged in
if ($wp_loaded && function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('current_user_can') && current_user_can('manage_options')) {
    // Redirect to the admin page
    $admin_url = admin_url('tools.php?page=wcm-search-replace');
    echo "<script>window.location.href = '" . esc_url($admin_url) . "';</script>";
    echo "<p>Redirecting to <a href='" . esc_url($admin_url) . "'>Search and Replace admin page</a>...</p>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Search and Replace Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.5;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            background: #f0f0f1;
            color: #3c434a;
        }
        .card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 2px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        h1 {
            color: #1d2327;
            font-size: 23px;
            font-weight: 400;
            margin: 0 0 20px 0;
        }
        .notice {
            padding: 12px;
            margin: 5px 0 15px;
            background: #fff;
            border-left: 4px solid #dba617;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .notice-error { border-color: #d63638; }
        .button {
            display: inline-block;
            background: #2271b1;
            border: 1px solid #2271b1;
            border-radius: 3px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            font-size: 13px;
            line-height: 2;
            min-height: 30px;
            padding: 0 10px;
            transition: all .1s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>WordPress Content Migrator - Search and Replace</h1>
        
        <div class="notice notice-error">
            <p><strong>Warning:</strong> This standalone Search and Replace tool has been deprecated. Please use the integrated WordPress admin page instead.</p>
        </div>
        
        <p>To use the Search and Replace tool, please access it through the WordPress admin interface:</p>
        
        <ol>
            <li>Log in to your WordPress admin dashboard</li>
            <li>Go to <strong>Tools</strong> &gt; <strong>Content Search &amp; Replace</strong></li>
            <li>Or go to <strong>Tools</strong> &gt; <strong>Content Migrator</strong> and click on the "Launch Search and Replace Tool" button</li>
        </ol>
        
        <?php if ($wp_loaded && function_exists('admin_url')): ?>
            <p>
                <a href="<?php echo esc_url(admin_url('tools.php?page=wcm-search-replace')); ?>" class="button">Go to Search and Replace Admin Page</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html> 