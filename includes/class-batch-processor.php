<?php
/**
 * Batch Processor for WordPress Content Migrator
 * 
 * Handles the batch processing of large CSV/Excel files
 */
class Batch_Processor {

    // Table names
    private $batches_table;
    private $batch_items_table;
    
    /**
     * Initialize the batch processor
     */
    public function __construct() {
        global $wpdb;
        $this->batches_table = $wpdb->prefix . 'wcm_batches';
        $this->batch_items_table = $wpdb->prefix . 'wcm_batch_items';
        
        // Register activation hook for table creation
        register_activation_hook(WCM_PLUGIN_FILE, array($this, 'create_database_tables'));
        
        // Setup hooks for background processing
        add_action('wcm_process_batch_item', array($this, 'process_batch_item'), 10, 2);
        add_action('wcm_process_next_batch', array($this, 'process_next_batch'), 10, 1);
        
        // Ajax handlers
        add_action('wp_ajax_wcm_get_batch_status', array($this, 'ajax_get_batch_status'));
        add_action('wp_ajax_wcm_retry_failed_item', array($this, 'ajax_retry_failed_item'));
        add_action('wp_ajax_wcm_retry_batch', array($this, 'ajax_retry_batch'));
        add_action('wp_ajax_wcm_cancel_batch', array($this, 'ajax_cancel_batch'));
    }
    
    /**
     * Create the necessary database tables
     */
    public function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Batches table - stores overall batch information
        $sql = "CREATE TABLE IF NOT EXISTS {$this->batches_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_path varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            failed_items int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            allow_overwrite tinyint(1) NOT NULL DEFAULT 0,
            started_at datetime NULL,
            completed_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Batch items table - stores individual item status
        $sql .= "CREATE TABLE IF NOT EXISTS {$this->batch_items_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) NOT NULL,
            row_index int(11) NOT NULL,
            row_data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            post_id bigint(20) DEFAULT NULL,
            result longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            attempts int(2) NOT NULL DEFAULT 0,
            started_at datetime NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            KEY batch_id (batch_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set version
        update_option('wcm_db_version', '1.0');
    }
    
    /**
     * Create a new batch from a file
     * 
     * @param string $file_path Path to the CSV/Excel file
     * @param string $file_name Original file name
     * @param bool $allow_overwrite Whether to allow overwriting existing content
     * @return int|WP_Error Batch ID or WP_Error on failure
     */
    public function create_batch($file_path, $file_name, $allow_overwrite = false) {
        global $wpdb;
        
        // Insert batch record
        $result = $wpdb->insert(
            $this->batches_table,
            array(
                'file_path' => $file_path,
                'file_name' => $file_name,
                'status' => 'pending',
                'allow_overwrite' => $allow_overwrite ? 1 : 0,
                'created_at' => current_time('mysql')
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create batch record: ' . $wpdb->last_error);
        }
        
        $batch_id = $wpdb->insert_id;
        
        // Now extract rows from the file and create batch items
        $excel_processor = new Excel_Processor();
        $rows = $excel_processor->extract_rows_from_file($file_path);
        
        if (is_wp_error($rows)) {
            $wpdb->delete($this->batches_table, array('id' => $batch_id));
            return $rows;
        }
        
        $total_items = count($rows);
        
        // Update batch with total count
        $wpdb->update(
            $this->batches_table,
            array('total_items' => $total_items),
            array('id' => $batch_id)
        );
        
        // Create batch items
        foreach ($rows as $index => $row_data) {
            $wpdb->insert(
                $this->batch_items_table,
                array(
                    'batch_id' => $batch_id,
                    'row_index' => $index,
                    'row_data' => json_encode($row_data),
                    'status' => 'pending'
                )
            );
        }
        
        // Schedule the first batch processing
        $this->schedule_batch_processing($batch_id);
        
        return $batch_id;
    }
    
    /**
     * Schedule batch processing
     * 
     * @param int $batch_id Batch ID
     */
    private function schedule_batch_processing($batch_id) {
        if (!wp_next_scheduled('wcm_process_next_batch', array($batch_id))) {
            wp_schedule_single_event(time(), 'wcm_process_next_batch', array($batch_id));
        }
    }
    
    /**
     * Process the next batch of items
     * 
     * @param int $batch_id Batch ID
     */
    public function process_next_batch($batch_id) {
        global $wpdb;
        
        // Get batch information
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->batches_table} WHERE id = %d",
            $batch_id
        ));
        
        if (!$batch) {
            error_log("Batch not found: {$batch_id}");
            return;
        }
        
        // Skip if batch is already completed or cancelled
        if (in_array($batch->status, array('completed', 'cancelled'))) {
            error_log("Batch {$batch_id} is already {$batch->status}");
            return;
        }
        
        // Update batch status to processing if it's pending
        if ($batch->status === 'pending') {
            $wpdb->update(
                $this->batches_table,
                array(
                    'status' => 'processing',
                    'started_at' => current_time('mysql')
                ),
                array('id' => $batch_id)
            );
        }
        
        // Get next batch of pending items (25 at a time)
        $batch_size = 25;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->batch_items_table} 
            WHERE batch_id = %d AND status = 'pending'
            ORDER BY id ASC
            LIMIT %d",
            $batch_id,
            $batch_size
        ));
        
        // If no more pending items, check if batch is complete
        if (empty($items)) {
            $this->maybe_complete_batch($batch_id);
            return;
        }
        
        // Process each item
        foreach ($items as $item) {
            // Schedule individual item processing
            wp_schedule_single_event(
                time(),
                'wcm_process_batch_item',
                array($batch_id, $item->id)
            );
        }
        
        // Schedule next batch
        wp_schedule_single_event(
            time() + 60, // Wait 1 minute before processing next batch
            'wcm_process_next_batch',
            array($batch_id)
        );
    }
    
    /**
     * Process a single batch item
     * 
     * @param int $batch_id Batch ID
     * @param int $item_id Item ID
     */
    public function process_batch_item($batch_id, $item_id) {
        global $wpdb;
        
        // Get item information
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->batch_items_table} WHERE id = %d AND batch_id = %d",
            $item_id,
            $batch_id
        ));
        
        if (!$item) {
            error_log("Batch item not found: {$item_id} in batch {$batch_id}");
            return;
        }
        
        // Get batch information
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->batches_table} WHERE id = %d",
            $batch_id
        ));
        
        if (!$batch) {
            error_log("Batch not found: {$batch_id}");
            return;
        }
        
        // Skip if batch is cancelled
        if ($batch->status === 'cancelled') {
            return;
        }
        
        // Update item status to processing
        $wpdb->update(
            $this->batch_items_table,
            array(
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $item->attempts + 1
            ),
            array('id' => $item_id)
        );
        
        try {
            // Decode row data
            $row_data = json_decode($item->row_data, true);
            if (!is_array($row_data)) {
                throw new Exception("Invalid row data for batch item {$item_id}");
            }
            
            // Process the row
            $excel_processor = new Excel_Processor();
            $result = $excel_processor->process_row($row_data, (bool)$batch->allow_overwrite, true);
            
            // Update item with result
            $status = ($result['status'] === 'success' || $result['status'] === 'skipped') 
                ? $result['status'] 
                : 'failed';
                
            $post_id = isset($result['post_id']) ? $result['post_id'] : null;
            
            $wpdb->update(
                $this->batch_items_table,
                array(
                    'status' => $status,
                    'post_id' => $post_id,
                    'result' => json_encode($result),
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $item_id)
            );
            
            // Update batch processed count
            $this->update_batch_counts($batch_id);
            
        } catch (Exception $e) {
            // Update item with error
            $wpdb->update(
                $this->batch_items_table,
                array(
                    'status' => ($item->attempts >= 3) ? 'failed' : 'pending',
                    'error_message' => $e->getMessage(),
                    'completed_at' => ($item->attempts >= 3) ? current_time('mysql') : null
                ),
                array('id' => $item_id)
            );
            
            error_log("Error processing batch item {$item_id} in batch {$batch_id}: " . $e->getMessage());
            
            // Update batch counts if we're marking as failed
            if ($item->attempts >= 3) {
                $this->update_batch_counts($batch_id);
            }
        }
        
        // Check if batch is complete
        $this->maybe_complete_batch($batch_id);
    }
    
    /**
     * Update batch counts
     * 
     * @param int $batch_id Batch ID
     */
    private function update_batch_counts($batch_id) {
        global $wpdb;
        
        // Get current counts
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' OR status = 'skipped' THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$this->batch_items_table}
            WHERE batch_id = %d",
            $batch_id
        ));
        
        // Update batch
        $wpdb->update(
            $this->batches_table,
            array(
                'processed_items' => (int)$counts->processed,
                'failed_items' => (int)$counts->failed
            ),
            array('id' => $batch_id)
        );
    }
    
    /**
     * Check if batch is complete and update status
     * 
     * @param int $batch_id Batch ID
     */
    private function maybe_complete_batch($batch_id) {
        global $wpdb;
        
        // Check if all items are processed or failed
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->batch_items_table} 
            WHERE batch_id = %d AND status IN ('pending', 'processing')",
            $batch_id
        ));
        
        if ($pending_count === '0') {
            // Update batch status to completed
            $wpdb->update(
                $this->batches_table,
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $batch_id)
            );
            
            error_log("Batch {$batch_id} completed");
        }
    }
    
    /**
     * Get batch status (used by AJAX)
     */
    public function ajax_get_batch_status() {
        // Check nonce
        check_ajax_referer('wcm_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
        
        if (!$batch_id) {
            wp_send_json_error('Invalid batch ID');
        }
        
        $status = $this->get_batch_status($batch_id);
        wp_send_json_success($status);
    }
    
    /**
     * Get batch status details
     * 
     * @param int $batch_id Batch ID
     * @return array Batch status details
     */
    public function get_batch_status($batch_id) {
        global $wpdb;
        
        // Get batch information
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->batches_table} WHERE id = %d",
            $batch_id
        ), ARRAY_A);
        
        if (!$batch) {
            return array('error' => 'Batch not found');
        }
        
        // Get item statistics
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_items,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_items,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_items,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_items,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_items
            FROM {$this->batch_items_table}
            WHERE batch_id = %d",
            $batch_id
        ), ARRAY_A);
        
        // Get items (optional, only return recent for UI updates)
        $items_limit = isset($_POST['items_limit']) ? intval($_POST['items_limit']) : 50;
        $items_offset = isset($_POST['items_offset']) ? intval($_POST['items_offset']) : 0;
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, row_index, status, post_id, error_message, attempts, started_at, completed_at 
            FROM {$this->batch_items_table} 
            WHERE batch_id = %d
            ORDER BY id ASC
            LIMIT %d, %d",
            $batch_id,
            $items_offset,
            $items_limit
        ), ARRAY_A);
        
        return array(
            'batch' => $batch,
            'stats' => $stats,
            'items' => $items
        );
    }
    
    /**
     * Retry a failed batch item
     */
    public function ajax_retry_failed_item() {
        // Check nonce
        check_ajax_referer('wcm_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error('Invalid item ID');
        }
        
        global $wpdb;
        
        // Reset item status and attempts
        $updated = $wpdb->update(
            $this->batch_items_table,
            array(
                'status' => 'pending',
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null
            ),
            array('id' => $item_id)
        );
        
        if ($updated === false) {
            wp_send_json_error('Failed to reset item: ' . $wpdb->last_error);
        }
        
        // Get batch ID
        $batch_id = $wpdb->get_var($wpdb->prepare(
            "SELECT batch_id FROM {$this->batch_items_table} WHERE id = %d",
            $item_id
        ));
        
        // Update batch status if needed
        $wpdb->update(
            $this->batches_table,
            array('status' => 'processing'),
            array('id' => $batch_id, 'status' => 'completed')
        );
        
        // Schedule immediate processing
        wp_schedule_single_event(
            time(),
            'wcm_process_batch_item',
            array($batch_id, $item_id)
        );
        
        wp_send_json_success();
    }
    
    /**
     * Retry all failed items in a batch
     */
    public function ajax_retry_batch() {
        // Check nonce
        check_ajax_referer('wcm_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
        
        if (!$batch_id) {
            wp_send_json_error('Invalid batch ID');
        }
        
        global $wpdb;
        
        // Reset all failed items
        $wpdb->update(
            $this->batch_items_table,
            array(
                'status' => 'pending',
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null
            ),
            array(
                'batch_id' => $batch_id,
                'status' => 'failed'
            )
        );
        
        // Update batch status
        $wpdb->update(
            $this->batches_table,
            array('status' => 'processing'),
            array('id' => $batch_id)
        );
        
        // Schedule next batch processing
        $this->schedule_batch_processing($batch_id);
        
        wp_send_json_success();
    }
    
    /**
     * Cancel a batch
     */
    public function ajax_cancel_batch() {
        // Check nonce
        check_ajax_referer('wcm_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $batch_id = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
        
        if (!$batch_id) {
            wp_send_json_error('Invalid batch ID');
        }
        
        global $wpdb;
        
        // Update batch status
        $wpdb->update(
            $this->batches_table,
            array('status' => 'cancelled'),
            array('id' => $batch_id)
        );
        
        wp_send_json_success();
    }
    
    /**
     * Get all batches (for admin panel)
     * 
     * @return array Array of batches
     */
    public function get_all_batches() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->batches_table} ORDER BY id DESC",
            ARRAY_A
        );
    }
} 