<?php
/**
 * WordPress Content Migrator - Cleanup Duplicate Posts
 * 
 * This utility helps find and clean up existing duplicate posts.
 */

// Make sure WordPress is loaded
if (!defined('WPINC')) {
    die;
}

/**
 * Add admin page for cleaning up duplicates
 */
function wordpress_content_migrator_add_cleanup_page() {
    add_submenu_page(
        'tools.php',
        'Clean Up Duplicates',
        'Clean Up Duplicates',
        'manage_options',
        'wordpress-content-migrator-cleanup',
        'wordpress_content_migrator_cleanup_page'
    );
}
add_action('admin_menu', 'wordpress_content_migrator_add_cleanup_page');

/**
 * Render the cleanup page
 */
function wordpress_content_migrator_cleanup_page() {
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Handle form submission
    $results = array();
    if (isset($_POST['find_duplicates']) && check_admin_referer('wordpress_content_migrator_cleanup')) {
        $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : array('page');
        $results = wordpress_content_migrator_find_duplicates($post_types);
    }
    
    // Handle duplicate deletion
    if (isset($_POST['delete_duplicates']) && check_admin_referer('wordpress_content_migrator_cleanup_delete')) {
        $duplicate_ids = isset($_POST['duplicate_ids']) ? $_POST['duplicate_ids'] : array();
        $results = wordpress_content_migrator_delete_duplicates($duplicate_ids);
    }
    
    // Render the page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if (!empty($results)): ?>
            <div class="notice <?php echo $results['status'] === 'success' ? 'notice-success' : 'notice-warning'; ?> is-dismissible">
                <p><?php echo esc_html($results['message']); ?></p>
                
                <?php if (!empty($results['duplicates'])): ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('wordpress_content_migrator_cleanup_delete'); ?>
                        
                        <h3>Found <?php echo count($results['duplicates']); ?> potential duplicate posts:</h3>
                        <p>Select duplicates to delete (original posts will be kept):</p>
                        
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Original Post</th>
                                    <th>Duplicate Post</th>
                                    <th>Similarity</th>
                                    <th>Delete?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['duplicates'] as $duplicate): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html(get_the_title($duplicate['original_id'])); ?></strong><br>
                                            URL: <?php echo esc_html(get_permalink($duplicate['original_id'])); ?><br>
                                            ID: <?php echo esc_html($duplicate['original_id']); ?><br>
                                            Date: <?php echo esc_html(get_the_date('Y-m-d H:i:s', $duplicate['original_id'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html(get_the_title($duplicate['duplicate_id'])); ?></strong><br>
                                            URL: <?php echo esc_html(get_permalink($duplicate['duplicate_id'])); ?><br>
                                            ID: <?php echo esc_html($duplicate['duplicate_id']); ?><br>
                                            Date: <?php echo esc_html(get_the_date('Y-m-d H:i:s', $duplicate['duplicate_id'])); ?>
                                        </td>
                                        <td><?php echo esc_html($duplicate['similarity'] . '%'); ?></td>
                                        <td>
                                            <input type="checkbox" name="duplicate_ids[]" value="<?php echo esc_attr($duplicate['duplicate_id']); ?>" 
                                                <?php if ($duplicate['similarity'] >= 90) echo 'checked'; ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="delete_duplicates" id="delete_duplicates" class="button button-primary" 
                                value="Delete Selected Duplicates" onclick="return confirm('Are you sure you want to delete the selected duplicates? This cannot be undone.');">
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Find Duplicate Posts</h2>
            <p>This tool will scan your WordPress content and find potential duplicate posts based on URL structure and content similarity.</p>
            <p><strong>Warning:</strong> Always backup your database before performing any cleanup operations.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wordpress_content_migrator_cleanup'); ?>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label>Post Types to Check</label></th>
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
                </table>
                
                <p class="submit">
                    <input type="submit" name="find_duplicates" id="find_duplicates" class="button button-primary" value="Find Duplicates">
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Find duplicate posts
 */
function wordpress_content_migrator_find_duplicates($post_types) {
    global $wpdb;
    
    // Start timer
    $start_time = microtime(true);
    
    // Initialize results
    $results = array(
        'status' => 'success',
        'message' => '',
        'duplicates' => array()
    );
    
    // Get all published posts
    $args = array(
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $posts = get_posts($args);
    $total_posts = count($posts);
    
    // Group posts by similar slugs
    $post_groups = array();
    foreach ($posts as $post) {
        $slug = sanitize_title($post->post_name);
        if (!isset($post_groups[$slug])) {
            $post_groups[$slug] = array();
        }
        $post_groups[$slug][] = $post->ID;
    }
    
    // Filter groups to only those with multiple posts
    $potential_duplicates = array_filter($post_groups, function($group) {
        return count($group) > 1;
    });
    
    // Check each group for content similarity
    $duplicates = array();
    foreach ($potential_duplicates as $slug => $post_ids) {
        // Sort by post date (oldest first)
        usort($post_ids, function($a, $b) {
            $date_a = get_post_field('post_date', $a);
            $date_b = get_post_field('post_date', $b);
            return strtotime($date_a) - strtotime($date_b);
        });
        
        // Consider the oldest post as the original
        $original_id = array_shift($post_ids);
        $original_content = get_post_field('post_content', $original_id);
        $original_title = get_post_field('post_title', $original_id);
        
        // Compare each potential duplicate with the original
        foreach ($post_ids as $duplicate_id) {
            $duplicate_content = get_post_field('post_content', $duplicate_id);
            $duplicate_title = get_post_field('post_title', $duplicate_id);
            
            // Calculate similarity
            $title_similarity = similar_text($original_title, $duplicate_title, $title_percent);
            $content_similarity = similar_text($original_content, $duplicate_content, $content_percent);
            
            // Use a weighted average (title more important)
            $similarity = (($title_percent * 2) + $content_percent) / 3;
            
            // If similarity is high enough, consider it a duplicate
            if ($similarity > 60) {
                $duplicates[] = array(
                    'original_id' => $original_id,
                    'duplicate_id' => $duplicate_id,
                    'similarity' => round($similarity, 1)
                );
            }
        }
    }
    
    // Sort duplicates by similarity (highest first)
    usort($duplicates, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
    
    // Calculate execution time
    $time = round(microtime(true) - $start_time, 2);
    
    // Prepare results
    $results['duplicates'] = $duplicates;
    $results['message'] = sprintf(
        'Scanned %d posts in %s seconds. Found %d potential duplicate posts.',
        $total_posts, $time, count($duplicates)
    );
    
    if (empty($duplicates)) {
        $results['message'] = sprintf(
            'Scanned %d posts in %s seconds. No duplicates found.',
            $total_posts, $time
        );
    }
    
    return $results;
}

/**
 * Delete duplicate posts
 */
function wordpress_content_migrator_delete_duplicates($duplicate_ids) {
    // Initialize results
    $results = array(
        'status' => 'success',
        'message' => ''
    );
    
    if (empty($duplicate_ids)) {
        $results['status'] = 'warning';
        $results['message'] = 'No duplicates selected for deletion.';
        return $results;
    }
    
    $deleted_count = 0;
    $error_count = 0;
    
    foreach ($duplicate_ids as $post_id) {
        $post_id = intval($post_id);
        $title = get_the_title($post_id);
        
        // Delete the post
        $deleted = wp_delete_post($post_id, true); // true = force delete, bypass trash
        
        if ($deleted) {
            $deleted_count++;
        } else {
            $error_count++;
        }
    }
    
    // Prepare result message
    if ($deleted_count > 0) {
        $results['message'] = sprintf(
            'Successfully deleted %d duplicate posts.',
            $deleted_count
        );
    }
    
    if ($error_count > 0) {
        $results['status'] = 'warning';
        $results['message'] .= sprintf(
            ' Failed to delete %d posts.',
            $error_count
        );
    }
    
    return $results;
}

/**
 * Add cleanup link to plugin action links
 */
function wordpress_content_migrator_add_cleanup_link($links, $file) {
    if (basename($file) === 'wordpress-content-migrator.php') {
        $cleanup_link = '<a href="' . admin_url('tools.php?page=wordpress-content-migrator-cleanup') . '">Clean Duplicates</a>';
        array_unshift($links, $cleanup_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'wordpress_content_migrator_add_cleanup_link', 10, 2); 