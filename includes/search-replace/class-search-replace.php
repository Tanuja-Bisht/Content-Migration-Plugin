<?php
/**
 * WordPress Content Migrator - Search and Replace Module
 * 
 * Main class for handling search and replace operations.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Content_Migrator_Search_Replace
 * 
 * Handles search and replace operations across the WordPress database.
 */
class WP_Content_Migrator_Search_Replace {
    
    /**
     * The single instance of this class
     */
    private static $instance = null;
    
    /**
     * Database tables to search
     */
    private $tables = array();
    
    /**
     * Tables with primary key columns
     */
    private $primary_keys = array();
    
    /**
     * Log of operations
     */
    private $log = array();
    
    /**
     * Preview mode flag
     */
    private $preview_mode = false;
    
    /**
     * CSV data
     */
    private $replacements = array();
    
    /**
     * Logger instance
     */
    private $logger = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        // Increase time limits for potentially long-running operations
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300'); // 5 minutes
        @set_time_limit(300);
        
        // Initialize tables to search (default WP tables)
        global $wpdb;
        
        // Core content tables
        $this->tables = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->options,
            $wpdb->comments,
            $wpdb->commentmeta,
            $wpdb->terms,
            $wpdb->termmeta,
            $wpdb->term_taxonomy,
            $wpdb->term_relationships,
            $wpdb->users,
            $wpdb->usermeta,
            $wpdb->links
        );
        
        // Primary keys for each table
        $this->primary_keys = array(
            $wpdb->posts => 'ID',
            $wpdb->postmeta => 'meta_id',
            $wpdb->options => 'option_id',
            $wpdb->comments => 'comment_ID',
            $wpdb->commentmeta => 'meta_id',
            $wpdb->terms => 'term_id',
            $wpdb->termmeta => 'meta_id',
            $wpdb->term_taxonomy => 'term_taxonomy_id',
            $wpdb->users => 'ID',
            $wpdb->usermeta => 'umeta_id',
            $wpdb->links => 'link_id'
        );
        
        // Default to preview mode for safety
        $this->preview_mode = true;
        
        // Initialize logger
        require_once plugin_dir_path(__FILE__) . 'class-logger.php';
        $this->logger = new WP_Content_Migrator_Search_Replace_Logger();
        
        $this->log_debug('Search and Replace object initialized');
    }
    
    /**
     * Get the single instance of this class
     *
     * @return WP_Content_Migrator_Search_Replace
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            
            // Initialize the logger
            require_once plugin_dir_path(__FILE__) . 'class-logger.php';
            self::$instance->logger = new WP_Content_Migrator_Search_Replace_Logger();
            
            // Check operation mode based on form submission
            if (isset($_POST['wp_content_migrator_search_replace_submit']) && isset($_POST['operation_mode'])) {
                // Set preview mode based on operation mode selection
                self::$instance->set_preview_mode($_POST['operation_mode'] === 'preview');
                self::$instance->log_debug('Preview mode set to: ' . (self::$instance->preview_mode ? 'true' : 'false') . ' based on form submission');
            } else if (isset($_POST['execute_changes'])) {
                // Execute changes mode is always not preview
                self::$instance->set_preview_mode(false);
                self::$instance->log_debug('Preview mode set to false for execute_changes');
            } else {
                // Default to preview mode for safety
                self::$instance->set_preview_mode(true);
                self::$instance->log_debug('Preview mode set to true by default');
            }
            
            // If we're executing changes (not in preview mode), try to load replacements
            if (isset($_POST['execute_changes'])) {
                $replacements = get_transient('wp_content_migrator_search_replace_replacements');
                if ($replacements && is_array($replacements)) {
                    self::$instance->replacements = $replacements;
                    self::$instance->log_debug('Loaded ' . count($replacements) . ' replacements from transient in get_instance()');
                }
            }
        }
        return self::$instance;
    }
    
    /**
     * Set preview mode
     *
     * @param boolean $preview Whether to run in preview mode
     * @return void
     */
    public function set_preview_mode($preview) {
        $this->preview_mode = $preview;
    }
    
    /**
     * Set the replacement patterns
     *
     * @param array $replacements Array of replacement patterns
     * @return void
     */
    public function set_replacements($replacements) {
        // Validate replacements is an array
        if (!is_array($replacements)) {
            $this->log_debug('ERROR: Invalid replacements passed to set_replacements(). Expected array, got ' . gettype($replacements));
            $this->replacements = array();
            return;
        }
        
        // Validate each replacement is properly structured
        $validated_replacements = array();
        
        // Handle both array formats: direct array of search/replace pairs or result from validate_search_replace_csv
        if (isset($replacements['success']) && isset($replacements['data']) && is_array($replacements['data']) && isset($replacements['data']['rows'])) {
            // Format from validate_search_replace_csv
            foreach ($replacements['data']['rows'] as $row) {
                if (isset($row['search_for']) && isset($row['replace_with'])) {
                    $validated_replacements[] = array(
                        'search_for' => $row['search_for'],
                        'replace_with' => $row['replace_with']
                    );
                }
            }
        } else {
            // Direct format of search/replace pairs
            foreach ($replacements as $replacement) {
                if (is_array($replacement) && isset($replacement['search_for']) && isset($replacement['replace_with'])) {
                    $validated_replacements[] = $replacement;
                }
            }
        }
        
        $this->replacements = $validated_replacements;
        
        $this->log_debug('Set replacements: ' . count($this->replacements) . ' valid patterns');
        $this->log_debug('Replacements array: ' . print_r($this->replacements, true));
        
        // Store in transient for later use
        set_transient('wp_content_migrator_search_replace_replacements', $this->replacements, HOUR_IN_SECONDS);
    }
    
    /**
     * Get the replacement patterns
     *
     * @return array Replacement patterns
     */
    public function get_replacements() {
        return $this->replacements;
    }
    
    /**
     * Validate and process a CSV file
     *
     * @param string $file_path Path to the CSV file
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function process_csv_file($file_path) {
        // Validate the CSV file
        require_once plugin_dir_path(__FILE__) . 'class-csv-validator.php';
        $validator = new WP_Content_Migrator_CSV_Validator();
        $validation_result = $validator->validate_search_replace_csv($file_path);
        
        $this->log_debug('CSV Validation Result: ' . print_r($validation_result, true));
        
        if (!$validation_result['success']) {
            $this->logger->log_error('CSV validation failed: ' . $validation_result['message'], array(
                'file_path' => $file_path
            ));
            return new WP_Error('csv_validation_failed', $validation_result['message']);
        }
        
        if (!file_exists($file_path)) {
            $error = new WP_Error('file_not_found', 'CSV file not found.');
            $this->logger->log_error('CSV file not found', array('file_path' => $file_path));
            return $error;
        }
        
        // Open the CSV file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $error = new WP_Error('file_open_error', 'Unable to open CSV file.');
            $this->logger->log_error('Unable to open CSV file', array('file_path' => $file_path));
            return $error;
        }
        
        // Process the file
        $this->replacements = array();
        $line_num = 0;
        
        // Read and store CSV content for debugging
        $csv_content = file_get_contents($file_path);
        $this->log_debug('CSV file content: ' . $csv_content);
        $this->log_debug('CSV file size: ' . filesize($file_path) . ' bytes');
        $this->log_debug('CSV file character count: ' . strlen($csv_content));
        rewind($handle); // Reset file pointer after reading
        
        // Skip header row
        $header = fgetcsv($handle);
        $this->log_debug('CSV header row: ' . print_r($header, true));
        
        while (($data = fgetcsv($handle)) !== false) {
            $line_num++;
            
            $this->log_debug('Processing CSV line ' . $line_num . ': ' . print_r($data, true));
            
            // Skip empty rows
            if (empty($data) || count($data) < 2) {
                $this->log[] = array(
                    'type' => 'warning',
                    'message' => "Line {$line_num}: Skipped - Not enough columns"
                );
                $this->log_debug('Skipping line ' . $line_num . ' - Not enough columns');
                continue;
            }
            
            // Get the old and new content
            $old_content = trim($data[0]);
            $new_content = isset($data[1]) ? trim($data[1]) : '';
            
            // Skip if old content is empty
            if (empty($old_content)) {
                $this->log[] = array(
                    'type' => 'warning',
                    'message' => "Line {$line_num}: Skipped - Empty search value"
                );
                $this->log_debug('Skipping line ' . $line_num . ' - Empty search value');
                continue;
            }
            
            // Add to replacements
            $this->replacements[] = array(
                'search_for' => $old_content,
                'replace_with' => $new_content
            );
            
            $this->log_debug('Added replacement: search for "' . $old_content . '", replace with "' . $new_content . '"');
            // Check if the pattern contains special characters that might need escaping
            if (preg_match('/[\\^$.*+?()[\]{}|\/]/', $old_content)) {
                $this->log_debug('WARNING: Search pattern contains special characters that might need escaping: ' . $old_content);
            }
        }
        
        fclose($handle);
        
        // Check if we have any replacements
        if (empty($this->replacements)) {
            $error = new WP_Error('no_replacements', 'No valid replacements found in the CSV file.');
            $this->logger->log_error('No valid replacements found', array('file_path' => $file_path));
            return $error;
        }
        
        $this->log_debug('Total replacements found: ' . count($this->replacements));
        $this->log_debug('Replacements array: ' . print_r($this->replacements, true));
        
        $this->logger->log_info('CSV file processed successfully', array(
            'file_path' => $file_path,
            'replacement_count' => count($this->replacements)
        ));
        
        // Store replacements in transient for later use
        set_transient('wp_content_migrator_search_replace_replacements', $this->replacements, HOUR_IN_SECONDS);
        
        // Return the replacements array instead of just 'true'
        return $this->replacements;
    }
    
    /**
     * Run the search and replace operation
     *
     * @return array Results of the operation
     */
    public function run() {
        // Increase time limits for search and replace operations
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300'); // 5 minutes
        @set_time_limit(300);
        $this->log_debug('Time limits increased for run()');
        
        global $wpdb;
        
        // Set up results
        $results = array(
            'tables_processed' => 0,
            'rows_processed' => 0,
            'changes' => 0,
            'errors' => 0,
            'previews' => array(),
            'processed_tables' => array()
        );
        
        // Check if we have replacements
        if (empty($this->replacements) || !is_array($this->replacements)) {
            $this->log_debug('ERROR: No replacement patterns found');
            $results['errors'] = 1;
            return $results;
        }
        
        // Log the replacements being used
        $this->log_debug('Starting search and replace operation with ' . count($this->replacements) . ' patterns');
        $this->log_debug('Replacements: ' . print_r($this->replacements, true));
        
        // Get a list of all tables in the database
        $all_tables = $wpdb->get_col("SHOW TABLES");
        
        // Force use all tables if no tables specified
        if (empty($this->tables)) {
            $this->tables = $all_tables;
            $this->log_debug('Using all database tables: ' . print_r($this->tables, true));
        }
        
        // Process each table
        foreach ($this->tables as $table) {
            // Skip if table doesn't exist
            if (!in_array($table, $all_tables)) {
                $this->log_debug('Table does not exist: ' . $table);
                continue;
            }
            
            // Get columns in this table
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
            if (!$columns) {
                $results['errors']++;
                $this->log_debug('Failed to get columns for table: ' . $table);
                continue;
            }
            
            // Find text columns
            $text_columns = array();
            foreach ($columns as $column) {
                $type = strtolower($column['Type']);
                if (strpos($type, 'text') !== false || 
                    strpos($type, 'varchar') !== false || 
                    strpos($type, 'char') !== false || 
                    strpos($type, 'json') !== false || 
                    strpos($type, 'longtext') !== false) {
                    $text_columns[] = $column['Field'];
                }
            }
            
            // Skip if no text columns
            if (empty($text_columns)) {
                $this->log_debug('No text columns found in table: ' . $table);
                continue;
            }
            
            // Find primary key (fallback to first column if none)
            $primary_key = null;
            foreach ($columns as $column) {
                if ($column['Key'] == 'PRI') {
                    $primary_key = $column['Field'];
                    break;
                }
            }
            if (!$primary_key) {
                $primary_key = $columns[0]['Field'];
            }
            
            // First attempt: Use direct string comparison (LIKE)
            $direct_match_found = $this->search_table_direct($table, $text_columns, $primary_key, $results);
            
            // If no direct matches, try exact match with additional search strategies
            if (!$direct_match_found && $results['changes'] == 0) {
                $this->search_table_exact($table, $text_columns, $primary_key, $results);
            }
        }
        
        // Limit the number of previews to avoid overloading the page
        if (count($results['previews']) > 10) {
            $results['previews'] = array_slice($results['previews'], 0, 10);
            $results['previews_limited'] = true;
        }
        
        return $results;
    }
    
    /**
     * Search a table using direct string comparison with LIKE
     * 
     * @param string $table Table name
     * @param array $text_columns Array of text column names
     * @param string $primary_key Primary key column name
     * @param array &$results Results array to update
     * @return bool Whether any matches were found
     */
    private function search_table_direct($table, $text_columns, $primary_key, &$results) {
        global $wpdb;
        $match_found = false;
        
        // Get rows with any text column containing any of the search patterns
        $search_conditions = array();
        foreach ($this->replacements as $replacement) {
            if (!isset($replacement['search_for']) || empty($replacement['search_for'])) {
                continue;
            }
            
            foreach ($text_columns as $column) {
                $search_value = '%' . $wpdb->esc_like($replacement['search_for']) . '%';
                $search_conditions[] = $wpdb->prepare("{$column} LIKE %s", $search_value);
            }
        }
        
        // Skip if no search conditions
        if (empty($search_conditions)) {
            return false;
        }
        
        // Build the query
        $where_clause = '(' . implode(' OR ', $search_conditions) . ')';
        $query = "SELECT * FROM {$table} WHERE {$where_clause} LIMIT 1000";
        
        $this->log_debug('Executing search query: ' . $query);
        
        // Execute the query
        $rows = $wpdb->get_results($query, ARRAY_A);
        
        // Skip if no rows
        if (!$rows) {
            $this->log_debug('No matching rows found in table: ' . $table);
            return false;
        }
        
        $results['tables_processed']++;
        $results['rows_processed'] += count($rows);
        
        // Process each row
        foreach ($rows as $row) {
            $row_id = isset($row[$primary_key]) ? $row[$primary_key] : null;
            $update_needed = false;
            $updates = array();
            
            // Process each text column
            foreach ($text_columns as $column) {
                if (!isset($row[$column]) || !is_string($row[$column]) || $row[$column] === '') {
                    continue;
                }
                
                $original_value = $row[$column];
                $new_value = $original_value;
                
                // Process serialized data
                $serialized = false;
                if (wcm_safe_is_serialized($new_value)) {
                    $serialized = true;
                    $unserialized = wcm_safe_unserialize($new_value);
                    $new_unserialized = $this->process_serialized_data($unserialized);
                    
                    if ($unserialized !== $new_unserialized) {
                        $new_value = serialize($new_unserialized);
                        $update_needed = true;
                        $updates[$column] = $new_value;
                        $match_found = true;
                        
                        if ($this->preview_mode) {
                            $results['previews'][] = array(
                                'table' => $table,
                                'column' => $column,
                                'id' => $row_id,
                                'old' => $this->truncate_value($original_value),
                                'new' => $this->truncate_value($new_value),
                                'serialized' => true
                            );
                        }
                        $results['changes']++;
                    }
                } else {
                    // Standard string replacement
                    $value_changed = false;
                    
                    foreach ($this->replacements as $replacement) {
                        if (!isset($replacement['search_for']) || !isset($replacement['replace_with'])) {
                            continue;
                        }
                        
                        $search_for = $replacement['search_for'];
                        $replace_with = $replacement['replace_with'];
                        
                        // Try both case sensitive and insensitive
                        $case_sensitive_replaced = str_replace($search_for, $replace_with, $new_value);
                        $case_insensitive_replaced = str_ireplace($search_for, $replace_with, $new_value);
                        
                        // Use the one that made changes
                        if ($case_sensitive_replaced !== $new_value) {
                            $new_value = $case_sensitive_replaced;
                            $value_changed = true;
                            $this->log_debug("Found match in table {$table}, column {$column}, row {$row_id} - case sensitive");
                        } else if ($case_insensitive_replaced !== $new_value) {
                            $new_value = $case_insensitive_replaced;
                            $value_changed = true;
                            $this->log_debug("Found match in table {$table}, column {$column}, row {$row_id} - case insensitive");
                        }
                    }
                    
                    if ($value_changed) {
                        $update_needed = true;
                        $updates[$column] = $new_value;
                        $match_found = true;
                        
                        if ($this->preview_mode) {
                            $results['previews'][] = array(
                                'table' => $table,
                                'column' => $column,
                                'id' => $row_id,
                                'old' => $this->truncate_value($original_value),
                                'new' => $this->truncate_value($new_value),
                                'serialized' => false
                            );
                        }
                        $results['changes']++;
                    }
                }
            }
            
            // Update the database if changes were made and not in preview mode
            if ($update_needed && !$this->preview_mode && $primary_key && $row_id) {
                $where = array($primary_key => $row_id);
                $wpdb->update($table, $updates, $where);
                $this->log_debug("Updated table {$table}, row {$row_id} with changes");
            }
        }
        
        return $match_found;
    }
    
    /**
     * Search a table using exact string comparison
     * 
     * @param string $table Table name
     * @param array $text_columns Array of text column names
     * @param string $primary_key Primary key column name
     * @param array &$results Results array to update
     * @return bool Whether any matches were found
     */
    private function search_table_exact($table, $text_columns, $primary_key, &$results) {
        global $wpdb;
        $match_found = false;
        
        // Process all rows with text columns one by one
        foreach ($text_columns as $column) {
            // Fetch all rows with non-empty text in this column
            $query = "SELECT * FROM {$table} WHERE {$column} IS NOT NULL AND {$column} != '' LIMIT 1000";
            $rows = $wpdb->get_results($query, ARRAY_A);
            
            if (!$rows) {
                continue;
            }
            
            if (!isset($results['tables_processed']) || !in_array($table, $results['processed_tables'])) {
            $results['tables_processed']++;
                $results['processed_tables'][] = $table;
            }
            
            $results['rows_processed'] += count($rows);
            
            // Process each row
            foreach ($rows as $row) {
                $row_id = isset($row[$primary_key]) ? $row[$primary_key] : null;
                $original_value = $row[$column];
                $new_value = $original_value;
                $update_needed = false;
                
                // Skip non-string values
                if (!is_string($original_value)) {
                        continue;
                    }
                    
                // Process serialized data
                if (wcm_safe_is_serialized($original_value)) {
                    $unserialized = wcm_safe_unserialize($original_value);
                    $new_unserialized = $this->process_serialized_data($unserialized);
                        
                    if ($unserialized !== $new_unserialized) {
                        $new_value = serialize($new_unserialized);
                            $update_needed = true;
                        $match_found = true;
                            
                            if ($this->preview_mode) {
                                $results['previews'][] = array(
                                    'table' => $table,
                                    'column' => $column,
                                    'id' => $row_id,
                                'old' => $this->truncate_value($original_value),
                                    'new' => $this->truncate_value($new_value),
                                    'serialized' => true
                                );
                            }
                        
                        $results['changes']++;
                        }
                    } else {
                        // Standard string replacement
                    $value_changed = false;
                    
                        foreach ($this->replacements as $replacement) {
                        $search_for = $replacement['search_for'];
                        $replace_with = $replacement['replace_with'];
                            
                        // Check if the value contains the search string
                        if (strpos($new_value, $search_for) !== false || 
                            stripos($new_value, $search_for) !== false) {
                            
                            // Try both case sensitive and insensitive
                            $temp_value = str_replace($search_for, $replace_with, $new_value);
                            
                            if ($temp_value !== $new_value) {
                                $new_value = $temp_value;
                                $value_changed = true;
                            } else {
                                $temp_value = str_ireplace($search_for, $replace_with, $new_value);
                                if ($temp_value !== $new_value) {
                                    $new_value = $temp_value;
                                    $value_changed = true;
                                }
                            }
                            }
                        }
                        
                    if ($value_changed) {
                            $update_needed = true;
                        $match_found = true;
                            
                            if ($this->preview_mode) {
                                $results['previews'][] = array(
                                    'table' => $table,
                                    'column' => $column,
                                    'id' => $row_id,
                                'old' => $this->truncate_value($original_value),
                                    'new' => $this->truncate_value($new_value),
                                    'serialized' => false
                                );
                        }
                        
                        $results['changes']++;
                    }
                }
                
                // Update the database if changes were made and not in preview mode
                if ($update_needed && !$this->preview_mode && $primary_key && $row_id) {
                    $wpdb->update(
                        $table, 
                        array($column => $new_value), 
                        array($primary_key => $row_id)
                    );
                }
            }
        }
        
        return $match_found;
    }
    
    /**
     * Process serialized data recursively
     *
     * @param mixed $data Serialized data to process
     * @return mixed Processed data
     */
    private function process_serialized_data($data) {
        if (is_string($data)) {
            $replaced = false;
            $new_value = $data;
            
            if (is_array($this->replacements)) {
            foreach ($this->replacements as $replacement) {
                    if (!is_array($replacement) || !isset($replacement['search_for']) || !isset($replacement['replace_with'])) {
                        continue;
                    }
                    $old_content = $replacement['search_for'];
                    $new_content = $replacement['replace_with'];
                
                    // Handle case sensitivity
                    $case_sensitive = isset($_POST['case_sensitive']) && $_POST['case_sensitive'] == 1;
                    
                    if (!$case_sensitive) {
                        // Case-insensitive check if string contains the search term
                        if (stripos($new_value, $old_content) !== false) {
                            $new_value = str_ireplace($old_content, $new_content, $new_value);
                            $replaced = true;
                            
                            $this->log_debug("Serialized data replaced: '$old_content' with '$new_content'");
                        }
                    } else {
                        // Case-sensitive check
                if (strpos($new_value, $old_content) !== false) {
                    $new_value = str_replace($old_content, $new_content, $new_value);
                    $replaced = true;
                            
                            $this->log_debug("Serialized data replaced (case-sensitive): '$old_content' with '$new_content'");
                        }
                    }
                }
            }
            
            return $new_value;
        } elseif (is_array($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = $this->process_serialized_data($value);
            }
            return $result;
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                $data->$key = $this->process_serialized_data($value);
            }
            return $data;
        }
        
        return $data;
    }
    
    /**
     * Get the operation log
     *
     * @return array Log entries
     */
    public function get_log() {
        return $this->log;
    }
    
    /**
     * Truncate a string value for display
     *
     * @param string $value Value to truncate
     * @param int $length Maximum length
     * @return string Truncated value
     */
    private function truncate_value($value, $length = 100) {
        if (strlen($value) <= $length) {
            return $value;
        }
        
        return substr($value, 0, $length) . '...';
    }
    
    /**
     * Add a custom table to search
     *
     * @param string $table Table name
     * @param string $primary_key Primary key column
     * @return void
     */
    public function add_table($table, $primary_key = 'ID') {
        if (!in_array($table, $this->tables)) {
            $this->tables[] = $table;
            $this->primary_keys[$table] = $primary_key;
        }
    }
    
    /**
     * Remove a table from the search
     *
     * @param string $table Table name
     * @return void
     */
    public function remove_table($table) {
        $key = array_search($table, $this->tables);
        if ($key !== false) {
            unset($this->tables[$key]);
            $this->tables = array_values($this->tables); // Reindex array
            
            if (isset($this->primary_keys[$table])) {
                unset($this->primary_keys[$table]);
            }
        }
    }
    
    /**
     * Get preview results for the search and replace operation
     * 
     * @return array Preview results
     */
    public function get_preview_results() {
        // Ensure we're in preview mode
        $this->preview_mode = true;
        
        // Check if replacements exist and are valid
        if (!isset($this->replacements) || !is_array($this->replacements) || empty($this->replacements)) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('ERROR: No valid replacements found in get_preview_results()');
            }
            return array(
                'tables_processed' => 0,
                'rows_processed' => 0,
                'changes' => 0,
                'errors' => 1,
                'previews' => array(),
                'details' => array()
            );
        }
        
        // Run the search and replace operation
        $results = $this->run();
        
        // Log the preview
        if ($this->logger) {
            $this->logger->log_preview($results);
        }
        
        return $results;
    }
    
    /**
     * Execute the search and replace operation
     * 
     * @return array Results of the operation
     */
    public function execute_replacements() {
        // Ensure we're not in preview mode
        $this->preview_mode = false;
        
        // Increase time limits for replacement execution
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300'); // 5 minutes
        @set_time_limit(300);
        $this->log_debug('Time limits increased for execute_replacements()');
        
        // Check if replacements exist and are valid
        if (!isset($this->replacements) || !is_array($this->replacements) || empty($this->replacements)) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('ERROR: No valid replacements found in execute_replacements()');
            }
            return array(
                'tables_processed' => 0,
                'rows_processed' => 0,
                'changes' => 0,
                'errors' => 1,
                'previews' => array(),
                'details' => array()
            );
        }
        
        // Run the search and replace operation
        $results = $this->run();
        
        // Log the operation
        if ($this->logger) {
            $this->logger->log_operation($results);
        }
        
        return $results;
    }
    
    /**
     * Log a debug message to the WordPress debug log if enabled
     * 
     * @param string $message The message to log
     * @return void
     */
    private function log_debug($message) {
        if (function_exists('wcm_debug_log')) {
            wcm_debug_log($message);
        } else if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('WCM S&R: ' . $message);
        }
    }
} 