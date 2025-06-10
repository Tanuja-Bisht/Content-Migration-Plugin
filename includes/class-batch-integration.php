<?php
/**
 * Batch Integration for WordPress Content Migrator
 * 
 * Handles integration with the main plugin
 */
class Batch_Integration {
    
    /**
     * Initialize the batch integration
     */
    public function __construct() {
        // Define plugin file constant if not already defined
        if (!defined('WCM_PLUGIN_FILE')) {
            define('WCM_PLUGIN_FILE', plugin_dir_path(dirname(__FILE__)) . 'wordpress-content-migrator.php');
        }
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        $this->init_components();
        
        // Add AJAX action for batch list
        add_action('wp_ajax_wcm_get_batch_list', array($this, 'ajax_get_batch_list'));
        
        // Create folder structure if needed
        add_action('admin_init', array($this, 'create_folders'));
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-batch-processor.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-batch-admin.php';
    }
    
    /**
     * Initialize batch processing components
     */
    private function init_components() {
        // Initialize batch admin
        new Batch_Admin();
    }
    
    /**
     * Create required folders
     */
    public function create_folders() {
        // Create uploads directory for batch files
        $upload_dir = wp_upload_dir();
        $wcm_dir = $upload_dir['basedir'] . '/wordpress-content-migrator';
        
        if (!file_exists($wcm_dir)) {
            wp_mkdir_p($wcm_dir);
            
            // Create .htaccess file to protect the directory
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($wcm_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Get batch list via AJAX
     */
    public function ajax_get_batch_list() {
        // Check nonce
        check_ajax_referer('wcm_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Instantiate admin class to render the batch table
        $batch_admin = new Batch_Admin();
        
        // Start output buffering to capture the HTML
        ob_start();
        $batch_admin->render_batch_table();
        $html = ob_get_clean();
        
        // Return HTML
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Integration point: Add batch processing link to standard upload page
     */
    public static function add_batch_link_to_upload_page($html) {
        // Removed the batch processing banner as requested
        return $html;
    }
    
    /**
     * Integration point: Initialize batch processing from original import
     * 
     * This allows users to convert a regular import to a batch process
     */
    public static function maybe_convert_to_batch($file_path, $file_name, $allow_overwrite) {
        // Check if batch processing is requested
        if (isset($_POST['use_batch_processing']) && $_POST['use_batch_processing'] == '1') {
            $batch_processor = new Batch_Processor();
            $batch_id = $batch_processor->create_batch($file_path, $file_name, $allow_overwrite);
            
            if (is_wp_error($batch_id)) {
                return array(
                    'status' => 'error',
                    'message' => $batch_id->get_error_message()
                );
            }
            
            $batch_url = admin_url('admin.php?page=wcm-batch-processing&batch_id=' . $batch_id);
            
            return array(
                'status' => 'batch_created',
                'message' => __('File has been queued for batch processing.', 'wordpress-content-migrator'),
                'batch_url' => $batch_url,
                'batch_id' => $batch_id
            );
        }
        
        // Return false if not converting to batch
        return false;
    }
} 