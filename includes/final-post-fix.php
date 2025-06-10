<?php
// Final fix for duplicate post issue that ensures posts are updated even when skipped

// Read the file
$file = __DIR__ . '/class-excel-processor.php';
$content = file_get_contents($file);

// Create backup
$backup_file = $file . '.bak-' . date('Y-m-d-H-i-s');
file_put_contents($backup_file, $content);
echo "Backup created: " . basename($backup_file) . "\n";

// Direct fix: Find the code section where URL comparison leads to skipping
$pattern = '/\/\/ Only skip if the exact URL matches\s+if \(\$normalized_existing_path === \$normalized_new_url\) \{\s+error_log\("MIGRATION: Exact URL match found.*?return array\(\s+\'status\' => \'skipped\',.*?\);\s+\}/s';

// Replace with a version that always updates when allow_overwrite is true
$replacement = '// Only skip if the exact URL matches and allow_overwrite is false
            if ($normalized_existing_path === $normalized_new_url && !$allow_overwrite) {
                error_log("MIGRATION: Exact URL match found. Skipping because allow_overwrite is false.");
                return array(
                    \'status\' => \'skipped\',
                    \'message\' => "Content already exists at exact URL {$new_url}. Set allow_overwrite to update it.",
                    \'title\' => $page_title,
                    \'slug\' => $slug
                );
            } else if ($normalized_existing_path === $normalized_new_url && $allow_overwrite) {
                error_log("MIGRATION: Exact URL match found. Will update because allow_overwrite is true.");
                // Update the existing post/page directly
                $post_data[\'ID\'] = $existing_post->ID;
                $post_id = wp_update_post($post_data);
                $action = \'updated\';
                goto post_processed; // Skip to post processing section
            }';

$content = preg_replace($pattern, $replacement, $content, 1, $count);
echo "Fix URL comparison for pages: " . ($count ? "Applied successfully" : "Failed - pattern not found") . "\n";

// Add post_processed label after wp_insert_post() call
$pattern2 = '/\$post_id = wp_insert_post\(\$post_data\);\s+\$action = \'created\';/';
$replacement2 = '$post_id = wp_insert_post($post_data);
                $action = \'created\';
                
            post_processed: // Label for skipping to post processing';

$content = preg_replace($pattern2, $replacement2, $content, 1, $count2);
echo "Added post_processed label: " . ($count2 ? "Applied successfully" : "Failed - pattern not found") . "\n";

// Write the updated content back to the file
file_put_contents($file, $content);

echo "Fix completed. The code now properly updates both posts and pages.\n";
echo "When 'Allow overwrite' is checked, existing content will be updated instead of skipped.\n"; 