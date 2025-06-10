<?php
/**
 * Batch Admin for WordPress Content Migrator
 * 
 * Handles the admin UI for batch processing
 */
class Batch_Admin {
    
    private $batch_processor;
    
    /**
     * Initialize the batch admin
     */
    public function __construct() {
        // Initialize batch processor
        $this->batch_processor = new Batch_Processor();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));
        
        // Handle file upload via AJAX
        add_action('wp_ajax_wcm_batch_upload', array($this, 'ajax_batch_upload'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add Batch Processing submenu under the existing plugin menu
        add_submenu_page(
            'wordpress-content-migrator', // Parent slug
            'Batch Processing', // Page title
            'Batch Processing', // Menu title
            'manage_options', // Capability
            'wcm-batch-processing', // Menu slug
            array($this, 'render_batch_page') // Callback function
        );
    }
    
    /**
     * Register scripts and styles
     */
    public function register_assets($hook) {
        // Only load on our admin page
        if ($hook != 'wordpress-content-migrator_page_wcm-batch-processing') {
            return;
        }
        
        // Register and enqueue main style
        wp_register_style(
            'wcm-batch-style',
            plugin_dir_url(WCM_PLUGIN_FILE) . 'assets/css/batch-admin.css',
            array(),
            WCM_VERSION
        );
        wp_enqueue_style('wcm-batch-style');
        
        // Register and enqueue main script
        wp_register_script(
            'wcm-batch-script',
            plugin_dir_url(WCM_PLUGIN_FILE) . 'assets/js/batch-admin.js',
            array('jquery'),
            WCM_VERSION,
            true
        );
        
        // Localize script with data and nonce
        wp_localize_script('wcm-batch-script', 'wcm_batch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcm_ajax_nonce'),
            'i18n' => array(
                'confirm_cancel' => __('Are you sure you want to cancel this batch? This cannot be undone.', 'wordpress-content-migrator'),
                'processing' => __('Processing...', 'wordpress-content-migrator'),
                'completed' => __('Completed', 'wordpress-content-migrator'),
                'failed' => __('Failed', 'wordpress-content-migrator'),
                'skipped' => __('Skipped', 'wordpress-content-migrator'),
                'pending' => __('Pending', 'wordpress-content-migrator'),
                'cancelled' => __('Cancelled', 'wordpress-content-migrator'),
                'retry' => __('Retry', 'wordpress-content-migrator'),
                'view' => __('View', 'wordpress-content-migrator'),
                'error' => __('Error', 'wordpress-content-migrator')
            )
        ));
        wp_enqueue_script('wcm-batch-script');
    }
    
    /**
     * Render the batch processing page
     */
    public function render_batch_page() {
        ?>
        <div class="wrap wcm-batch-wrap">
            <h1><?php _e('Content Migrator - Batch Processing', 'wordpress-content-migrator'); ?></h1>
            
            <div class="wcm-batch-tabs">
                <button class="wcm-batch-tab-link active" data-tab="upload"><?php _e('Upload File', 'wordpress-content-migrator'); ?></button>
                <button class="wcm-batch-tab-link" data-tab="batches"><?php _e('Manage Batches', 'wordpress-content-migrator'); ?></button>
            </div>
            
            <div id="wcm-batch-tab-upload" class="wcm-batch-tab-content active">
                <div class="wcm-batch-upload-form">
                    <h2><?php _e('Upload CSV or Excel File', 'wordpress-content-migrator'); ?></h2>
                    <p><?php _e('Upload a CSV or Excel file with up to 1000 URLs to import content in batches. The process will continue in the background even if you leave this page.', 'wordpress-content-migrator'); ?></p>
                    
                    <form id="wcm-batch-upload-form" method="post" enctype="multipart/form-data">
                        <div class="form-field">
                            <label for="wcm-batch-file"><?php _e('Select File', 'wordpress-content-migrator'); ?></label>
                            <input type="file" name="wcm_batch_file" id="wcm-batch-file" accept=".csv,.xlsx" required>
                            <p class="description"><?php _e('Supported formats: CSV, XLSX', 'wordpress-content-migrator'); ?></p>
                        </div>
                        
                        <div class="form-field">
                            <label for="wcm-batch-overwrite">
                                <input type="checkbox" name="wcm_allow_overwrite" id="wcm-batch-overwrite" value="1">
                                <?php _e('Allow Overwrite', 'wordpress-content-migrator'); ?>
                            </label>
                            <p class="description"><?php _e('If checked, existing content with the same URL will be updated. Otherwise, it will be skipped.', 'wordpress-content-migrator'); ?></p>
                        </div>
                        
                        <div class="form-field">
                            <input type="hidden" name="action" value="wcm_batch_upload">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wcm_ajax_nonce'); ?>">
                            <button type="submit" class="button button-primary" id="wcm-batch-upload-button">
                                <?php _e('Upload & Process', 'wordpress-content-migrator'); ?>
                            </button>
                            <div id="wcm-batch-upload-progress" style="display: none;">
                                <div class="spinner is-active"></div>
                                <span><?php _e('Uploading file...', 'wordpress-content-migrator'); ?></span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div id="wcm-batch-upload-result" style="display: none;">
                    <div class="notice notice-success">
                        <p><?php _e('File uploaded successfully! A new batch has been created and processing has started.', 'wordpress-content-migrator'); ?></p>
                    </div>
                    <div class="wcm-batch-buttons">
                        <button class="button" id="wcm-batch-upload-another"><?php _e('Upload Another File', 'wordpress-content-migrator'); ?></button>
                        <button class="button button-primary" id="wcm-batch-view-status"><?php _e('View Batch Status', 'wordpress-content-migrator'); ?></button>
                    </div>
                </div>
                
                <div id="wcm-batch-upload-error" style="display: none;">
                    <div class="notice notice-error">
                        <p id="wcm-batch-upload-error-message"><?php _e('An error occurred during upload.', 'wordpress-content-migrator'); ?></p>
                    </div>
                    <button class="button" id="wcm-batch-try-again"><?php _e('Try Again', 'wordpress-content-migrator'); ?></button>
                </div>
            </div>
            
            <div id="wcm-batch-tab-batches" class="wcm-batch-tab-content">
                <div class="wcm-batch-list">
                    <h2><?php _e('Active Batches', 'wordpress-content-migrator'); ?></h2>
                    
                    <div id="wcm-batch-table-container">
                        <?php $this->render_batch_table(); ?>
                    </div>
                </div>
                
                <div id="wcm-batch-details" style="display: none;">
                    <div class="wcm-batch-details-header">
                        <h2><?php _e('Batch Details: ', 'wordpress-content-migrator'); ?><span id="wcm-batch-details-title"></span></h2>
                        <button class="button" id="wcm-batch-back-to-list"><?php _e('Back to List', 'wordpress-content-migrator'); ?></button>
                    </div>
                    
                    <div class="wcm-batch-details-summary">
                        <div class="wcm-batch-stats">
                            <div class="wcm-stat-box">
                                <div class="wcm-stat-label"><?php _e('Status', 'wordpress-content-migrator'); ?></div>
                                <div class="wcm-stat-value" id="wcm-batch-status"></div>
                            </div>
                            <div class="wcm-stat-box">
                                <div class="wcm-stat-label"><?php _e('Progress', 'wordpress-content-migrator'); ?></div>
                                <div class="wcm-stat-value" id="wcm-batch-progress">0%</div>
                                <div class="wcm-progress-bar">
                                    <div class="wcm-progress-bar-fill" id="wcm-progress-bar-fill" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div class="wcm-stat-box">
                                <div class="wcm-stat-label"><?php _e('Total Items', 'wordpress-content-migrator'); ?></div>
                                <div class="wcm-stat-value" id="wcm-batch-total">0</div>
                            </div>
                            <div class="wcm-stat-box">
                                <div class="wcm-stat-label"><?php _e('Processed', 'wordpress-content-migrator'); ?></div>
                                <div class="wcm-stat-value" id="wcm-batch-processed">0</div>
                            </div>
                            <div class="wcm-stat-box">
                                <div class="wcm-stat-label"><?php _e('Failed', 'wordpress-content-migrator'); ?></div>
                                <div class="wcm-stat-value" id="wcm-batch-failed">0</div>
                            </div>
                        </div>
                        
                        <div class="wcm-batch-actions">
                            <button class="button" id="wcm-batch-retry-all" style="display: none;"><?php _e('Retry Failed Items', 'wordpress-content-migrator'); ?></button>
                            <button class="button" id="wcm-batch-cancel" style="display: none;"><?php _e('Cancel Batch', 'wordpress-content-migrator'); ?></button>
                            <button class="button button-primary" id="wcm-batch-refresh"><?php _e('Refresh Status', 'wordpress-content-migrator'); ?></button>
                        </div>
                    </div>
                    
                    <div class="wcm-batch-items-list">
                        <h3><?php _e('Batch Items', 'wordpress-content-migrator'); ?></h3>
                        <div id="wcm-batch-items-table">
                            <!-- Items table will be loaded here via AJAX -->
                            <div class="spinner is-active"></div>
                            <p><?php _e('Loading batch items...', 'wordpress-content-migrator'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the batch table
     */
    public function render_batch_table() {
        $batches = $this->batch_processor->get_all_batches();
        
        if (empty($batches)) {
            echo '<div class="wcm-no-batches">';
            echo '<p>' . __('No batches found. Upload a file to create a new batch.', 'wordpress-content-migrator') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped wcm-batch-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('ID', 'wordpress-content-migrator') . '</th>';
        echo '<th>' . __('File Name', 'wordpress-content-migrator') . '</th>';
        echo '<th>' . __('Status', 'wordpress-content-migrator') . '</th>';
        echo '<th>' . __('Progress', 'wordpress-content-migrator') . '</th>';
        echo '<th>' . __('Created', 'wordpress-content-migrator') . '</th>';
        echo '<th>' . __('Actions', 'wordpress-content-migrator') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($batches as $batch) {
            $progress = 0;
            if ($batch['total_items'] > 0) {
                $progress = round(($batch['processed_items'] / $batch['total_items']) * 100);
            }
            
            $status_class = 'wcm-status-' . $batch['status'];
            $status_label = ucfirst($batch['status']);
            
            echo '<tr data-batch-id="' . esc_attr($batch['id']) . '">';
            echo '<td>' . esc_html($batch['id']) . '</td>';
            echo '<td>' . esc_html($batch['file_name']) . '</td>';
            echo '<td><span class="wcm-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td>';
            echo '<div class="wcm-progress-bar">';
            echo '<div class="wcm-progress-bar-fill" style="width: ' . esc_attr($progress) . '%;"></div>';
            echo '</div>';
            echo '<span class="wcm-progress-text">' . esc_html($progress) . '% (' . esc_html($batch['processed_items']) . '/' . esc_html($batch['total_items']) . ')</span>';
            echo '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($batch['created_at']))) . '</td>';
            echo '<td>';
            echo '<button class="button wcm-batch-view" data-batch-id="' . esc_attr($batch['id']) . '">' . __('View Details', 'wordpress-content-migrator') . '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * Handle batch file upload via AJAX
     */
    public function ajax_batch_upload() {
        // Check nonce
        check_ajax_referer('wcm_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wordpress-content-migrator')));
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['wcm_batch_file']) || $_FILES['wcm_batch_file']['error'] !== UPLOAD_ERR_OK) {
            $error = isset($_FILES['wcm_batch_file']) ? $this->get_upload_error_message($_FILES['wcm_batch_file']['error']) : __('No file uploaded', 'wordpress-content-migrator');
            wp_send_json_error(array('message' => $error));
        }
        
        // Get allow overwrite setting
        $allow_overwrite = isset($_POST['wcm_allow_overwrite']) && $_POST['wcm_allow_overwrite'] == '1';
        
        // Get uploaded file info
        $file = $_FILES['wcm_batch_file'];
        $file_name = sanitize_file_name($file['name']);
        $file_tmp = $file['tmp_name'];
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $wcm_dir = $upload_dir['basedir'] . '/wordpress-content-migrator';
        
        if (!file_exists($wcm_dir)) {
            wp_mkdir_p($wcm_dir);
            
            // Create .htaccess file to protect the directory
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($wcm_dir . '/.htaccess', $htaccess_content);
        }
        
        // Generate a unique filename for the uploaded file
        $unique_filename = uniqid() . '-' . $file_name;
        $file_path = $wcm_dir . '/' . $unique_filename;
        
        // Move the uploaded file to our directory
        if (!move_uploaded_file($file_tmp, $file_path)) {
            wp_send_json_error(array('message' => __('Failed to save uploaded file', 'wordpress-content-migrator')));
        }
        
        // Create a new batch
        $batch_id = $this->batch_processor->create_batch($file_path, $file_name, $allow_overwrite);
        
        if (is_wp_error($batch_id)) {
            // Clean up the file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            wp_send_json_error(array('message' => $batch_id->get_error_message()));
        }
        
        // Return success with batch ID
        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'message' => __('File uploaded successfully', 'wordpress-content-migrator')
        ));
    }
    
    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'wordpress-content-migrator');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'wordpress-content-migrator');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 'wordpress-content-migrator');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'wordpress-content-migrator');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'wordpress-content-migrator');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'wordpress-content-migrator');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload', 'wordpress-content-migrator');
            default:
                return __('Unknown upload error', 'wordpress-content-migrator');
        }
    }
} 