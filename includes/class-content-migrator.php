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
        add_action('admin_post_content_migrator_download_sample', array($this, 'download_sample_excel'));
        
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
     * Display the Format Document page.
     */
    public function display_format_document_page() {
        // Get results from transient if available
        $results_data = array('errors' => array(), 'success_message' => '', 'download_url' => '');
        $transient_key = 'content_migrator_format_results_' . get_current_user_id();

        if (isset($_GET['processed']) && $_GET['processed'] == 1) {
            $results_data = get_transient($transient_key);
            if ($results_data) {
                delete_transient($transient_key);
            }
        }

        // Display the admin page
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        // Show success message if any
        if (!empty($results_data['success_message'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . esc_html($results_data['success_message']) . '</strong></p>';
            
            // If we have a download URL, show the download button
            if (!empty($results_data['download_url'])) {
                $nonce = wp_create_nonce('content_migrator_download_formatted');
                $download_url = admin_url('admin-post.php?action=content_migrator_download_formatted&nonce=' . $nonce);
                echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary">Download Formatted File</a></p>';
            }
            
            echo '</div>';
        }
        
        // Show error messages if any
        if (!empty($results_data['errors'])) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Errors:</strong></p>';
            echo '<ul>';
            foreach ($results_data['errors'] as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        echo '<div class="cm-content-wrapper">';
        echo '<div class="cm-main-content">';
        echo '<div class="card">';
        echo '<h2>Format Document</h2>';
        echo '<p>Upload a CSV or Excel file to format it for Content Migrator. This tool will process the file according to the business logic and create a properly formatted file ready for migration.</p>';
        
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="content_migrator_format_document">';
        wp_nonce_field('content_migrator_format_document', 'content_migrator_format_nonce');
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="format_file">File</label></th>';
        echo '<td>';
        echo '<input type="file" name="format_file" id="format_file" accept=".xlsx,.csv" required>';
        echo '<p class="description">Upload a CSV or Excel file to format for Content Migrator.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">Format and Download</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="cm-sidebar">';
        echo '<div class="card">';
        echo '<h2>How it Works</h2>';
        echo '<p>This tool reformats your spreadsheet according to these rules:</p>';
        echo '<ol>';
        echo '<li>Processes rows marked with "MIGRATE"</li>';
        echo '<li>Creates a Menu Name from Top Level Nav headings</li>';
        echo '<li>Extracts just the slug from URLs</li>';
        echo '<li>Determines content type (page/post) based on position</li>';
        echo '<li>Excludes content under Footer Nav heading</li>';
        echo '</ol>';
        echo '</div>';
        
        echo '<div class="card">';
        echo '<h2>Expected File Structure</h2>';
        echo '<p>Your input file should include:</p>';
        echo '<ul>';
        echo '<li>A section marked "MIGRATE"</li>';
        echo '<li>Headings like "Top Level Nav", "Blog", "Footer Nav"</li>';
        echo '<li>Columns for URLs, Meta Title, H1, etc.</li>';
        echo '</ul>';
        echo '<p>The output will be formatted to work with the Content Migrator.</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Handle formatting document submission
     */
    public function handle_format_document() {
        // Check nonce for security
        if (!isset($_POST['content_migrator_format_nonce']) || !wp_verify_nonce($_POST['content_migrator_format_nonce'], 'content_migrator_format_document')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $results = array();
        $errors = array();
        $success_message = '';
        $download_url = '';

        // Check if file was uploaded
        if (!isset($_FILES['format_file']) || $_FILES['format_file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'File upload failed.';
            
            // Provide more specific error messages based on the error code
            if (isset($_FILES['format_file']['error'])) {
                switch ($_FILES['format_file']['error']) {
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
            $this->redirect_with_format_results($errors, $success_message, $download_url);
            return;
        }

        // Make sure file type is valid
        $file_extension = strtolower(pathinfo($_FILES['format_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, array('xlsx', 'csv'))) {
            $errors[] = 'File must have .xlsx or .csv extension.';
            $this->redirect_with_format_results($errors, $success_message, $download_url);
            return;
        }

        // Process the file
        try {
            // Get the uploaded file path
            $uploaded_file = $_FILES['format_file']['tmp_name'];
            
            // Create timestamp for the output filename
            $timestamp = date('Ymd_Hi');
            // Always save as CSV for maximum compatibility
            $output_filename = "formatted_{$timestamp}.csv";
            $output_path = wp_upload_dir()['basedir'] . '/' . $output_filename;
            
            // Process the file based on its type
            if ($file_extension === 'csv') {
                $result = $this->process_format_csv($uploaded_file, $output_path);
            } else {
                $result = $this->process_format_xlsx($uploaded_file, $output_path);
            }
            
            if ($result['success']) {
                $success_message = 'File formatted successfully.';
                // Store the file path for download
                set_transient('content_migrator_formatted_file_' . get_current_user_id(), $output_path, 3600); // 1 hour expiration
                $download_url = 'yes'; // Just a flag to show the download button
            } else {
                $errors[] = 'Error formatting file: ' . $result['message'];
            }
        } catch (Exception $e) {
            $errors[] = 'Error processing file: ' . $e->getMessage();
        }

        // Redirect back with results
        $this->redirect_with_format_results($errors, $success_message, $download_url);
    }
    
    /**
     * Process CSV file for formatting
     * 
     * @param string $input_file Path to the input CSV file
     * @param string $output_file Path to save the output CSV file
     * @return array Result information
     */
    private function process_format_csv($input_file, $output_file) {
        try {
            // Debug log - create log file in uploads directory
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/content_migrator_debug.log';
            file_put_contents($log_file, "=== Processing started at " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
            file_put_contents($log_file, "Input file: $input_file\nOutput file: $output_file\n", FILE_APPEND);
            
            // Open the input file
            $input = fopen($input_file, 'r');
            if (!$input) {
                file_put_contents($log_file, "ERROR: Could not open input file\n", FILE_APPEND);
                return array('success' => false, 'message' => 'Could not open input file');
            }
            
            // Create the output file
            $output = fopen($output_file, 'w');
            if (!$output) {
                fclose($input);
                file_put_contents($log_file, "ERROR: Could not create output file\n", FILE_APPEND);
                return array('success' => false, 'message' => 'Could not create output file');
            }
            
            // Write header row to output
            $header_row = array('MIGRATE', 'Menu Name', 'Old URL', 'New URL', 'Meta Title', 'H1', 'Page/Post Title', 'Image', 'Type');
            fputcsv($output, $header_row);
            
            // Read all input rows into memory
            $all_rows = array();
            $row_count = 0;
            
            // Read the file content first to check BOM and detect line endings
            $file_content = file_get_contents($input_file);
            // Remove UTF-8 BOM if present
            $bom = pack('H*','EFBBBF');
            $file_content = preg_replace("/^$bom/", '', $file_content);
            
            // Detect line endings
            $line_ending = "\n";
            if (strpos($file_content, "\r\n") !== false) {
                $line_ending = "\r\n";
            } elseif (strpos($file_content, "\r") !== false) {
                $line_ending = "\r";
            }
            
            file_put_contents($log_file, "Detected line ending: " . bin2hex($line_ending) . "\n", FILE_APPEND);
            
            // Reset file pointer
            rewind($input);
            
            // Try to detect the delimiter
            $first_line = fgets($input);
            $possible_delimiters = array(',', ';', "\t", '|');
            $delimiter = ','; // Default
            $max_count = 0;
            
            foreach ($possible_delimiters as $possible_delimiter) {
                $count = substr_count($first_line, $possible_delimiter);
                if ($count > $max_count) {
                    $max_count = $count;
                    $delimiter = $possible_delimiter;
                }
            }
            
            file_put_contents($log_file, "Detected delimiter: " . bin2hex($delimiter) . "\n", FILE_APPEND);
            
            // Reset file pointer again
            rewind($input);
            
            // Read the CSV file with the detected delimiter
            while (($row = fgetcsv($input, 0, $delimiter)) !== false) {
                // Trim values to remove potential whitespace issues
                $trimmed_row = array_map('trim', $row);
                $all_rows[] = $trimmed_row;
                file_put_contents($log_file, "Read row " . $row_count . ": " . json_encode($trimmed_row) . "\n", FILE_APPEND);
                $row_count++;
            }
            
            fclose($input);
            
            file_put_contents($log_file, "Read $row_count rows from input file\n", FILE_APPEND);
            
            if (count($all_rows) === 0) {
                fclose($output);
                file_put_contents($log_file, "ERROR: Empty or invalid CSV file\n", FILE_APPEND);
                return array('success' => false, 'message' => 'Empty or invalid CSV file');
            }
            
            // Check if we have any data rows
            if ($row_count <= 1) {
                fclose($output);
                file_put_contents($log_file, "ERROR: CSV file has only header or is empty\n", FILE_APPEND);
                return array('success' => false, 'message' => 'CSV file has only header or is empty');
            }
            
            // Assume first row is header
            $headers = $all_rows[0];
            file_put_contents($log_file, "Using headers from first row: " . implode(', ', $headers) . "\n", FILE_APPEND);
            
            // Debug the first few data rows to check what we're dealing with
            for ($i = 1; $i < min(5, count($all_rows)); $i++) {
                file_put_contents($log_file, "Data row $i: " . implode(', ', $all_rows[$i]) . "\n", FILE_APPEND);
            }
            
            // Map column indexes
            $col_indexes = array(
                'old_url' => -1,
                'new_url' => -1,
                'content' => -1,
                'page_title' => -1,
                'h1' => -1,
                'image' => -1,
                'meta_title' => -1
            );
            
            // Find the indexes of important columns
            foreach ($headers as $index => $header) {
                $header_lower = strtolower(trim($header));
                file_put_contents($log_file, "Mapping header '$header_lower' at index $index\n", FILE_APPEND);
                
                if ($header_lower === 'old url' || $header_lower === 'old_url' || $header_lower === 'url') {
                    $col_indexes['old_url'] = $index;
                } elseif ($header_lower === 'new url' || $header_lower === 'new_url') {
                    $col_indexes['new_url'] = $index;
                } elseif ($header_lower === 'content') {
                    $col_indexes['content'] = $index;
                } elseif (strpos($header_lower, 'page title') !== false || $header_lower === 'title' || $header_lower === 'page/post title') {
                    $col_indexes['page_title'] = $index;
                } elseif ($header_lower === 'h1' || $header_lower === 'heading') {
                    $col_indexes['h1'] = $index;
                } elseif (strpos($header_lower, 'image') !== false || strpos($header_lower, 'photo') !== false) {
                    $col_indexes['image'] = $index;
                } elseif (strpos($header_lower, 'meta title') !== false || $header_lower === 'meta_title') {
                    $col_indexes['meta_title'] = $index;
                }
            }
            
            file_put_contents($log_file, "Column mapping: " . print_r($col_indexes, true) . "\n", FILE_APPEND);
            
            // Process data rows (starting from second row)
            $processed_rows = 0;
            
            for ($i = 1; $i < count($all_rows); $i++) {
                $row = $all_rows[$i];
                
                // Skip truly empty rows, but process any row that has at least one non-empty value
                if (empty($row) || (count($row) === 1 && empty(trim($row[0])))) {
                    file_put_contents($log_file, "Row $i: Skipping truly empty row\n", FILE_APPEND);
                    continue;
                }
                
                // If row has fewer columns than headers, pad it with empty values
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                    file_put_contents($log_file, "Row $i: Padded row to match header count\n", FILE_APPEND);
                }
                
                // Check if row has any non-empty values
                $has_content = false;
                foreach ($row as $cell) {
                    if (!empty(trim($cell))) {
                        $has_content = true;
                        break;
                    }
                }
                
                if (!$has_content) {
                    file_put_contents($log_file, "Row $i: Skipping row with all empty cells\n", FILE_APPEND);
                    continue;
                }
                
                file_put_contents($log_file, "Processing row $i: " . implode(', ', $row) . "\n", FILE_APPEND);
                
                // Extract data from row
                $old_url = '';
                $new_url = '';
                $meta_title = '';
                $h1 = '';
                $page_title = '';
                $featured_image = '';
                $menu_name = '';
                
                // Extract URLs
                if ($col_indexes['old_url'] >= 0 && isset($row[$col_indexes['old_url']])) {
                    $old_url = trim($row[$col_indexes['old_url']]);
                    file_put_contents($log_file, "Found Old URL: '$old_url'\n", FILE_APPEND);
                }
                
                if ($col_indexes['new_url'] >= 0 && isset($row[$col_indexes['new_url']])) {
                    $new_url = trim($row[$col_indexes['new_url']]);
                    file_put_contents($log_file, "Found New URL: '$new_url'\n", FILE_APPEND);
                }
                
                // Extract titles
                if ($col_indexes['page_title'] >= 0 && isset($row[$col_indexes['page_title']])) {
                    $page_title = trim($row[$col_indexes['page_title']]);
                    file_put_contents($log_file, "Found Page Title: '$page_title'\n", FILE_APPEND);
                }
                
                if ($col_indexes['meta_title'] >= 0 && isset($row[$col_indexes['meta_title']])) {
                    $meta_title = trim($row[$col_indexes['meta_title']]);
                    file_put_contents($log_file, "Found Meta Title: '$meta_title'\n", FILE_APPEND);
                } else if (!empty($page_title)) {
                    // Use page title as meta title if not explicitly provided
                    $meta_title = $page_title;
                }
                
                if ($col_indexes['h1'] >= 0 && isset($row[$col_indexes['h1']])) {
                    $h1 = trim($row[$col_indexes['h1']]);
                    file_put_contents($log_file, "Found H1: '$h1'\n", FILE_APPEND);
                }
                
                // Extract featured image URL
                if ($col_indexes['image'] >= 0 && isset($row[$col_indexes['image']])) {
                    $featured_image = trim($row[$col_indexes['image']]);
                    file_put_contents($log_file, "Found Featured Image: '$featured_image'\n", FILE_APPEND);
                    
                    // Standardize to "Yes" or "No"
                    if (!empty($featured_image)) {
                        $featured_image_lower = strtolower($featured_image);
                        if ($featured_image_lower == 'yes' || $featured_image_lower == 'y' || $featured_image_lower == 'true' || $featured_image_lower == '1') {
                            $featured_image = 'Yes';
                        } else {
                            $featured_image = 'No';
                        }
                    } else {
                        $featured_image = 'No';
                    }
                } else {
                    $featured_image = 'No';
                }
                
                // Generate New URL from Old URL if not explicitly provided
                if (empty($new_url) && !empty($old_url)) {
                    $parsed_url = parse_url($old_url);
                    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
                    $new_url = trim($path, '/');
                    file_put_contents($log_file, "Generated New URL from path: '$new_url'\n", FILE_APPEND);
                    
                    // If URL is empty or just a domain with no path, use a sanitized version of the title
                    if (empty($new_url) && !empty($page_title)) {
                        $new_url = $this->sanitize_title_for_url($page_title);
                        file_put_contents($log_file, "Generated New URL from title: '$new_url'\n", FILE_APPEND);
                    }
                }
                
                // Use page title as H1 if H1 is empty
                if (empty($h1) && !empty($page_title)) {
                    $h1 = $page_title;
                }
                
                // Determine default content type (page)
                $type = 'page';
                
                // Create output row - ensure we're using the correct order of columns
                $migrate_value = '';
                
                // Check if the row has valid data for migration
                if (!empty($old_url) && (!empty($new_url) || !empty($page_title))) {
                    // Only mark rows as "MIGRATE" if they have necessary data
                    $migrate_value = 'MIGRATE';
                }
                
                $output_row = array(
                    $migrate_value,         // Only add MIGRATE if the row has valid data
                    $menu_name,            // Menu Name
                    $old_url,              // Old URL
                    $new_url,              // New URL
                    $meta_title,           // Meta Title
                    $h1,                   // H1
                    $page_title,           // Page/Post Title
                    $featured_image,       // Image
                    $type                  // Type
                );
                
                file_put_contents($log_file, "Writing output row: " . implode(', ', $output_row) . "\n", FILE_APPEND);
                
                // Write the row to the output file
                fputcsv($output, $output_row);
                $processed_rows++;
            }
            
            fclose($output);
            
            file_put_contents($log_file, "Processing complete. Processed $processed_rows rows.\n", FILE_APPEND);
            
            if ($processed_rows > 0) {
                return array('success' => true, 'message' => "Successfully processed $processed_rows rows");
            } else {
                return array('success' => false, 'message' => 'No valid rows found in input file');
            }
        } catch (Exception $e) {
            file_put_contents($log_file, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            return array('success' => false, 'message' => 'Error processing CSV: ' . $e->getMessage());
        }
    }
    
    /**
     * Helper function to sanitize a title for use as a URL slug
     */
    private function sanitize_title_for_url($title) {
        // Remove special characters
        $slug = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        // Replace spaces with hyphens
        $slug = preg_replace('/\s+/', '-', $slug);
        // Convert to lowercase
        $slug = strtolower($slug);
        // Remove extra hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        // Trim hyphens from beginning and end
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Process XLSX file for formatting
     * 
     * @param string $input_file Path to the input XLSX file
     * @param string $output_file Path to save the output CSV file
     * @return array Result information
     */
    private function process_format_xlsx($input_file, $output_file) {
        try {
            // Convert XLSX to CSV first for simplified processing
            $temp_csv = $this->xlsx_to_csv($input_file);
            if (!$temp_csv) {
                return array('success' => false, 'message' => 'Could not convert XLSX to CSV');
            }
            
            // Process the CSV
            $result = $this->process_format_csv($temp_csv, $output_file);
            
            // Clean up temp file
            @unlink($temp_csv);
            
            return $result;
        } catch (Exception $e) {
            if (isset($temp_csv) && file_exists($temp_csv)) {
                @unlink($temp_csv);
            }
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Helper function to convert XLSX to CSV
     */
    private function xlsx_to_csv($xlsx_file) {
        // Create temporary file for CSV output
        $temp_csv = tempnam(sys_get_temp_dir(), 'csv_');
        
        try {
            // XLSX files are ZIP archives containing XML files
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive extension is required to process Excel files');
            }
            
            // Extract Excel file contents
            $zip = new ZipArchive();
            if ($zip->open($xlsx_file) !== true) {
                throw new Exception('Could not open Excel file');
            }
            
            // Create a temporary directory
            $temp_dir = tempnam(sys_get_temp_dir(), 'xlsx_');
            if (file_exists($temp_dir)) {
                unlink($temp_dir);
            }
            mkdir($temp_dir);
            
            // Extract files
            $zip->extractTo($temp_dir);
            $zip->close();
            
            // Load the sheet data
            $sheet_file = $temp_dir . '/xl/worksheets/sheet1.xml';
            if (!file_exists($sheet_file)) {
                throw new Exception('Could not find sheet data in Excel file');
            }
            
            // Load shared strings if available
            $strings = array();
            $shared_strings_file = $temp_dir . '/xl/sharedStrings.xml';
            if (file_exists($shared_strings_file)) {
                $xml = simplexml_load_file($shared_strings_file);
                foreach ($xml->si as $si) {
                    $strings[] = (string) $si->t;
                }
            }
            
            // Parse the sheet
            $sheet = simplexml_load_file($sheet_file);
            $rows = array();
            
            // Get all rows
            foreach ($sheet->sheetData->row as $row) {
                $row_index = (int) $row['r'];
                $cells = array();
                
                // Get cells in this row
                foreach ($row->c as $cell) {
                    $cell_ref = (string) $cell['r'];
                    $column = preg_replace('/[0-9]+/', '', $cell_ref);
                    $col_index = $this->column_letter_to_xlsx_index($column);
                    
                    // Get cell value
                    $value = '';
                    if (isset($cell->v)) {
                        $value = (string) $cell->v;
                        
                        // Handle different data types
                        if (isset($cell['t']) && (string) $cell['t'] === 's') {
                            // Shared string
                            $value = $strings[(int) $value];
                        }
                    }
                    
                    $cells[$col_index] = $value;
                }
                
                $rows[$row_index] = $cells;
            }
            
            // Write to CSV
            $csv = fopen($temp_csv, 'w');
            ksort($rows);  // Sort by row index
            
            foreach ($rows as $row) {
                // Fill in any blank cells
                $csv_row = array();
                $max_col = 0;
                foreach ($row as $col => $value) {
                    $max_col = max($max_col, $col);
                }
                
                for ($i = 0; $i <= $max_col; $i++) {
                    $csv_row[$i] = isset($row[$i]) ? $row[$i] : '';
                }
                
                ksort($csv_row);  // Make sure columns are in order
                fputcsv($csv, $csv_row);
            }
            
            fclose($csv);
            
            // Clean up
            $this->delete_xlsx_temp_directory($temp_dir);
            
            return $temp_csv;
            
        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($temp_csv)) {
                @unlink($temp_csv);
            }
            if (isset($temp_dir) && file_exists($temp_dir)) {
                $this->delete_xlsx_temp_directory($temp_dir);
            }
            
            throw $e;
        }
    }
    
    /**
     * Convert column letter to index for XLSX processing (A=0, B=1, etc.)
     */
    private function column_letter_to_xlsx_index($column) {
        $column = strtoupper($column);
        $index = 0;
        for ($i = 0; $i < strlen($column); $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
    
    /**
     * Delete temporary directory and its contents
     */
    private function delete_xlsx_temp_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object == "." || $object == "..") continue;
            
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            
            if (is_dir($path)) {
                $this->delete_xlsx_temp_directory($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
    
    /**
     * Redirect with format results
     */
    private function redirect_with_format_results($errors, $success_message, $download_url) {
        // Store data in transient
        $transient_key = 'content_migrator_format_results_' . wp_get_current_user()->ID;
        set_transient($transient_key, array(
            'errors' => $errors,
            'success_message' => $success_message,
            'download_url' => $download_url
        ), 60 * 5); // 5 minutes expiration

        // Redirect back to format page
        wp_redirect(admin_url('admin.php?page=content-migrator-format-document&processed=1'));
        exit;
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

        // Make sure file type checking doesn't prevent valid CSV and XLSX files
        // We'll let the Excel_Processor class do the detailed format checking
        $file_extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, array('xlsx', 'csv'))) {
            $errors[] = 'File must have .xlsx or .csv extension.';
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
     * Download sample Excel file
     */
    public function download_sample_excel() {
        // Check nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'content_migrator_download_sample')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $processor = new Excel_Processor();
        $processor->generate_sample_excel();
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
