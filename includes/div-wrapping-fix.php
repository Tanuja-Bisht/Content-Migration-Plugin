<?php
/**
 * WordPress Content Migrator - DIV Wrapping Fix
 * 
 * This file contains functions to fix the unwanted div wrapping in imported content.
 * It should be included from the main plugin file and applied to the Excel_Processor class.
 */

// Make sure WordPress is loaded
if (!defined('WPINC')) {
    die;
}

/**
 * Apply the fix to the Excel_Processor class
 */
function apply_div_wrapping_fix() {
    // Check if the Excel_Processor class exists
    if (!class_exists('Excel_Processor')) {
        error_log('Excel_Processor class not found. Cannot apply div wrapping fix.');
        return false;
    }
    
    // Create a new instance of the Excel_Processor class
    $processor = new Excel_Processor();
    
    // Override the problematic methods
    add_filter('wpdb_pre_query', array($processor, 'fixed_reduce_div_nesting'), 10, 2);
    add_filter('content_save_pre', array($processor, 'fix_unwanted_divs'), 10, 1);
    
    return true;
}

/**
 * Add the fixed method to the Excel_Processor class
 */
add_action('plugins_loaded', function() {
    if (class_exists('Excel_Processor')) {
        // Add the fixed methods to the Excel_Processor class
        Excel_Processor::add_fixed_methods();
    }
});

/**
 * Add the fixed methods to the Excel_Processor class
 */
if (!function_exists('Excel_Processor::add_fixed_methods')) {
    // Add the fixed methods to the Excel_Processor class
    class_alias('Excel_Processor', 'Original_Excel_Processor');
    
    class Excel_Processor extends Original_Excel_Processor {
        /**
         * Fixed version of reduce_div_nesting that keeps proper HTML structure
         */
        public function fixed_reduce_div_nesting($html) {
            // First, eliminate completely empty divs
            $html = preg_replace('/<div[^>]*>\s*<\/div>/is', '', $html);
            
            // Then, eliminate deeply nested divs with no semantic content
            $pattern = '/<div[^>]*>\s*<div[^>]*>\s*<div[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/is';
            $replacement = '<div>$1</div>';
            
            // Apply multiple times to handle deep nesting
            $previous = '';
            $current = $html;
            
            // Keep applying until no more changes or reached iteration limit
            $iterations = 0;
            while ($previous !== $current && $iterations < 5) {
                $previous = $current;
                $current = preg_replace($pattern, $replacement, $previous);
                $iterations++;
            }
            
            // Now only convert divs to paragraphs if they only contain text (no HTML tags)
            $current = preg_replace('/<div[^>]*>([^<>]+?)<\/div>/is', '<p>$1</p>', $current);
            
            return $current;
        }
        
        /**
         * Fix unwanted divs in content before saving
         */
        public function fix_unwanted_divs($content) {
            // Don't process empty content
            if (empty($content)) {
                return $content;
            }
            
            // Clean up empty divs
            $content = preg_replace('/<div[^>]*>\s*<\/div>/is', '', $content);
            
            // Fix double paragraph wrapping
            $content = preg_replace('/<p[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/p>/is', '<p>$1</p>', $content);
            
            // Only convert divs to paragraphs if they only contain text (no HTML tags)
            $content = preg_replace('/<div[^>]*>([^<>]+?)<\/div>/is', '<p>$1</p>', $content);
            
            return $content;
        }
        
        /**
         * Override the original reduce_div_nesting method
         */
        public function reduce_div_nesting($html) {
            return $this->fixed_reduce_div_nesting($html);
        }
        
        /**
         * Add fixed methods to the class
         */
        public static function add_fixed_methods() {
            add_filter('content_save_pre', array(new self(), 'fix_unwanted_divs'), 10, 1);
        }
    }
}

// Apply the fix
apply_div_wrapping_fix(); 