<?php
/**
 * Search and Replace Handler
 * 
 * Handles form submissions and processing for the Search and Replace functionality.
 */

// Start session at the very beginning before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Increase execution time for larger databases
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300'); // 5 minutes
@set_time_limit(300);

// Include the debug functions file
require_once plugin_dir_path(dirname(__FILE__)) . 'debug-functions.php';

// Debug: Add a log entry to confirm this file is loaded
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
error_log('Search and Replace Handler file is loaded');
    wcm_debug_log('Search and Replace Handler file is loaded');
}

// Include any required files here
require_once dirname(__FILE__) . '/class-search-replace.php';
require_once dirname(__FILE__) . '/class-csv-validator.php';
require_once dirname(__FILE__) . '/class-logger.php';

/**
 * Process search and replace request and preview changes
 * 
 * @param array $replacements Array of replacements from CSV file
 * @param bool $preview Whether to preview changes (true) or execute them (false)
 * @return array Results of the operation
 */
function wp_content_migrator_process_replacements($replacements, $preview = true) {
    // Create a new instance of the search and replace class
    $search_replace = WP_Content_Migrator_Search_Replace::get_instance();
    
    // Set preview mode
    $search_replace->set_preview_mode($preview);
    
    // Set the replacements
    $search_replace->set_replacements($replacements);
    
    // Process the replacements
    try {
        if ($preview) {
            // Get the preview results
            $results = $search_replace->get_preview_results();
        } else {
            // Execute the replacements
            $results = $search_replace->execute_replacements();
        }
        
        if (is_wp_error($results)) {
            return array(
                'error' => $results->get_error_message(),
                'tables_processed' => 0,
                'rows_processed' => 0,
                'changes' => 0,
                'errors' => 1
            );
        }
        
        return $results;
    } catch (Exception $e) {
        return array(
            'error' => $e->getMessage(),
            'tables_processed' => 0,
            'rows_processed' => 0,
            'changes' => 0,
            'errors' => 1
        );
    }
}

/**
 * Process CSV file and extract replacements
 * 
 * @param string $file_path Path to the CSV file
 * @return array|WP_Error Array of replacements or WP_Error on failure
 */
function wp_content_migrator_process_csv($file_path) {
    // Validate the CSV file
    $validator = new WP_Content_Migrator_CSV_Validator();
    $validation_result = $validator->validate_file($file_path);
    
    if (is_wp_error($validation_result)) {
        return $validation_result;
    }
    
    // Process the CSV file and extract the replacements
    $replacements = array();
    $handle = fopen($file_path, 'r');
    
    // Skip the header row
    fgetcsv($handle);
    
    // Process each line
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 2) {
            $replacements[] = array(
                'search_for' => $data[0],
                'replace_with' => $data[1]
            );
        }
    }
    
    fclose($handle);
    
    if (empty($replacements)) {
        return new WP_Error('empty_csv', 'No valid replacements found in the CSV file.');
    }
    
    return $replacements;
}

/**
 * Execute search and replace operation
 * 
 * @param bool $return_results Whether to return results instead of redirecting
 * @return array|void Results array if $return_results is true, otherwise redirects
 */
function wp_content_migrator_execute_search_replace($return_results = false) {
    // Always return results for the single-page workflow
    $return_results = true;
    
    // Verify nonce if coming from form
    if (isset($_POST['wp_content_migrator_search_replace_execute_nonce'])) {
        if (!wp_verify_nonce($_POST['wp_content_migrator_search_replace_execute_nonce'], 'wp_content_migrator_search_replace_execute')) {
            if ($return_results) {
                return array(
                    'error' => 'Security check failed.',
                    'tables_processed' => 0,
                    'rows_processed' => 0,
                    'changes' => 0,
                    'errors' => 1
                );
            } else {
                wp_die('Security check failed.');
            }
        }
    }
    
    // For debugging: log session data to check what's stored
    if (function_exists('wcm_debug_log')) {
        wcm_debug_log('SESSION data in execute: ' . print_r($_SESSION, true));
    }
    
    // Check if we have replacements in session
    if (!isset($_SESSION['wp_content_migrator_search_replace_replacements']) || empty($_SESSION['wp_content_migrator_search_replace_replacements'])) {
        if ($return_results) {
            return array(
                'error' => 'No replacement data found. Please upload a CSV file first.',
                'tables_processed' => 0,
                'rows_processed' => 0,
                'changes' => 0,
                'errors' => 1
            );
        } else {
            // Redirect back to the admin page with an error
            add_settings_error(
                'wp_content_migrator_search_replace',
                'no_replacements',
                'No replacement data found. Please upload a CSV file first.',
                'error'
            );
            wp_redirect(add_query_arg(array('page' => 'custom-search-replace-migrator'), admin_url('admin.php')));
            exit;
        }
    }
    
    // Include required files if not already included
    if (!class_exists('WP_Content_Migrator_Search_Replace')) {
        require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/search-replace/class-search-replace.php';
    }
    
    // Process the actual replacements
        $search_replace = WP_Content_Migrator_Search_Replace::get_instance();
        
    // Set to execute mode (not preview)
    $search_replace->set_preview_mode(false);
    
    // Log the replacements before setting them
    if (function_exists('wcm_debug_log')) {
        wcm_debug_log('Replacements from session: ' . print_r($_SESSION['wp_content_migrator_search_replace_replacements'], true));
    }
    
    // Load the replacements from session
    $search_replace->set_replacements($_SESSION['wp_content_migrator_search_replace_replacements']);
    
    try {
        // Execute the replacements
        $results = $search_replace->execute_replacements();
        
        if (is_wp_error($results)) {
            if ($return_results) {
                return array(
                    'error' => $results->get_error_message(),
                    'tables_processed' => 0,
                    'rows_processed' => 0,
                    'changes' => 0,
                    'errors' => 1
                );
            } else {
                add_settings_error(
                    'wp_content_migrator_search_replace',
                    'execute_error',
                    $results->get_error_message(),
                    'error'
                );
                wp_redirect(add_query_arg(array('page' => 'custom-search-replace-migrator'), admin_url('admin.php')));
                exit;
            }
        }
        
        // Clear the session data when done
        unset($_SESSION['wp_content_migrator_search_replace_replacements']);
        
        if ($return_results) {
            return $results;
        } else {
            // Store the results in session to display them after redirect
            $_SESSION['wp_content_migrator_search_replace_results'] = $results;
            
            // Add a success message
            add_settings_error(
                'wp_content_migrator_search_replace',
                'execute_success',
                'Search and replace completed successfully! ' . $results['changes'] . ' changes were applied.',
                'success'
            );
            
            // Redirect to show the results
            wp_redirect(add_query_arg(array('page' => 'custom-search-replace-migrator', 'action' => 'results'), admin_url('admin.php')));
                exit;
            }
        } catch (Exception $e) {
        if ($return_results) {
            return array(
                'error' => $e->getMessage(),
                'tables_processed' => 0,
                'rows_processed' => 0,
                'changes' => 0,
                'errors' => 1
            );
        } else {
            add_settings_error(
                'wp_content_migrator_search_replace',
                'execute_error',
                $e->getMessage(),
                'error'
            );
            wp_redirect(add_query_arg(array('page' => 'custom-search-replace-migrator'), admin_url('admin.php')));
            exit;
        }
    }
}

/**
 * Validates and processes a CSV file upload for search and replace
 * 
 * @param array $file The uploaded file from $_FILES
 * @return array|WP_Error The extracted replacements or an error
 */
function wp_content_migrator_validate_file_upload($file) {
    // Check if file was uploaded successfully
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log('WCM Debug - Upload error in validation function: ' . $file['error']);
        return new WP_Error('upload_error', 'File upload failed: ' . $file['error']);
    }
    
    // Check if the temp file exists
    error_log('WCM Debug - Temp file path: ' . $file['tmp_name']);
    if (!file_exists($file['tmp_name'])) {
        error_log('WCM Debug - Temp file does not exist!');
        return new WP_Error('tmp_file_missing', 'The uploaded file could not be found on the server.');
    }
    
    // Check file type
    $file_info = pathinfo($file['name']);
    if (strtolower($file_info['extension']) !== 'csv') {
        return new WP_Error('invalid_file_type', 'The uploaded file must be a CSV file.');
    }
    
    // Process the CSV file
    return wp_content_migrator_process_csv($file['tmp_name']);
}

/**
 * Process search and replace request and preview changes
 * 
 * @param bool $return_results Whether to return results instead of redirecting
 * @return array|void Results array if $return_results is true, otherwise redirects
 */
function wp_content_migrator_process_search_replace($return_results = false) {
    // Always return results for the single-page workflow
    $return_results = true;
    
    // Process CSV upload
    if (isset($_POST['wp_content_migrator_search_replace_submit'])) {
        if (!current_user_can('manage_options')) {
            return array('error' => 'You do not have sufficient permissions to access this page.');
        }
        
        // Verify nonce
        if (!isset($_POST['wp_content_migrator_search_replace_nonce']) || 
            !wp_verify_nonce($_POST['wp_content_migrator_search_replace_nonce'], 'wp_content_migrator_search_replace')) {
            return array('error' => 'Security check failed.');
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['search_replace_file']) || $_FILES['search_replace_file']['error'] !== UPLOAD_ERR_OK) {
            return array('error' => 'File upload failed. Please try again.');
        }
        
        // Process the file
        $replacements = wp_content_migrator_validate_file_upload($_FILES['search_replace_file']);
        
        if (is_wp_error($replacements)) {
            return array('error' => $replacements->get_error_message());
        }
        
        // Store the replacements in session for later use (for execute step)
        $_SESSION['wp_content_migrator_search_replace_replacements'] = $replacements;
        
        // Process the replacements in preview mode
        $results = wp_content_migrator_process_replacements($replacements, true);
        
        // Return the preview results
        return $results;
    }
    // Process execute request (after seeing preview)
    else if (isset($_POST['execute_changes'])) {
        if (!current_user_can('manage_options')) {
            return array('error' => 'You do not have sufficient permissions to access this page.');
        }
        
        // Verify nonce
        if (!isset($_POST['wp_content_migrator_search_replace_execute_nonce']) || 
            !wp_verify_nonce($_POST['wp_content_migrator_search_replace_execute_nonce'], 'wp_content_migrator_search_replace_execute')) {
            return array('error' => 'Security check failed.');
        }
        
        // Check if file was uploaded again for execution
        if (!isset($_FILES['search_replace_file_execute']) || $_FILES['search_replace_file_execute']['error'] !== UPLOAD_ERR_OK) {
            return array('error' => 'Please upload the CSV file again to execute changes.');
        }
        
        // Process the file for execution
        $replacements = wp_content_migrator_validate_file_upload($_FILES['search_replace_file_execute']);
        
        if (is_wp_error($replacements)) {
            return array('error' => $replacements->get_error_message());
        }
        
        // Execute the replacements (not in preview mode)
        $search_replace = WP_Content_Migrator_Search_Replace::get_instance();
        $search_replace->set_preview_mode(false);
        $search_replace->set_replacements($replacements);
        
        try {
            // Execute the replacements
            $results = $search_replace->execute_replacements();
            
            if (is_wp_error($results)) {
                return array(
                    'error' => $results->get_error_message(),
                    'tables_processed' => 0,
                    'rows_processed' => 0,
                    'changes' => 0,
                    'errors' => 1
                );
            }
            
            return $results;
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
                'tables_processed' => 0,
                'rows_processed' => 0,
                'changes' => 0,
                'errors' => 1
            );
        }
    }
    
    return array(
        'error' => 'No form submission detected.',
        'tables_processed' => 0,
        'rows_processed' => 0,
        'changes' => 0,
        'errors' => 1
    );
}

/**
 * Legacy handler function - still supports the old approach for backward compatibility
 */
function wp_content_migrator_handle_search_replace() {
    // If called with the new return_results parameter, defer to the new functions
    $args = func_get_args();
    if (!empty($args) && isset($args[0]) && $args[0] === true) {
        if (isset($_POST['wp_content_migrator_search_replace_submit'])) {
            return wp_content_migrator_process_search_replace(true);
        } elseif (isset($_POST['execute_changes'])) {
            return wp_content_migrator_execute_search_replace(true);
        }
        return array();
    }
    
    // Otherwise process normally with the old redirect approach
    if (isset($_POST['wp_content_migrator_search_replace_submit'])) {
        wp_content_migrator_process_search_replace(false);
    } else if (isset($_POST['execute_changes'])) {
        wp_content_migrator_execute_search_replace(false);
    }
    
    // Handle sample CSV download
    if (isset($_GET['action']) && $_GET['action'] === 'sample') {
        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="search-replace-sample.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create the CSV content
        $output = fopen('php://output', 'w');
        fputcsv($output, array('old_content', 'new_content'));
        fputcsv($output, array('https://oldsite.com', 'https://newsite.com'));
        fputcsv($output, array('https://oldsite.com/blog', 'https://newsite.com/blog'));
        fputcsv($output, array('http://oldsite.com', 'https://newsite.com'));
        fputcsv($output, array('info@oldsite.com', 'info@newsite.com'));
        fputcsv($output, array('+1 (800) 123-4567', '+1 (800) 765-4321'));
        fputcsv($output, array('123 Old Street, Old City, Old State', '456 New Avenue, New City, New State'));
        fputcsv($output, array('Old Company Name', 'New Company Name'));
        fputcsv($output, array('Old Product', 'New Product'));
        fputcsv($output, array('Copyright © 2022', 'Copyright © 2023'));
        fclose($output);
        
        exit;
    }
}

// Register handler with WordPress
add_action('admin_init', 'wp_content_migrator_handle_search_replace'); 