<?php
/**
 * Search and Replace Admin Page
 * 
 * Provides the UI for the Search and Replace functionality.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Load required classes and functions
require_once plugin_dir_path(__FILE__) . 'class-search-replace.php';
require_once plugin_dir_path(__FILE__) . 'class-csv-validator.php';
require_once plugin_dir_path(__FILE__) . 'class-logger.php';
require_once plugin_dir_path(__FILE__) . 'handler.php';

/**
 * Render the Search and Replace admin page
 */
function wp_content_migrator_search_replace_page() {
    // Check if we need to start a session
    if (!session_id()) {
        session_start();
    }
    
    // Store the original error reporting level for later restoration
    $original_error_level = error_reporting();
    
    // Temporarily disable deprecation warnings for this page
    error_reporting($original_error_level & ~E_DEPRECATED);
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Initialize variables to track the page state
    $preview_results = null;
    $execution_results = null;
    $error_message = null;
    $success_message = null;
    $search_replace = WP_Content_Migrator_Search_Replace::get_instance();
    
    // Process CSV upload form submission
    if (isset($_POST['wp_content_migrator_search_replace_submit'])) {
        // Clear previous results
        $preview_results = null;
        $execution_results = null;
        
        // Verify nonce
        if (!isset($_POST['wp_content_migrator_search_replace_nonce']) || 
            !wp_verify_nonce($_POST['wp_content_migrator_search_replace_nonce'], 'wp_content_migrator_search_replace')) {
            $error_message = 'Security check failed. Please try again.';
        } else {
            // Regular file upload process for initial preview
            if (!isset($_FILES['search_replace_file']) || $_FILES['search_replace_file']['error'] !== UPLOAD_ERR_OK) {
                // Handle upload errors with specific messages
                $error_message = 'File upload failed. Please try again.';
                
                // Debug information
                error_log('WCM Debug - File upload failed: ' . print_r($_FILES, true));
                
                if (isset($_FILES['search_replace_file'])) {
                    error_log('WCM Debug - Upload error code: ' . $_FILES['search_replace_file']['error']);
                    
                    switch ($_FILES['search_replace_file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = 'The uploaded file is too large.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = 'The file was only partially uploaded.';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = 'No file was uploaded. Please select a CSV file.';
                            break;
                    }
                }
            } else {
                // Check file type
                $file_info = pathinfo($_FILES['search_replace_file']['name']);
                if (strtolower($file_info['extension']) !== 'csv') {
                    $error_message = 'The uploaded file must be a CSV file.';
                } else {
                    // Process CSV file
                    try {
                        // Get the selected operation mode
                        $operation_mode = isset($_POST['operation_mode']) ? $_POST['operation_mode'] : 'preview';
                        
                        // Store the operation mode in the session for later use
                        $_SESSION['wp_content_migrator_operation_mode'] = $operation_mode;
                        error_log('WCM Debug - Stored operation mode in session: ' . $operation_mode);
                        
                        // Process the CSV file
                        $replacements = $search_replace->process_csv_file($_FILES['search_replace_file']['tmp_name']);
                        
                        if (is_wp_error($replacements)) {
                            $error_message = 'Error processing CSV: ' . $replacements->get_error_message();
                        } else {
                            // Store the replacements
                            $search_replace->set_replacements($replacements);
                            
                            // Process according to operation mode
                            if ($operation_mode === 'preview') {
                                error_log('WCM Debug - Setting preview mode to TRUE for preview operation');
                                // Preview mode - show changes first
                                $search_replace->set_preview_mode(true);
                                $preview_results = $search_replace->get_preview_results();
                                $success_message = 'File processed successfully. Please review the changes below.';
                            } else {
                                error_log('WCM Debug - Setting preview mode to FALSE for direct operation');
                                // Direct mode - apply changes immediately
                                $search_replace->set_preview_mode(false);
                                $execution_results = $search_replace->execute_replacements();
                                
                                if (is_wp_error($execution_results)) {
                                    $error_message = 'Error executing replacements: ' . $execution_results->get_error_message();
                                } else {
                                    $success_message = 'Search and replace operation completed successfully.';
                                    // Clear transient after successful execution
                                    delete_transient('wp_content_migrator_search_replace_replacements');
                                    // Clear session operation mode
                                    if (isset($_SESSION['wp_content_migrator_operation_mode'])) {
                                        unset($_SESSION['wp_content_migrator_operation_mode']);
                                        error_log('WCM Debug - Cleared operation mode from session after execution');
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $error_message = 'Error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
    // Process execute changes form submission
    else if (isset($_POST['execute_changes'])) {
        // Clear preview results
        $preview_results = null;
        
        // Verify nonce
        if (!isset($_POST['wp_content_migrator_search_replace_execute_nonce']) || 
            !wp_verify_nonce($_POST['wp_content_migrator_search_replace_execute_nonce'], 'wp_content_migrator_search_replace_execute')) {
            $error_message = 'Security check failed. Please try again.';
        } else {
            try {
                // Get stored replacements from preview step
                $replacements = get_transient('wp_content_migrator_search_replace_replacements');
                
                if (!$replacements || !is_array($replacements) || empty($replacements)) {
                    // If no stored replacements, try to get from file upload
                    if (isset($_FILES['search_replace_file_execute']) && $_FILES['search_replace_file_execute']['error'] === UPLOAD_ERR_OK) {
                        // Process the CSV file
                        $file_info = pathinfo($_FILES['search_replace_file_execute']['name']);
                        if (strtolower($file_info['extension']) !== 'csv') {
                            $error_message = 'The uploaded file must be a CSV file.';
                        } else {
                            $replacements = $search_replace->process_csv_file($_FILES['search_replace_file_execute']['tmp_name']);
                        }
                    } else {
                        $error_message = 'No replacement data found. Please start over and try again.';
                    }
                }
                
                if (!is_wp_error($replacements) && !isset($error_message)) {
                    // Set up for execution
                    $search_replace->set_preview_mode(false);
                    $search_replace->set_replacements($replacements);
                    
                    // Execute replacements
                    $execution_results = $search_replace->execute_replacements();
                    
                    if (is_wp_error($execution_results)) {
                        $error_message = 'Error executing replacements: ' . $execution_results->get_error_message();
                    } else {
                        $success_message = 'Search and replace operation completed successfully.';
                        // Clear transient after successful execution
                        delete_transient('wp_content_migrator_search_replace_replacements');
                        // Clear session operation mode
                        if (isset($_SESSION['wp_content_migrator_operation_mode'])) {
                            unset($_SESSION['wp_content_migrator_operation_mode']);
                            error_log('WCM Debug - Cleared operation mode from session after execution');
                        }
                    }
                } else if (is_wp_error($replacements)) {
                    $error_message = 'Error processing replacements: ' . $replacements->get_error_message();
                }
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
            }
        }
    }
    // Process reset request from form submission
    else if (isset($_POST['reset_search_replace'])) {
        // Verify nonce
        if (!isset($_POST['wp_content_migrator_reset_nonce']) || 
            !wp_verify_nonce($_POST['wp_content_migrator_reset_nonce'], 'wp_content_migrator_reset')) {
            $error_message = 'Security check failed. Please try again.';
        } else {
            // Reset any results variables for this page load
            $preview_results = null;
            $execution_results = null;
            
            // Clear any stored replacements
            delete_transient('wp_content_migrator_search_replace_replacements');
            
            // Clear session operation mode
            if (isset($_SESSION['wp_content_migrator_operation_mode'])) {
                unset($_SESSION['wp_content_migrator_operation_mode']);
                error_log('WCM Debug - Cleared operation mode from session during reset');
            }
            
            $success_message = 'Search and replace data has been reset. You can now upload a new CSV file.';
        }
    }
    
    // Check if reset action is requested
    if (isset($_GET['action']) && $_GET['action'] === 'reset') {
        // Reset any results variables for this page load
        $preview_results = null;
        $execution_results = null;
        
        // Clear any stored replacements
        delete_transient('wp_content_migrator_search_replace_replacements');
        
        // Clear session operation mode
        if (isset($_SESSION['wp_content_migrator_operation_mode'])) {
            unset($_SESSION['wp_content_migrator_operation_mode']);
            error_log('WCM Debug - Cleared operation mode from session during reset');
        }
        
        $success_message = 'Search and replace data has been reset. You can now upload a new CSV file.';
        
        // Redirect to the main page without the action=reset parameter
        // This prevents issues with preview mode after reset
        wp_redirect(add_query_arg(array('page' => 'custom-search-replace-migrator'), admin_url('admin.php')));
        exit;
    }
    
    // Start main page output
    ?>
    <div class="wrap search-replace-admin-page">
        <h1>Search and Replace</h1>
        
        <?php 
        // Debug PHP upload configuration
        error_log('WCM Debug - PHP upload_max_filesize: ' . ini_get('upload_max_filesize'));
        error_log('WCM Debug - PHP post_max_size: ' . ini_get('post_max_size'));
        error_log('WCM Debug - PHP memory_limit: ' . ini_get('memory_limit'));
        
        // Display error message if any
        if ($error_message) {
            echo '<div class="notice notice-error" style="background-color: #f8d7da !important; color: #721c24 !important; border-color: #f5c6cb !important;"><p style="font-weight: bold;">' . esc_html($error_message) . '</p></div>';
        }
        
        // Display success message if any
        if ($success_message) {
            echo '<div class="notice notice-success" style="background-color: #d4edda !important; color: #155724 !important; border-color: #c3e6cb !important;"><p style="font-weight: bold;">' . esc_html($success_message) . '</p></div>';
        }
        ?>
        
        <!-- Hero button for Content Migration -->
        <div class="module-navigation" style="margin: 15px 0;">
            <a href="<?php echo admin_url('admin.php?page=content-migrator'); ?>" class="button button-primary button-hero" style="background-color: #2271b1; border-color: #2271b1; color: white; text-shadow: none; font-weight: bold;">Content Migration Tool</a>
        </div>
        
        <div class="horizontal-cards">
            <!-- Main column - form -->
            <div class="card main-card">
                <?php 
                // Show results if available
                if ($execution_results) {
                    // Include operation results template
                    $results = $execution_results;
                    include plugin_dir_path(__FILE__) . 'templates/operation-results.php';
                } elseif ($preview_results) {
                    // Include preview results template
                    $results = $preview_results;
                    include plugin_dir_path(__FILE__) . 'templates/preview-results.php';
                } else {
                    // Show the upload form
                ?>
                    <h2>Search and Replace with CSV</h2>
                    <p>Upload a CSV file with search and replace patterns to update content across your site.</p>
                    
                    <form method="post" enctype="multipart/form-data" action="">
                        <?php wp_nonce_field('wp_content_migrator_search_replace', 'wp_content_migrator_search_replace_nonce'); ?>
                        
                        <?php // Debug form submission data
                        error_log('WCM Debug - Form HTML: enctype=' . htmlspecialchars('multipart/form-data')); 
                        ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="search_replace_file">CSV File</label></th>
                                <td>
                                    <input type="file" name="search_replace_file" id="search_replace_file" accept=".csv" required>
                                    <p class="description">Upload a CSV file with two columns: old_content,new_content<br>
                                    <strong>Format:</strong> The first row should be a header (old_content,new_content), and all rows after that should contain the text to search for and the text to replace it with.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="preview_mode">Operation Mode</label></th>
                                <td>
                                    <label><input type="radio" name="operation_mode" id="preview_mode" value="preview" checked> Preview Changes First</label><br>
                                    <label><input type="radio" name="operation_mode" id="direct_mode" value="direct"> Apply Changes Directly</label>
                                    <p class="description">
                                        <strong>Preview Changes First</strong>: Shows you a summary of what changes will be made with sample data.<br>
                                        <strong>Apply Changes Directly</strong>: Makes all changes immediately without showing a preview.
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="wp_content_migrator_search_replace_submit" id="submit" class="button button-primary" value="Process CSV File">
                            <a href="?page=custom-search-replace-migrator&action=sample" class="button">Download Sample CSV</a>
                        </p>
                    </form>
                    
                    <script>
                    // Update button text based on selected operation mode
                    jQuery(document).ready(function($) {
                        // Initial update
                        updateButtonText();
                        
                        // Update on change
                        $('input[name="operation_mode"]').change(function() {
                            updateButtonText();
                        });
                        
                        function updateButtonText() {
                            if ($('#preview_mode').is(':checked')) {
                                $('#submit').val('Preview Changes');
                            } else {
                                $('#submit').val('Apply Changes Directly');
                            }
                        }
                    });
                    </script>
                <?php } ?>
            </div>
            
            <!-- Instructions card -->
            <div class="card instructions-card">
                <h2>Instructions</h2>
                
                <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold;">
                    <p style="margin: 0; font-size: 14px;"><strong>Important:</strong> This operation will search and replace content across your entire WordPress database. Please back up your database before proceeding.</p>
                </div>
                
                <p>This tool allows you to search and replace content across your WordPress site using a CSV file.</p>
                
                <h3>CSV Format</h3>
                <p>Prepare a CSV file with two columns:</p>
                <ol>
                    <li><strong>old_content</strong>: The text to search for</li>
                    <li><strong>new_content</strong>: The text to replace it with</li>
                </ol>
                
                <h3>Process</h3>
                <ol>
                    <li>Upload your CSV file</li>
                    <li>Review the changes in preview mode</li>
                    <li>Apply the changes if everything looks correct</li>
                </ol>
            </div>
            
            <!-- Tips card -->
            <div class="card tips-card">
                <h2>Tips</h2>
                <ul>
                    <li>Always back up your database before making changes</li>
                    <li>For URLs, include the full URL with protocol</li>
                    <li>The tool safely handles serialized data</li>
                    <li>Review changes carefully before applying</li>
                </ul>
                <?php if ($preview_results || $execution_results) : ?>
                <p class="submit">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=custom-search-replace-migrator')); ?>">
                        <?php wp_nonce_field('wp_content_migrator_reset', 'wp_content_migrator_reset_nonce'); ?>
                        <input type="hidden" name="reset_search_replace" value="1">
                        <button type="submit" class="button">Start New Search/Replace</button>
                    </form>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
    // Include styles within the function's HTML output
    echo '<style>
        /* Styles for the admin layout */
        .horizontal-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            width: 100%;
        }
        
        .card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 2px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        
        /* Make sure all forms properly handle file uploads */
        form {
            display: block;
        }

        /* Hide any global/top warning banners */
        .wrap.search-replace-admin-page > .notice-warning,
        .wrap.search-replace-admin-page > div[class*="notice"] {
            display: none !important;
        }
        
        /* Only show the warning in the instructions card */
        .instructions-card .notice {
            display: block !important;
        }

        /* Enhanced color styling for messages */
        .notice-success {
            background-color: #d4edda !important;
            color: #155724 !important;
            border-color: #c3e6cb !important;
        }
        
        .notice-error, .notice-warning {
            background-color: #f8d7da !important;
            color: #721c24 !important;
            border-color: #f5c6cb !important;
        }
        
        /* Status styling */
        .status-success {
            color: #155724;
            font-weight: bold;
        }
        
        .status-error, .status-warning {
            color: #721c24;
            font-weight: bold;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .horizontal-cards {
                flex-direction: column;
            }
            
            .horizontal-cards .card {
                min-width: 100%;
            }
        }
    </style>';

    // Restore original error reporting level
    error_reporting($original_error_level); 
}
?> 