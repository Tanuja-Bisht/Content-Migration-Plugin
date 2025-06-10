<?php
/**
 * Test script for Search and Replace functionality
 */

// Load WordPress
require_once dirname(dirname(dirname(__FILE__))) . '/wp-load.php';

// Load required files
require_once __DIR__ . '/includes/search-replace/class-search-replace.php';
require_once __DIR__ . '/includes/search-replace/class-csv-validator.php';
require_once __DIR__ . '/includes/search-replace/class-logger.php';

// Initialize the class
$search_replace = WP_Content_Migrator_Search_Replace::get_instance();

// Set to preview mode
$search_replace->set_preview_mode(true);

// Create some test replacement data
$replacements = array(
    array('search_for' => 'test', 'replace_with' => 'TEST'),
    array('search_for' => 'hello', 'replace_with' => 'HELLO')
);

// Set the replacements
$search_replace->set_replacements($replacements);

echo "<h1>Search and Replace Test</h1>";

try {
    // Get preview results
    echo "<h2>Trying to get preview results...</h2>";
    $results = $search_replace->get_preview_results();
    
    echo "<pre>";
    print_r($results);
    echo "</pre>";
    
    // Try to execute replacements (but still in preview mode)
    echo "<h2>Trying to get execute results...</h2>";
    $search_replace->set_preview_mode(false);
    $exec_results = $search_replace->run();
    
    echo "<pre>";
    print_r($exec_results);
    echo "</pre>";
} catch (Exception $e) {
    echo "<h2>Error occurred:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
} 