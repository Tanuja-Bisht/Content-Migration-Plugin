<?php
/**
 * Logger Class for Search and Replace
 * 
 * Provides logging functionality for the Search and Replace operations.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Logger class for Search and Replace operations
 */
class WP_Content_Migrator_Search_Replace_Logger {
    
    /**
     * Log entries
     *
     * @var array
     */
    private $log = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->log = array();
    }
    
    /**
     * Log a message
     *
     * @param string $level Log level (info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function log($level, $message, $context = array()) {
        $this->log[] = array(
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'time' => date('Y-m-d H:i:s')
        );
        
        // Also log to PHP error log for debugging
        error_log("[$level] $message");
    }
    
    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function log_info($message, $context = array()) {
        $this->log('info', $message, $context);
        }
        
    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function log_warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function log_error($message, $context = array()) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Get all log entries
     *
     * @return array Log entries
     */
    public function get_log() {
        return $this->log;
    }
    
    /**
     * Get log entries of a specific level
     *
     * @param string $level Log level (info, warning, error)
     * @return array Filtered log entries
     */
    public function get_log_by_level($level) {
        return array_filter($this->log, function($entry) use ($level) {
            return $entry['level'] === $level;
        });
    }
    
    /**
     * Clear the log
     *
     * @return void
     */
    public function clear_log() {
        $this->log = array();
    }
    
    /**
     * Export the log as a string
     *
     * @return string Log as a string
     */
    public function export_log() {
        $output = '';
        foreach ($this->log as $entry) {
            $output .= '[' . $entry['time'] . '] [' . strtoupper($entry['level']) . '] ' . $entry['message'] . "\n";
        }
        return $output;
    }
    
    /**
     * Log preview results
     *
     * @param array $results Results of preview operation
     * @return void
     */
    public function log_preview($results) {
        $this->log_info('Search and replace preview completed', array(
                'tables_processed' => $results['tables_processed'],
                'rows_processed' => $results['rows_processed'],
                'potential_changes' => $results['changes']
        ));
    }
    
    /**
     * Log operation results
     *
     * @param array $results Results of search and replace operation
     * @return void
     */
    public function log_operation($results) {
        $this->log_info('Search and replace operation completed', array(
            'tables_processed' => $results['tables_processed'],
            'rows_processed' => $results['rows_processed'],
            'changes_made' => $results['changes'],
            'errors' => $results['errors']
        ));
    }
} 