<?php
/**
 * The main plugin class
 */

class Content_Migrator {

    /**
     * Initialize the plugin
     */
    public function run() {
        // Add hooks
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_post_content_migrator_upload', array($this, 'handle_file_upload'));
        add_action('admin_post_content_migrator_download_sample', array($this, 'download_sample_csv'));
        add_action('admin_post_content_migrator_url_replace', array($this, 'handle_url_replace'));
        
        // Add handler for the Format Document feature
        add_action('admin_post_content_migrator_format_document', array($this, 'handle_format_document'));
        add_action('admin_post_content_migrator_download_formatted', array($this, 'handle_download_formatted'));
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style('content-migrator', CONTENT_MIGRATOR_PLUGIN_URL . 'admin/css/content-migrator-admin.css', array(), CONTENT_MIGRATOR_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script('content-migrator', CONTENT_MIGRATOR_PLUGIN_URL . 'admin/js/content-migrator-admin.js', array('jquery'), CONTENT_MIGRATOR_VERSION . '.' . time(), false);
    }

    /**
     * Add admin menu pages.
     */
    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page(
            'Content Migrator',
            'Content Migrator',
            'manage_options',
            'content-migrator',
            null, // No callback for main menu
            'dashicons-upload',
            81
        );
        
        // Submenu - Start Migration (existing functionality)
        add_submenu_page(
            'content-migrator',
            'Start Migration',
            'Start Migration',
            'manage_options',
            'content-migrator',
            array($this, 'display_plugin_admin_page')
        );
        
        // Submenu - URL Search & Replace
        add_submenu_page(
            'content-migrator',
            'URL Search & Replace',
            'URL Search & Replace',
            'manage_options',
            'content-migrator-url-replace',
            array($this, 'display_url_replace_page')
        );
        
        // Format Document submenu is hidden for now
        /*
        // Submenu - Format Document (renamed from Create Document)
        add_submenu_page(
            'content-migrator',
            'Format Document',
            'Format Document',
            'manage_options',
            'content-migrator-format-document',
            array($this, 'display_format_document_page')
        );
        */
    }

    /**
     * Display the admin page.
     */
    public function display_plugin_admin_page() {
        try {
            // Display the admin page
            include_once CONTENT_MIGRATOR_PLUGIN_DIR . 'admin/partials/content-migrator-admin-display.php';
        } catch (Exception $e) {
            echo '<div class="wrap"><h1>Content Migrator</h1>';
            echo '<div class="error"><p>' . $e->getMessage() . '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Display the URL search and replace page.
     */
    public function display_url_replace_page() {
        // Load the URL replacer class
        require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-url-replacer.php';
        $url_replacer = new URL_Replacer();
        $url_replacer->display_url_replace_page();
    }

    /**
     * Handle URL search and replace
     */
    public function handle_url_replace() {
        // Load the URL replacer class
        require_once CONTENT_MIGRATOR_PLUGIN_DIR . 'includes/class-url-replacer.php';
        $url_replacer = new URL_Replacer();
        $url_replacer->handle_url_replace();
    }

    /**
     * Process Excel file upload.
     */
    public function handle_file_upload() {
        // Check nonce for security
        if (!isset($_POST['content_migrator_nonce']) || !wp_verify_nonce($_POST['content_migrator_nonce'], 'content_migrator_upload')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $allow_overwrite = isset($_POST['allow_overwrite']) ? true : false;
        $results = array();
        $errors = array();
        $success_message = '';

        // Check if file was uploaded
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'File upload failed.';
            
            // Provide more specific error messages based on the error code
            if (isset($_FILES['excel_file']['error'])) {
                switch ($_FILES['excel_file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_message .= ' The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message .= ' The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message .= ' The uploaded file was only partially uploaded.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message .= ' No file was uploaded.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message .= ' Missing a temporary folder.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message .= ' Failed to write file to disk.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message .= ' A PHP extension stopped the file upload.';
                        break;
                }
            }
            
            $errors[] = $error_message;
            $this->redirect_with_data($errors, $results, '');
            return;
        }

        // Check file extension - only allow CSV
        $file_extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            $errors[] = 'File must have .csv extension.';
            $this->redirect_with_data($errors, $results, '');
            return;
        }

        // Process the file
        try {
            $processor = new Excel_Processor();
            $results = $processor->process_excel_file($_FILES['excel_file']['tmp_name'], $allow_overwrite);
            
            // Create success message
            $success_count = 0;
            $error_count = 0;
            $skipped_count = 0;
            
            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    $success_count++;
                } elseif ($result['status'] === 'error') {
                    $error_count++;
                } elseif ($result['status'] === 'skipped') {
                    $skipped_count++;
                }
            }
            
            $success_message = sprintf(
                'Migration completed: %d created/updated, %d skipped, %d failed.', 
                $success_count, 
                $skipped_count, 
                $error_count
            );
            
            // Flush rewrite rules to ensure new pages are accessible
            $this->flush_permalinks();
        } catch (Exception $e) {
            $errors[] = 'Error processing file: ' . $e->getMessage();
        }

        // Redirect back with results
        $this->redirect_with_data($errors, $results, $success_message);
    }

    /**
     * Flush permalinks to ensure pages are accessible
     */
    private function flush_permalinks() {
        // Save the current permalink structure
        $permalink_structure = get_option('permalink_structure');
        
        // Update permalinks twice, with a slight change to force a refresh
        update_option('permalink_structure', $permalink_structure . '/');
        flush_rewrite_rules();
        
        // Restore original permalink structure
        update_option('permalink_structure', $permalink_structure);
        flush_rewrite_rules();
    }

    /**
     * Generate and download a sample CSV file
     */
    public function download_sample_csv() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'content_migrator_download_sample')) {
            wp_die('Security check failed');
        }

        // Create Excel processor instance
        $excel_processor = new Excel_Processor();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="content-migrator-sample.csv"');
        header('Pragma: no-cache');
        
        // Generate sample data
        $excel_processor->generate_sample_excel();
        
        exit;
    }

    /**
     * Redirect with data
     */
    private function redirect_with_data($errors, $results, $success_message) {
        // Store data in transient
        $transient_key = 'content_migrator_results_' . wp_get_current_user()->ID;
        set_transient($transient_key, array(
            'errors' => $errors,
            'results' => $results,
            'success_message' => $success_message
        ), 60 * 5); // 5 minutes expiration

        // Redirect back to admin page
        wp_redirect(admin_url('admin.php?page=content-migrator&processed=1'));
        exit;
    }

    /**
     * Handle download of formatted file and cleanup
     */
    public function handle_download_formatted() {
        // Check nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'content_migrator_download_formatted')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Get the file path from transient
        $file_path = get_transient('content_migrator_formatted_file_' . get_current_user_id());
        if (!$file_path || !file_exists($file_path)) {
            wp_die('File not found or has expired. Please format your document again.');
        }

        // Get file info
        $file_name = basename($file_path);
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $file_size = filesize($file_path);

        // Set appropriate headers for download
        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        header('Pragma: public');

        // Ensure we're not using output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Output file content
        readfile($file_path);

        // Delete the file after download
        @unlink($file_path);
        
        // Remove the transient
        delete_transient('content_migrator_formatted_file_' . get_current_user_id());
        
        exit;
    }
}
