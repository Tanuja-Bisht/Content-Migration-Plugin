<?php
/**
 * WordPress Content Migrator - Direct DIV Wrapping Fix
 * 
 * This file directly modifies the Excel_Processor class methods to fix the div wrapping issue.
 * It uses output buffering to capture and modify the class file contents.
 */

// Make sure WordPress is loaded
if (!defined('WPINC')) {
    die;
}

/**
 * Fix the unwanted div tags in content
 * This is a simple and direct approach that modifies the file in place
 */
function fix_div_wrapping_in_excel_processor() {
    // Path to the Excel_Processor class file
    $file_path = plugin_dir_path(__FILE__) . 'class-excel-processor.php';
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log('Excel_Processor class file not found at ' . $file_path);
        return false;
    }
    
    // Get current file contents
    $content = file_get_contents($file_path);
    
    // Create a backup of the original file if it doesn't exist
    $backup_path = $file_path . '.bak';
    if (!file_exists($backup_path)) {
        file_put_contents($backup_path, $content);
        error_log('Backup of class-excel-processor.php created at ' . $backup_path);
    }
    
    // Fix the reduce_div_nesting function
    $original_reduce_div = '/private function reduce_div_nesting\(\$html\) \{.*?return \$current;\s*\}/s';
    $new_reduce_div = 'private function reduce_div_nesting($html) {
        // First, eliminate completely empty divs
        $html = preg_replace(\'/<div[^>]*>\s*<\/div>/is\', \'\', $html);
        
        // Then, eliminate deeply nested divs with no semantic content
        $pattern = \'/<div[^>]*>\s*<div[^>]*>\s*<div[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/is\';
        $replacement = \'<div>$1</div>\';
        
        // Apply multiple times to handle deep nesting
        $previous = \'\';
        $current = $html;
        
        // Keep applying until no more changes or reached iteration limit
        $iterations = 0;
        while ($previous !== $current && $iterations < 5) {
            $previous = $current;
            $current = preg_replace($pattern, $replacement, $previous);
            $iterations++;
        }
        
        // Now only convert divs to paragraphs if they only contain text (no HTML tags)
        $current = preg_replace(\'/<div[^>]*>([^<>]+?)<\/div>/is\', \'<p>$1</p>\', $current);
        
        return $current;
    }';
    
    // Replace the function in content
    $content = preg_replace($original_reduce_div, $new_reduce_div, $content);
    
    // Fix the div to paragraph conversion in prepare_post_content
    $original_div_to_p = '/\$content = \$this->reduce_div_nesting\(\$content\);\s*\$content = preg_replace\(\'/<div>(.*?)<\/div>/is\', \'<p>\$1<\/p>\', \$content\);/s';
    $new_div_to_p = '$content = $this->reduce_div_nesting($content);
        // MODIFIED: Only convert divs to paragraphs if they only contain text (no HTML tags)
        $content = preg_replace(\'/<div[^>]*>([^<>]+?)<\/div>/is\', \'<p>$1</p>\', $content);
        // Remove empty divs
        $content = preg_replace(\'/<div[^>]*>\s*<\/div>/is\', \'\', $content);';
    
    // Replace the div conversion code
    $content = preg_replace($original_div_to_p, $new_div_to_p, $content);
    
    // Also update the prepare_page_content function in the same way
    $original_page_div_to_p = '/\$content = \$this->strip_attributes\(\$content\);/s';
    $new_page_div_to_p = '$content = $this->strip_attributes($content);
        
        // Fix div handling: only convert divs to paragraphs if they only contain text
        $content = preg_replace(\'/<div[^>]*>([^<>]+?)<\/div>/is\', \'<p>$1</p>\', $content);
        // Remove empty divs
        $content = preg_replace(\'/<div[^>]*>\s*<\/div>/is\', \'\', $content);';
    
    // Replace the div conversion code in prepare_page_content
    $content = preg_replace($original_page_div_to_p, $new_page_div_to_p, $content);
    
    // Write the modified content back to the file
    $result = file_put_contents($file_path, $content);
    
    if ($result !== false) {
        error_log('Successfully updated class-excel-processor.php to fix div wrapping issues');
        return true;
    } else {
        error_log('Failed to write updated class-excel-processor.php');
        return false;
    }
}

// Apply the fix
fix_div_wrapping_in_excel_processor();

// Display success message if this file is accessed directly
if (isset($_GET['run_fix'])) {
    echo '<div style="background-color: #e7f9e7; padding: 20px; border: 1px solid #4CAF50; margin: 20px; border-radius: 5px;">';
    echo '<h2>WordPress Content Migrator - DIV Wrapping Fix</h2>';
    echo '<p>The fix has been applied to prevent unwanted div tags in content.</p>';
    echo '<p>A backup of the original file has been created at: ' . plugin_dir_path(__FILE__) . 'class-excel-processor.php.bak</p>';
    echo '<p><a href="' . admin_url('tools.php?page=wordpress-content-migrator') . '">Return to Content Migrator</a></p>';
    echo '</div>';
} 