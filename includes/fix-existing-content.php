<?php
/**
 * WordPress Content Migrator - Fix Existing Content
 * 
 * This script will apply the div wrapping fix to all existing content in the database.
 * It's designed to be run manually from the admin area.
 */

// Make sure WordPress is loaded
if (!defined('WPINC')) {
    die;
}

/**
 * Create admin page for fixing existing content
 */
function wordpress_content_migrator_add_fix_content_page() {
    add_submenu_page(
        'tools.php',
        'Fix Content Formatting',
        'Fix Content Formatting',
        'manage_options',
        'wordpress-content-migrator-fix',
        'wordpress_content_migrator_fix_content_page'
    );
}
add_action('admin_menu', 'wordpress_content_migrator_add_fix_content_page');

/**
 * Render the fix content page
 */
function wordpress_content_migrator_fix_content_page() {
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Handle form submission
    if (isset($_POST['fix_content_submit']) && check_admin_referer('wordpress_content_migrator_fix')) {
        // Get posts to process
        $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : array('page');
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 20;
        $dry_run = isset($_POST['dry_run']) ? true : false;
        
        // Process content
        $results = wordpress_content_migrator_fix_content($post_types, $batch_size, $dry_run);
        
        // Show results
        echo '<div class="updated notice is-dismissible"><p>';
        echo '<strong>Processing complete!</strong><br>';
        echo 'Posts processed: ' . $results['processed'] . '<br>';
        echo 'Posts updated: ' . $results['updated'] . '<br>';
        echo 'Execution time: ' . $results['time'] . ' seconds<br>';
        
        if (!empty($results['sample_changes'])) {
            echo '<h3>Sample Changes:</h3>';
            echo '<div style="max-height: 400px; overflow-y: auto; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';
            foreach ($results['sample_changes'] as $post_id => $changes) {
                echo '<h4>Post ID: ' . $post_id . ' - ' . get_the_title($post_id) . '</h4>';
                echo '<div style="margin-bottom: 20px;">';
                echo '<h5>Before:</h5>';
                echo '<div style="background: #ffeeee; padding: 10px; border: 1px solid #ddd; margin-bottom: 10px;">';
                echo substr($changes['before'], 0, 300) . '...';
                echo '</div>';
                echo '<h5>After:</h5>';
                echo '<div style="background: #eeffee; padding: 10px; border: 1px solid #ddd;">';
                echo substr($changes['after'], 0, 300) . '...';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</p></div>';
    }
    
    // Render the form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="card">
            <h2>Fix Content Formatting</h2>
            <p>This tool will scan your WordPress content and fix unwanted div tags that may have been added during content migration.</p>
            <p><strong>Warning:</strong> It's recommended to create a database backup before proceeding.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wordpress_content_migrator_fix'); ?>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label>Post Types to Process</label></th>
                        <td>
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            foreach ($post_types as $post_type) {
                                if ($post_type->name === 'attachment') continue;
                                ?>
                                <label>
                                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked($post_type->name === 'page' || $post_type->name === 'post'); ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label><br>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="batch_size">Batch Size</label></th>
                        <td>
                            <input type="number" min="1" max="100" name="batch_size" id="batch_size" value="20" class="small-text">
                            <p class="description">Number of posts to process at once. Lower is safer but slower.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dry_run">Dry Run</label></th>
                        <td>
                            <input type="checkbox" name="dry_run" id="dry_run" value="1">
                            <p class="description">If checked, no changes will be made to the database. Use this to preview the results.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="fix_content_submit" id="submit" class="button button-primary" value="Process Content">
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Fix unwanted divs in existing content
 */
function wordpress_content_migrator_fix_content($post_types, $batch_size = 20, $dry_run = false) {
    // Start timer
    $start_time = microtime(true);
    
    // Initialize results
    $results = array(
        'processed' => 0,
        'updated' => 0,
        'time' => 0,
        'sample_changes' => array()
    );
    
    // Get posts
    $args = array(
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $batch_size,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $content = get_post_field('post_content', $post_id);
            
            // Skip if empty content
            if (empty($content)) {
                continue;
            }
            
            $results['processed']++;
            
            // Apply fixes
            $fixed_content = wordpress_content_migrator_apply_div_fixes($content);
            
            // Check if content changed
            if ($fixed_content !== $content) {
                $results['updated']++;
                
                // Save sample of first few changes for review
                if (count($results['sample_changes']) < 5) {
                    $results['sample_changes'][$post_id] = array(
                        'before' => $content,
                        'after' => $fixed_content
                    );
                }
                
                // Update the post if not in dry run mode
                if (!$dry_run) {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_content' => $fixed_content
                    ));
                }
            }
        }
    }
    
    wp_reset_postdata();
    
    // Calculate execution time
    $results['time'] = round(microtime(true) - $start_time, 2);
    
    return $results;
}

/**
 * Apply div fixes to content
 */
function wordpress_content_migrator_apply_div_fixes($content) {
    // Don't process empty content
    if (empty($content)) {
        return $content;
    }
    
    // Fix 1: Remove completely empty divs
    $content = preg_replace('/<div[^>]*>\s*<\/div>/is', '', $content);
    
    // Fix 2: Reduce deeply nested divs
    $pattern = '/<div[^>]*>\s*<div[^>]*>\s*<div[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/is';
    $replacement = '<div>$1</div>';
    
    // Apply multiple times to handle deep nesting
    $previous = '';
    $current = $content;
    $iterations = 0;
    
    while ($previous !== $current && $iterations < 5) {
        $previous = $current;
        $current = preg_replace($pattern, $replacement, $previous);
        $iterations++;
    }
    
    // Fix 3: Convert divs containing only text to paragraphs
    $current = preg_replace('/<div[^>]*>([^<>]+?)<\/div>/is', '<p>$1</p>', $current);
    
    // Fix 4: Fix double paragraph wrapping
    $current = preg_replace('/<p[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/p>/is', '<p>$1</p>', $current);
    
    return $current;
}

// Add a direct link to the fix tool from the plugins page
function wordpress_content_migrator_add_fix_link($links, $file) {
    if (basename($file) === 'wordpress-content-migrator.php') {
        $settings_link = '<a href="' . admin_url('tools.php?page=wordpress-content-migrator-fix') . '">Fix Content</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'wordpress_content_migrator_add_fix_link', 10, 2); 