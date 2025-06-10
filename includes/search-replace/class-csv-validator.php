<?php
/**
 * CSV Validator Class
 * 
 * Validates CSV files for the Search and Replace functionality.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class WP_Content_Migrator_CSV_Validator
 * 
 * Validates CSV files for the Search and Replace functionality.
 */
class WP_Content_Migrator_CSV_Validator {
    
    /**
     * Validate a CSV file for search and replace operations
     * 
     * @param string $file_path Path to the CSV file
     * @return array [success => bool, message => string, data => array|null]
     */
    public function validate_search_replace_csv($file_path) {
        // Debug log this function call
        if (function_exists('wcm_debug_log')) {
            wcm_debug_log('Validating CSV file: ' . $file_path);
        }
        
        // Check if file exists
        if (!file_exists($file_path)) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('File not found: ' . $file_path);
            }
            return [
                'success' => false, 
                'message' => 'File not found: ' . $file_path,
                'data' => null
            ];
        }
        
        // Read the file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('Could not open file for reading');
            }
            return [
                'success' => false, 
                'message' => 'Could not open the file for reading.',
                'data' => null
            ];
        }
        
        // Read and validate header row
        $header = fgetcsv($handle);
        if (!$header || count($header) < 2) {
            fclose($handle);
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('CSV file must have at least two columns. Found: ' . print_r($header, true));
            }
            return [
                'success' => false, 
                'message' => 'CSV file must have at least two columns.',
                'data' => null
            ];
        }
        
        // Check expected header format
        $expected_headers = ['search_for', 'replace_with'];
        $normalized_header = array_map('strtolower', array_map('trim', $header));
        
        // Log header information
        if (function_exists('wcm_debug_log')) {
            wcm_debug_log('CSV header: ' . print_r($header, true));
            wcm_debug_log('Normalized header: ' . print_r($normalized_header, true));
        }
        
        $header_valid = true;
        
        // Alternate header formats
        $alternate_headers = [
            ['old_content', 'new_content'],
            ['old', 'new'],
            ['find', 'replace'],
            ['search', 'replace']
        ];
        
        // Check if header matches expected or alternate formats
        if ($normalized_header[0] !== $expected_headers[0] || $normalized_header[1] !== $expected_headers[1]) {
            $header_valid = false;
            foreach ($alternate_headers as $alt_header) {
                if ($normalized_header[0] === $alt_header[0] && $normalized_header[1] === $alt_header[1]) {
                    $header_valid = true;
                    break;
                }
            }
        }
        
        // If the header format doesn't match exactly, but has at least 2 columns,
        // we'll just accept it and use the first two columns as search/replace
        if (!$header_valid && count($normalized_header) >= 2) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('Header format is not standard, but has at least 2 columns. Will use first two columns.');
            }
            $header_valid = true;
        }
        
        if (!$header_valid) {
            fclose($handle);
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('Invalid header format. Expected "search_for" and "replace_with" columns.');
            }
            return [
                'success' => false, 
                'message' => 'CSV header must contain "search_for" and "replace_with" columns (or similar).',
                'data' => null
            ];
        }
        
        // Read all rows
        $rows = [];
        $line_number = 1; // Start after header
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== false) {
            $line_number++;
            
            // Log row data
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('CSV line ' . $line_number . ': ' . print_r($data, true));
            }
            
            // Validate row has at least 2 columns
            if (count($data) < 2) {
                $errors[] = "Line {$line_number}: Not enough columns. Required 2, found " . count($data);
                if (function_exists('wcm_debug_log')) {
                    wcm_debug_log("Line {$line_number}: Not enough columns. Required 2, found " . count($data));
                }
                continue;
            }
            
            // Trim values
            $search_for = trim($data[0]);
            $replace_with = trim($data[1]);
            
            // Check if search pattern is empty
            if (empty($search_for)) {
                $errors[] = "Line {$line_number}: Search pattern cannot be empty.";
                if (function_exists('wcm_debug_log')) {
                    wcm_debug_log("Line {$line_number}: Search pattern cannot be empty.");
                }
                continue;
            }
            
            // Store valid row
            $rows[] = [
                'search_for' => $search_for,
                'replace_with' => $replace_with
            ];
            
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log("Line {$line_number}: Valid replacement - Search for: '$search_for', Replace with: '$replace_with'");
            }
        }
        
        fclose($handle);
        
        // Check if we have any valid rows
        if (empty($rows)) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('No valid search/replace pairs found in the CSV file.');
            }
            return [
                'success' => false, 
                'message' => 'No valid search/replace pairs found in the CSV file.',
                'data' => null
            ];
        }
        
        // Return results
        $result = [
            'success' => true,
            'message' => count($errors) > 0 
                ? 'File validated with ' . count($errors) . ' warnings.' 
                : 'File validated successfully.',
            'data' => [
                'rows' => $rows,
                'errors' => $errors,
                'count' => count($rows)
            ]
        ];
        
        if (function_exists('wcm_debug_log')) {
            wcm_debug_log('CSV validation result: ' . print_r($result, true));
        }
        
        return $result;
    }
    
    /**
     * Validate a CSV string
     * 
     * @param string $csv_content CSV content as a string
     * @return array [success => bool, message => string, data => array|null]
     */
    public function validate_csv_string($csv_content) {
        // Create a temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'csv_validate_');
        file_put_contents($temp_file, $csv_content);
        
        // Validate the file
        $result = $this->validate_search_replace_csv($temp_file);
        
        // Clean up
        unlink($temp_file);
        
        return $result;
    }
    
    /**
     * Validates a CSV file for Search and Replace operations
     *
     * @param string $file_path Path to the CSV file
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found.');
        }
        
        // Check if file is readable
        if (!is_readable($file_path)) {
            return new WP_Error('file_not_readable', 'Cannot read CSV file. Check file permissions.');
        }
        
        // Check file size
        $file_size = filesize($file_path);
        if ($file_size === 0) {
            return new WP_Error('empty_file', 'The CSV file is empty.');
        }
        
        // Max file size: 10MB
        $max_size = 10 * 1024 * 1024;
        if ($file_size > $max_size) {
            return new WP_Error('file_too_large', 'The CSV file exceeds the maximum allowed size (10MB).');
        }
        
        // Open the file and validate structure
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_open_error', 'Unable to open CSV file.');
        }
        
        // Check the header row
        $header = fgetcsv($handle);
        if (!$header || count($header) < 2) {
            fclose($handle);
            return new WP_Error('invalid_header', 'The CSV file does not have the required columns. Expected: old_content, new_content');
        }
        
        // Normalize header column names
        $header = array_map('strtolower', array_map('trim', $header));
        
        // Check if required columns exist
        if (!in_array('old_content', $header) && !in_array('new_content', $header) && 
            !(count($header) === 2 && $header[0] !== $header[1])) {
            fclose($handle);
            return new WP_Error(
                'missing_columns', 
                'The CSV file must have "old_content" and "new_content" columns, or at least two distinct columns.'
            );
        }
        
        // Check for at least one valid row of data
        $data_row = fgetcsv($handle);
        if (!$data_row || count($data_row) < 2) {
            fclose($handle);
            return new WP_Error('no_data', 'The CSV file does not contain any valid data rows.');
        }
        
        // Basic validation of the first row
        if (empty(trim($data_row[0]))) {
            fclose($handle);
            return new WP_Error('empty_search', 'The first search term is empty. All search terms must contain content.');
        }
        
        // Close the file
        fclose($handle);
        
        return true;
    }
    
    /**
     * Get column indexes for old and new content
     *
     * @param string $file_path Path to the CSV file
     * @return array|WP_Error Array with 'old' and 'new' indexes, or WP_Error
     */
    public static function get_column_indexes($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found.');
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_open_error', 'Unable to open CSV file.');
        }
        
        // Get the header row
        $header = fgetcsv($handle);
        fclose($handle);
        
        if (!$header || count($header) < 2) {
            return new WP_Error('invalid_header', 'The CSV file does not have the required columns.');
        }
        
        // Normalize header column names
        $normalized_header = array_map('strtolower', array_map('trim', $header));
        
        // Find column indexes
        $old_index = array_search('old_content', $normalized_header);
        $new_index = array_search('new_content', $normalized_header);
        
        // If standard column names not found, use first two columns
        if ($old_index === false || $new_index === false) {
            $old_index = 0;
            $new_index = 1;
        }
        
        return array(
            'old' => $old_index,
            'new' => $new_index
        );
    }
    
    /**
     * Count the number of valid rows in a CSV file
     *
     * @param string $file_path Path to the CSV file
     * @return int|WP_Error Number of valid rows, or WP_Error
     */
    public static function count_valid_rows($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found.');
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_open_error', 'Unable to open CSV file.');
        }
        
        // Skip header row
        fgetcsv($handle);
        
        // Count valid rows
        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 2 && !empty(trim($data[0]))) {
                $count++;
            }
        }
        
        fclose($handle);
        
        return $count;
    }

    /**
     * Validate a CSV file
     * 
     * @param string $file_path Path to the CSV file
     * @return array|WP_Error Validation result or error
     */
    public function validate_file($file_path) {
        // Log validation attempt
        if (function_exists('wcm_debug_log')) {
            wcm_debug_log('Validating CSV file at: ' . $file_path);
        }
        
        // Check if file exists
        if (!file_exists($file_path)) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('CSV validation failed: File not found - ' . $file_path);
            }
            return new WP_Error('file_not_found', 'CSV file not found.');
        }
        
        // Run full validation using the existing method
        $result = $this->validate_search_replace_csv($file_path);
        
        if (!$result['success']) {
            if (function_exists('wcm_debug_log')) {
                wcm_debug_log('CSV validation failed: ' . $result['message']);
            }
            return new WP_Error('validation_failed', $result['message']);
        }
        
        if (function_exists('wcm_debug_log')) {
            wcm_debug_log('CSV validation successful - ' . count($result['data']['rows']) . ' replacements found');
        }
        
        // Return successful result
        return $result['data']['rows'];
    }
} 