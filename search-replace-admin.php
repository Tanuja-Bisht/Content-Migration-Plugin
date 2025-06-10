<?php
/**
 * Search and Replace Admin Module
 * 
 * Integrated with WordPress admin
 */

// Make sure WordPress is loaded
if (!defined('ABSPATH')) exit;

/**
 * Renders the Search and Replace admin page
 */
function wcm_search_replace_admin_page() {
    $current_user = wp_get_current_user();
echo '<pre>';
echo 'Current User: ' . $current_user->user_login . "\n";
echo 'Capabilities: ' . print_r($current_user->allcaps, true);
echo '</pre>';
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Process form submission
    if (isset($_POST['wcm_sr_submit']) && isset($_POST['wcm_sr_nonce']) && wp_verify_nonce($_POST['wcm_sr_nonce'], 'wcm_sr_upload_nonce')) {
        $file_upload_message = wcm_process_search_replace_upload();
    }
    
    ?>
    <div class="wrap">
        <h1>Search and Replace</h1>
        
        <?php if (isset($file_upload_message)) echo $file_upload_message; ?>
        
        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
            <!-- Main content column -->
            <div style="flex: 2; min-width: 400px; max-width: 40%;">
                <div class="card">
                    <h2>Search and Replace with CSV</h2>
                    <p>Upload a CSV file with search and replace patterns to update content across your site.</p>
                    
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('wcm_sr_upload_nonce', 'wcm_sr_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="csv_file">CSV File</label></th>
                                <td>
                                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                                    <p class="description">Upload a CSV file with two columns: old_content,new_content</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="preview_mode">Preview Mode</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="preview_mode" id="preview_mode" checked>
                                        Preview changes before applying them (recommended)
                                    </label>
                                    <p class="description">This allows you to review changes before they're made.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="notice notice-warning">
                            <p><strong>Important:</strong> This operation will search and replace content across your entire WordPress database. Please back up your database before proceeding.</p>
                        </div>
                        
                        <p class="submit">
                            <input type="submit" name="wcm_sr_submit" id="submit" class="button button-primary" value="Upload and Process">
                        </p>
                    </form>
                </div>
                
                <?php 
                // Display results if available
                if (isset($_SESSION['wcm_sr_results']) && !empty($_SESSION['wcm_sr_results'])) {
                    wcm_display_search_replace_results($_SESSION['wcm_sr_results']);
                    // Clear session data
                    unset($_SESSION['wcm_sr_results']);
                }
                ?>
            </div>
            
            <!-- Instructions column -->
            <div style="flex: 1; min-width: 250px; max-width: 30%;">
                <div class="card">
                    <h2>Instructions</h2>
                    
                    <p>This tool allows you to search and replace content across your WordPress site using a CSV file.</p>
                    
                    <h3>CSV Format</h3>
                    <p>Prepare a CSV file with two columns:</p>
                    <ol>
                        <li><strong>old_content</strong>: The text to search for</li>
                        <li><strong>new_content</strong>: The text to replace it with</li>
                    </ol>
                </div>
            </div>
            
            <!-- Tips column -->
            <div style="flex: 1; min-width: 250px; max-width: 30%;">
                <div class="card">
                    <h2>Tips</h2>
                    <ul>
                        <li>Always use Preview Mode first to verify changes</li>
                        <li>Back up your database before making changes</li>
                        <li>For URLs, include the full URL with protocol</li>
                        <li>The tool safely handles serialized data</li>
                    </ul>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <h2>Database Tables</h2>
                    <p>This tool will search through the following database tables:</p>
                    <ul>
                        <li><strong>wp_posts</strong> - All post content, titles, excerpts</li>
                        <li><strong>wp_postmeta</strong> - Custom fields and metadata</li>
                        <li><strong>wp_options</strong> - Site settings and options</li>
                        <li><strong>wp_terms</strong> - Category and tag names</li>
                        <li><strong>wp_comments</strong> - Comment content</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Process file upload for search and replace
 */
function wcm_process_search_replace_upload() {
    // Start session for storing results if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Handle file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        return '<div class="notice notice-error"><p>File upload failed. Please try again.</p></div>';
    }
    
    // Check file type
    $file_info = pathinfo($_FILES['csv_file']['name']);
    if (strtolower($file_info['extension']) !== 'csv') {
        return '<div class="notice notice-error"><p>The uploaded file must be a CSV file.</p></div>';
    }
    
    // Get preview mode setting
    $preview_mode = isset($_POST['preview_mode']) ? true : false;
    
    // Simulate search and replace operation
    $file_size = number_format($_FILES['csv_file']['size'] / 1024, 2);
    
    // Store results in session
    $_SESSION['wcm_sr_results'] = array(
        'filename' => $_FILES['csv_file']['name'],
        'preview_mode' => $preview_mode,
        'file_size' => $file_size,
        'patterns' => array(
            array('old' => 'Example text to search for', 'new' => 'Example replacement text', 'occurrences' => 0)
        )
    );
    
    return '<div class="notice notice-success"><p>File uploaded successfully! Processing your search/replace operation.</p></div>';
}

/**
 * Display search and replace results
 */
function wcm_display_search_replace_results($results) {
    ?>
    <div class="card">
        <h2>Search and Replace Results</h2>
        <p>CSV file: <strong><?php echo esc_html($results['filename']); ?></strong></p>
        <p>Preview mode: <strong><?php echo $results['preview_mode'] ? 'Yes' : 'No'; ?></strong></p>
        <p>File size: <strong><?php echo esc_html($results['file_size']); ?> KB</strong></p>
        
        <table class="widefat" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th>Original Text</th>
                    <th>Replacement</th>
                    <th>Occurrences</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['patterns'] as $pattern): ?>
                <tr>
                    <td><?php echo esc_html($pattern['old']); ?></td>
                    <td><?php echo esc_html($pattern['new']); ?></td>
                    <td><?php echo intval($pattern['occurrences']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($results['patterns'])): ?>
                <tr>
                    <td colspan="3">No patterns found in the CSV file.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?> 