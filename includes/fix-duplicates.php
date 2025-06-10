<?php
/**
 * WordPress Content Migrator - Fix Duplicate Posts
 * 
 * This file enhances the duplicate detection to prevent duplicate posts
 * when re-uploading content sheets.
 */

// Make sure WordPress is loaded
if (!defined('WPINC')) {
    die;
}

/**
 * Apply the duplicate detection fix
 * This modifies the Excel_Processor class functions related to duplicate detection
 */
function fix_duplicate_detection() {
    // Override batch processing to ensure URL tracking
    add_filter('wp_content_migrator_batch_processing', '__return_true');
    
    // Add our duplicate detection hook
    add_filter('wp_content_migrator_pre_process_row', 'enhance_duplicate_detection', 10, 2);
    
    error_log("MIGRATION: Enhanced duplicate detection activated");
    return true;
}

/**
 * Enhanced duplicate detection that focuses on URLs rather than titles
 */
function enhance_duplicate_detection($row_data, $allow_overwrite) {
    // Skip if overwrite is allowed
    if ($allow_overwrite) {
        return $row_data;
    }
    
    // Skip if no URL
    $new_url = isset($row_data['new_url']) ? trim($row_data['new_url']) : '';
    if (empty($new_url)) {
        return $row_data;
    }
    
    // Normalize URL
    $new_url = '/' . ltrim(trim($new_url), '/');
    $new_url = rtrim($new_url, '/');
    
    // Get post type
    $type = isset($row_data['type']) ? strtolower(trim($row_data['type'])) : 'page';
    if ($type !== 'post' && $type !== 'page') {
        $type = 'page';
    }
    
    // Build the full path
    $full_path = ltrim($new_url, '/');
    
    // Look for existing post at exact URL path
    $post = get_page_by_path($full_path, OBJECT, $type);
    if ($post) {
        // Mark this row as a duplicate
        $row_data['_is_duplicate'] = true;
        $row_data['_duplicate_id'] = $post->ID;
        
        error_log("MIGRATION: Found duplicate at exact path: {$full_path} (ID: {$post->ID})");
        return $row_data;
    }
    
    // If not found by path, try more aggressive URL matching for posts
    if ($type === 'post') {
        // Extract slug
        $slug = basename($full_path);
        
        // Try to find posts with same slug
        global $wpdb;
        $potential_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = 'post' AND post_status IN ('publish', 'draft')",
            $slug
        ));
        
        if (!empty($potential_posts)) {
            // Check if any post matches the URL structure (e.g., category/slug)
            foreach ($potential_posts as $post) {
                // Get actual permalink for comparison
                $permalink = get_permalink($post->ID);
                if ($permalink) {
                    // Remove domain for comparison
                    $permalink = wp_parse_url($permalink, PHP_URL_PATH);
                    
                    // Compare normalized paths
                    $permalink = '/' . ltrim(trim($permalink), '/');
                    $permalink = rtrim($permalink, '/');
                    
                    if ($permalink === $new_url) {
                        // Mark this row as a duplicate
                        $row_data['_is_duplicate'] = true;
                        $row_data['_duplicate_id'] = $post->ID;
                        
                        error_log("MIGRATION: Found duplicate post at permalink: {$permalink} (ID: {$post->ID})");
                        return $row_data;
                    }
                }
            }
        }
    }
    
    // Check database for any post with this exact URL
    // This handles cases where permalink structures may be different
    // but post slugs or hierarchies match
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT p.ID FROM $wpdb->posts p
         LEFT JOIN $wpdb->postmeta m ON p.ID = m.post_id AND m.meta_key = '_wp_content_migrator_url'
         WHERE (m.meta_value = %s OR p.guid = %s) AND p.post_type = %s AND p.post_status IN ('publish', 'draft')",
        $new_url,
        home_url($new_url),
        $type
    );
    
    $existing_id = $wpdb->get_var($sql);
    if ($existing_id) {
        // Mark this row as a duplicate
        $row_data['_is_duplicate'] = true;
        $row_data['_duplicate_id'] = $existing_id;
        
        error_log("MIGRATION: Found duplicate via meta URL: {$new_url} (ID: {$existing_id})");
        return $row_data;
    }
    
    return $row_data;
}

/**
 * Enhanced hook for process_row that adds URL tracking
 */
function enhanced_process_row($row_data, $allow_overwrite, $skip_rewrite_flush = false) {
    // Get the Excel_Processor instance
    $processor = new Excel_Processor();
    
    // If marked as duplicate, return skipped status
    if (isset($row_data['_is_duplicate']) && $row_data['_is_duplicate']) {
        $new_url = isset($row_data['new_url']) ? trim($row_data['new_url']) : '';
        $page_title = isset($row_data['title']) ? trim($row_data['title']) : '';
        $slug = basename(trim($new_url, '/'));
        
        error_log("MIGRATION: Skipping duplicate for URL: {$new_url}");
        
        return array(
            'status' => 'skipped',
            'message' => "Content already exists at URL {$new_url}. Skipped to prevent duplicate.",
            'title' => $page_title ?: ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'url' => $new_url,
            'post_id' => isset($row_data['_duplicate_id']) ? $row_data['_duplicate_id'] : 0
        );
    }
    
    // Process the row normally
    $result = $processor->process_row($row_data, $allow_overwrite, $skip_rewrite_flush);
    
    // If successful, store the URL in our tracking system
    if (isset($result['status']) && $result['status'] === 'success' && isset($result['post_id'])) {
        $new_url = isset($row_data['new_url']) ? trim($row_data['new_url']) : '';
        if (!empty($new_url)) {
            // Normalize URL
            $new_url = '/' . ltrim(trim($new_url), '/');
            $new_url = rtrim($new_url, '/');
            
            // Store URL in post meta
            update_post_meta($result['post_id'], '_wp_content_migrator_url', $new_url);
            
            error_log("MIGRATION: Stored URL tracking for {$new_url} (ID: {$result['post_id']})");
        }
    }
    
    return $result;
}

/**
 * Apply the fix by replacing class methods
 */
add_action('plugins_loaded', function() {
    // Add our enhanced process_row as a filter
    add_filter('wp_content_migrator_process_row', 'enhanced_process_row', 10, 3);
    
    // Enable URL tracking across sessions
    fix_duplicate_detection();
}, 20); // Run after the plugin is loaded

/**
 * Override the Excel_Processor class to use our enhanced methods
 */
if (class_exists('Excel_Processor')) {
    class_alias('Excel_Processor', 'Original_Excel_Processor_For_Duplicates');
    
    class Excel_Processor extends Original_Excel_Processor_For_Duplicates {
        /**
         * Override batch processing to ensure URL tracking
         */
        public function is_batch_processing_enabled() {
            return apply_filters('wp_content_migrator_batch_processing', true);
        }
        
        /**
         * Override process_row to use our enhanced version
         */
        public function process_row($row_data, $allow_overwrite, $skip_rewrite_flush = false) {
            // Apply pre-processing filter
            $row_data = apply_filters('wp_content_migrator_pre_process_row', $row_data, $allow_overwrite);
            
            // Use our enhanced version
            return apply_filters('wp_content_migrator_process_row', $row_data, $allow_overwrite, $skip_rewrite_flush);
        }
        
        /**
         * Add more safeguards to prevent duplicates
         */
        public function process_csv_file($file_path, $allow_overwrite) {
            // Get previously processed URLs
            $processed_urls = $this->get_processed_urls();
            
            // Process as normal
            $results = parent::process_csv_file($file_path, $allow_overwrite);
            
            // After processing, iterate through successfully processed posts and update URL tracking
            foreach ($results['details'] as $detail) {
                if (isset($detail['status']) && $detail['status'] === 'success' && isset($detail['post_id'])) {
                    $new_url = isset($detail['url']) ? $detail['url'] : '';
                    if (!empty($new_url)) {
                        // Store URL in postmeta
                        update_post_meta($detail['post_id'], '_wp_content_migrator_url', $new_url);
                        
                        // Add to processed URLs
                        $processed_urls[$new_url] = $detail['post_id'];
                    }
                }
            }
            
            // Save processed URLs for future imports
            $this->save_processed_urls($processed_urls);
            
            return $results;
        }
    }
} 