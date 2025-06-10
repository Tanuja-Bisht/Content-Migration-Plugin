<?php
/*
 * This file documents the changes made to fix the duplicate post creation issue.
 *
 * The following changes were made to class-excel-processor.php:
 *
 * 1. Modified the existing post detection to look for posts by slug for both posts and pages,
 *    not just for pages.
 *
 * 2. Updated the SQL query to use the post_type parameter dynamically.
 *
 * 3. Added special handling for posts to consider any post with a matching slug as a match,
 *    since posts don't have hierarchy like pages do.
 *
 * These changes ensure that existing posts are properly updated rather than creating duplicates.
 */

// Script to fix the duplicate post detection issue

// Read the file
$file = __DIR__ . '/class-excel-processor.php';
$content = file_get_contents($file);

// Create backup
file_put_contents($file . '.bak-' . date('Y-m-d-H-i-s'), $content);

// Find the area where existing post detection happens around line ~878
$pattern = '/\/\/ If not found by path, try to find by slug for pages\s+if \(\!\\$existing_post && \\$type === \'page\'\) \{/';
$replacement = "// If not found by path, try to find by slug for both pages and posts\n        if (!\\$existing_post) {";

// Replace it
$new_content = preg_replace($pattern, $replacement, $content);

// Now also modify the query to work for both types
$pattern2 = '/\\$potential_pages = \\$wpdb->get_results\\(\\$wpdb->prepare\\(\s+"SELECT ID, post_parent FROM \\$wpdb->posts WHERE post_name = %s AND post_type = \'page\' AND post_status IN \\(\'publish\', \'draft\'\\)",\s+\\$slug\s+\\)\\);/';
$replacement2 = "\\$potential_pages = \\$wpdb->get_results(\\$wpdb->prepare(\n                \"SELECT ID, post_parent FROM \\$wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status IN ('publish', 'draft')\",\n                \\$slug,\n                \\$type\n            ));";

// Apply this change
$new_content = preg_replace($pattern2, $replacement2, $new_content);

// Update the code that checks parent matches to handle posts differently
$pattern3 = '/\/\/ Check if any of the existing pages with this slug has the correct parent\s+foreach \\(\\$potential_pages as \\$page\\) \{\s+if \\(\\(int\\)\\$page->post_parent === \\(int\\)\\$expected_parent_id\\) \{/';
$replacement3 = "// Check if any of the existing pages/posts with this slug has the correct parent\n                foreach (\\$potential_pages as \\$page) {\n                    // For pages, check parent ID matches\n                    // For posts, any matching slug is considered a match since posts don't have hierarchy\n                    if ((\\$type === 'post') || ((int)\\$page->post_parent === (int)\\$expected_parent_id)) {";

// Apply this change
$new_content = preg_replace($pattern3, $replacement3, $new_content);

// Write the updated content back to the file
file_put_contents($file, $new_content);

echo "Successfully updated post detection logic to check existing posts by slug, preventing duplicate posts.\n";
echo "The code now treats both post types properly.\n"; 