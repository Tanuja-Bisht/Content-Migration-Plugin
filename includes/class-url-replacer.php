<?php
/**
 * URL Search and Replace functionality
 */

class URL_Replacer {

    /**
     * Display the URL search and replace page.
     */
    public function display_url_replace_page() {
        // Get results from transient if available
        $results_data = array('errors' => array(), 'success_message' => '', 'replacements' => array());
        $transient_key = 'content_migrator_url_replace_results_' . get_current_user_id();

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
        echo '<h2>URL Search & Replace</h2>';
        echo '<p>Upload a CSV file with old URLs and new URLs to perform a search and replace operation across your entire website content.</p>';
        
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="content_migrator_url_replace">';
        wp_nonce_field('content_migrator_url_replace', 'content_migrator_url_replace_nonce');
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="url_replace_file">CSV File</label></th>';
        echo '<td>';
        echo '<input type="file" name="url_replace_file" id="url_replace_file" accept=".csv" required>';
        echo '<p class="description">Upload a CSV file with Old URL and New URL columns. You can use the same format as the content migration file.</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">Options</th>';
        echo '<td>';
        echo '<label>';
        echo '<input type="checkbox" name="simulate" value="1" checked>';
        echo ' Simulation mode (does not make actual changes)';
        echo '</label>';
        echo '<p class="description">Use this to preview what changes would be made without actually updating the database.</p>';
        echo '<br><br>';
        echo '<label>';
        echo '<input type="checkbox" name="include_attachments" value="1" checked>';
        echo ' Include media attachments';
        echo '</label>';
        echo '<p class="description">Search and replace in attachment URLs as well.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">Search & Replace URLs</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        
        // Display results if available
        if (!empty($results_data['replacements'])) {
            echo '<div class="card">';
            echo '<h2>Search & Replace Results</h2>';
            echo '<div style="max-height: 400px; overflow: auto;">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Content Type</th>';
            echo '<th>Title/Name</th>';
            echo '<th>Old URL</th>';
            echo '<th>New URL</th>';
            echo '<th>Occurrences</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($results_data['replacements'] as $replacement) {
                echo '<tr>';
                echo '<td>' . esc_html($replacement['type']) . '</td>';
                echo '<td>' . esc_html($replacement['title']) . '</td>';
                echo '<td>' . esc_html($replacement['old_url']) . '</td>';
                echo '<td>' . esc_html($replacement['new_url']) . '</td>';
                echo '<td>' . esc_html($replacement['count']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div class="cm-sidebar">';
        echo '<div class="cm-sidebar-flex">';
        echo '<div class="card sidebar-card">';
        echo '<h2>Instructions</h2>';
        echo '<ol>';
        echo '<li><strong>Prepare your CSV file</strong> with the following columns:';
        echo '<ul>';
        echo '<li><strong>Old URL:</strong> The URL pattern to search for</li>';
        echo '<li><strong>New URL:</strong> The URL to replace it with</li>';
        echo '</ul>';
        echo '</li>';
        echo '<li><strong>Upload your file</strong> and select options:';
        echo '<ul>';
        echo '<li>Use <strong>Simulation mode</strong> to preview changes</li>';
        echo '<li>Include <strong>media attachments</strong> to search image URLs too</li>';
        echo '</ul>';
        echo '</li>';
        echo '<li><strong>Click "Search & Replace URLs"</strong> to start the process</li>';
        echo '<li><strong>Review the results</strong> carefully before running without simulation mode</li>';
        echo '</ol>';
        echo '</div>';
        
        echo '<div class="card sidebar-card">';
        echo '<h2>Important Rules</h2>';
        echo '<ul>';
        echo '<li><strong>Always start with simulation mode</strong> to avoid unintended changes</li>';
        echo '<li><strong>Use absolute URLs</strong> in your CSV for more consistent results</li>';
        echo '<li><strong>Back up your database</strong> before performing actual replacements</li>';
        echo '<li><strong>Include protocol</strong> (http:// or https://) in old URLs when possible</li>';
        echo '<li><strong>Check for trailing slashes</strong> - the tool handles them automatically, but review your results</li>';
        echo '<li><strong>Large sites may timeout</strong> - if this happens, split your CSV into smaller batches</li>';
        echo '<li><strong>Clear your cache</strong> after making replacements to see changes on the site</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="card">';
        echo '<h2>How It Works</h2>';
        echo '<p>This tool:</p>';
        echo '<ul>';
        echo '<li>Reads old and new URLs from your CSV file</li>';
        echo '<li>Searches all post content, postmeta, and optionally attachments</li>';
        echo '<li>Ensures proper URL formatting (adds leading slash if missing)</li>';
        echo '<li>Maintains a detailed log of all replacements made</li>';
        echo '<li>Works with both absolute and relative URLs</li>';
        echo '</ul>';
        echo '<p><strong>Note:</strong> Always backup your database before running in non-simulation mode!</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<style>
            .cm-content-wrapper {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -10px;
            }
            .cm-main-content {
                flex: 2;
                min-width: 400px;
                padding: 0 10px;
                box-sizing: border-box;
                margin-bottom: 20px;
            }
            .cm-sidebar {
                flex: 3;
                padding: 0 10px;
                box-sizing: border-box;
            }
            .cm-sidebar-flex {
                display: flex;
                flex-direction: row;
                gap: 20px;
                margin-bottom: 20px;
            }
            .sidebar-card {
                flex: 1;
                min-width: 300px;
                box-sizing: border-box;
                overflow-wrap: break-word;
                word-wrap: break-word;
            }
            .sidebar-card ul, .sidebar-card ol {
                margin-left: 1.5em;
                padding-right: 10px;
            }
            .sidebar-card li {
                margin-bottom: 8px;
            }
            @media screen and (max-width: 1200px) {
                .cm-sidebar-flex {
                    flex-direction: column;
                }
                .sidebar-card {
                    width: 100%;
                }
            }
            @media screen and (max-width: 850px) {
                .cm-content-wrapper {
                    flex-direction: column;
                }
                .cm-main-content, .cm-sidebar {
                    flex: none;
                    width: 100%;
                }
            }
            #wpfooter {
                display: none;
            }
        </style>';
        
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Handle URL search and replace submission
     */
    public function handle_url_replace() {
        // Check nonce for security
        if (!isset($_POST['content_migrator_url_replace_nonce']) || !wp_verify_nonce($_POST['content_migrator_url_replace_nonce'], 'content_migrator_url_replace')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $simulate = isset($_POST['simulate']) ? true : false;
        $include_attachments = isset($_POST['include_attachments']) ? true : false;
        $results = array();
        $errors = array();
        $success_message = '';
        $replacements = array();

        // Check if file was uploaded
        if (!isset($_FILES['url_replace_file']) || $_FILES['url_replace_file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'File upload failed.';
            
            // Provide more specific error messages based on the error code
            if (isset($_FILES['url_replace_file']['error'])) {
                switch ($_FILES['url_replace_file']['error']) {
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
            $this->redirect_with_url_replace_results($errors, $results, '', $replacements);
            return;
        }

        // Check file extension - only allow CSV
        $file_extension = strtolower(pathinfo($_FILES['url_replace_file']['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            $errors[] = 'File must have .csv extension.';
            $this->redirect_with_url_replace_results($errors, $results, '', $replacements);
            return;
        }

        // Process the file
        try {
            // Parse the CSV file to get URL mappings
            $url_mappings = $this->parse_url_mappings_from_csv($_FILES['url_replace_file']['tmp_name']);
            
            if (empty($url_mappings)) {
                $errors[] = 'No valid URL mappings found in the CSV file.';
                $this->redirect_with_url_replace_results($errors, $results, '', $replacements);
                return;
            }
            
            // Perform the search and replace
            $replacements = $this->perform_url_search_replace($url_mappings, $simulate, $include_attachments);
            
            // Create success message
            $mode_text = $simulate ? 'Simulation' : 'Replacement';
            $total_replacements = 0;
            foreach ($replacements as $replacement) {
                $total_replacements += $replacement['count'];
            }
            
            $success_message = sprintf(
                '%s completed: %d URLs found across %d content items.', 
                $mode_text,
                $total_replacements,
                count($replacements)
            );
            
            if ($simulate) {
                $success_message .= ' No changes were made to the database. Run again without simulation mode to apply changes.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Error processing file: ' . $e->getMessage();
        }

        // Redirect back with results
        $this->redirect_with_url_replace_results($errors, $results, $success_message, $replacements);
    }
    
    /**
     * Parse URL mappings from a CSV file
     * 
     * @param string $file_path Path to the CSV file
     * @return array Array of URL mappings [old_url => new_url]
     */
    private function parse_url_mappings_from_csv($file_path) {
        $url_mappings = array();
        
        // Open the file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file');
        }
        
        // Read header row to identify column positions
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Could not read headers from CSV file');
        }
        
        // Filter out non-printable characters from headers and trim
        foreach ($headers as &$header) {
            $header = trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header));
        }
        
        // Find the column indexes for Old URL and New URL
        $old_url_index = -1;
        $new_url_index = -1;
        
        foreach ($headers as $index => $header) {
            $header_lower = strtolower($header);
            if ($header_lower === 'old url' || $header_lower === 'old_url') {
                $old_url_index = $index;
            } elseif ($header_lower === 'new url' || $header_lower === 'new_url') {
                $new_url_index = $index;
            }
        }
        
        // Check if we found both required columns
        if ($old_url_index === -1 || $new_url_index === -1) {
            throw new Exception('CSV file must contain "Old URL" and "New URL" columns');
        }
        
        // Process the rows
        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty($row) || count($row) <= max($old_url_index, $new_url_index)) {
                continue;
            }
            
            $old_url = trim($row[$old_url_index]);
            $new_url = trim($row[$new_url_index]);
            
            // Skip rows where either URL is empty
            if (empty($old_url) || empty($new_url)) {
                continue;
            }
            
            // Add to mappings array
            $url_mappings[$old_url] = $new_url;
            
            // Also add variations of the URLs
            // With and without trailing slashes
            $url_mappings[rtrim($old_url, '/')] = $new_url;
            $url_mappings[rtrim($old_url, '/') . '/'] = $new_url;
            
            // Without scheme and www
            $parsed_old = parse_url($old_url);
            if (isset($parsed_old['host']) && isset($parsed_old['path'])) {
                $host = $parsed_old['host'];
                $path = $parsed_old['path'];
                
                // Host without www
                $host_no_www = preg_replace('/^www\./', '', $host);
                
                // Add variations
                $url_mappings[$host . $path] = $new_url;
                $url_mappings[$host_no_www . $path] = $new_url;
                
                // With trailing slash variations
                $url_mappings[$host . rtrim($path, '/')] = $new_url;
                $url_mappings[$host . rtrim($path, '/') . '/'] = $new_url;
                $url_mappings[$host_no_www . rtrim($path, '/')] = $new_url;
                $url_mappings[$host_no_www . rtrim($path, '/') . '/'] = $new_url;
            }
        }
        
        fclose($handle);
        
        return $url_mappings;
    }
    
    /**
     * Perform URL search and replace across the site content
     * 
     * @param array $url_mappings URL mappings [old_url => new_url]
     * @param bool $simulate Whether to simulate the changes
     * @param bool $include_attachments Whether to include attachments
     * @return array Results of the operation
     */
    private function perform_url_search_replace($url_mappings, $simulate, $include_attachments) {
        global $wpdb;
        $replacements = array();
        
        // Ensure new URLs have leading slash if they're relative
        foreach ($url_mappings as $old_url => $new_url) {
            // Skip if the new URL is already an absolute URL
            if (!preg_match('/^https?:\/\//', $new_url)) {
                // Add leading slash if not present
                $url_mappings[$old_url] = '/' . ltrim($new_url, '/');
            }
        }
        
        // 1. Search in post content
        $post_types = get_post_types(array('public' => true));
        $post_types_list = "'" . implode("','", esc_sql($post_types)) . "'";
        
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content, post_type 
             FROM {$wpdb->posts} 
             WHERE post_type IN ({$post_types_list}) 
             AND post_status = 'publish'"
        );
        
        foreach ($posts as $post) {
            $original_content = $post->post_content;
            $new_content = $original_content;
            $replacement_count = 0;
            
            // Search and replace each URL mapping
            foreach ($url_mappings as $old_url => $new_url) {
                // Count occurrences
                $count = substr_count($new_content, $old_url);
                if ($count > 0) {
                    $replacement_count += $count;
                    $new_content = str_replace($old_url, $new_url, $new_content);
                }
            }
            
            // If we made replacements, update the post content
            if ($replacement_count > 0) {
                if (!$simulate) {
                    $wpdb->update(
                        $wpdb->posts,
                        array('post_content' => $new_content),
                        array('ID' => $post->ID)
                    );
                }
                
                $replacements[] = array(
                    'type' => 'Post: ' . $post->post_type,
                    'title' => $post->post_title,
                    'old_url' => '(multiple)',
                    'new_url' => '(multiple)',
                    'count' => $replacement_count
                );
            }
        }
        
        // 2. Search in post meta
        $meta_key_like = '%url%';
        
        $meta_entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_title, p.post_type
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE (pm.meta_key LIKE %s OR pm.meta_value LIKE %s)
                 AND p.post_status = 'publish'",
                $meta_key_like,
                '%http%'
            )
        );
        
        foreach ($meta_entries as $meta) {
            $original_value = $meta->meta_value;
            $new_value = $original_value;
            $replacement_count = 0;
            
            // Search and replace each URL mapping
            foreach ($url_mappings as $old_url => $new_url) {
                // Check if the meta value is a serialized array
                if (is_serialized($new_value)) {
                    // Unserialize, replace, and reserialize
                    $unserialized = maybe_unserialize($new_value);
                    $new_unserialized = $this->replace_urls_in_array($unserialized, $url_mappings, $replacement_count);
                    if ($replacement_count > 0) {
                        $new_value = maybe_serialize($new_unserialized);
                    }
                } else {
                    // Simple string replacement
                    $count = substr_count($new_value, $old_url);
                    if ($count > 0) {
                        $replacement_count += $count;
                        $new_value = str_replace($old_url, $new_url, $new_value);
                    }
                }
            }
            
            // If we made replacements, update the meta value
            if ($replacement_count > 0) {
                if (!$simulate) {
                    update_post_meta($meta->post_id, $meta->meta_key, $new_value);
                }
                
                $replacements[] = array(
                    'type' => 'Meta: ' . $meta->post_type,
                    'title' => $meta->post_title . ' (' . $meta->meta_key . ')',
                    'old_url' => '(multiple)',
                    'new_url' => '(multiple)',
                    'count' => $replacement_count
                );
            }
        }
        
        // 3. Search in options table
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options}
             WHERE option_value LIKE '%http%'"
        );
        
        foreach ($options as $option) {
            $original_value = $option->option_value;
            $new_value = $original_value;
            $replacement_count = 0;
            
            // Handle serialized values
            if (is_serialized($new_value)) {
                // Unserialize, replace, and reserialize
                $unserialized = maybe_unserialize($new_value);
                $new_unserialized = $this->replace_urls_in_array($unserialized, $url_mappings, $replacement_count);
                if ($replacement_count > 0) {
                    $new_value = maybe_serialize($new_unserialized);
                }
            } else {
                // Simple string replacement
                foreach ($url_mappings as $old_url => $new_url) {
                    $count = substr_count($new_value, $old_url);
                    if ($count > 0) {
                        $replacement_count += $count;
                        $new_value = str_replace($old_url, $new_url, $new_value);
                    }
                }
            }
            
            // If we made replacements, update the option
            if ($replacement_count > 0) {
                if (!$simulate) {
                    update_option($option->option_name, $new_value);
                }
                
                $replacements[] = array(
                    'type' => 'Option',
                    'title' => $option->option_name,
                    'old_url' => '(multiple)',
                    'new_url' => '(multiple)',
                    'count' => $replacement_count
                );
            }
        }
        
        // 4. Optionally search in attachment URLs
        if ($include_attachments) {
            $attachments = $wpdb->get_results(
                "SELECT ID, guid, post_title
                 FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'"
            );
            
            foreach ($attachments as $attachment) {
                $original_guid = $attachment->guid;
                $new_guid = $original_guid;
                $replacement_count = 0;
                
                foreach ($url_mappings as $old_url => $new_url) {
                    // First check if the old URL is in the GUID
                    $count = substr_count($new_guid, $old_url);
                    if ($count > 0) {
                        $replacement_count += $count;
                        $new_guid = str_replace($old_url, $new_url, $new_guid);
                    }
                }
                
                // If we made replacements, update the attachment GUID
                if ($replacement_count > 0) {
                    if (!$simulate) {
                        $wpdb->update(
                            $wpdb->posts,
                            array('guid' => $new_guid),
                            array('ID' => $attachment->ID)
                        );
                    }
                    
                    $replacements[] = array(
                        'type' => 'Attachment',
                        'title' => $attachment->post_title,
                        'old_url' => $original_guid,
                        'new_url' => $new_guid,
                        'count' => $replacement_count
                    );
                }
            }
        }
        
        return $replacements;
    }
    
    /**
     * Recursively replace URLs in an array or nested array
     * 
     * @param mixed $data Array or value to process
     * @param array $url_mappings URL mappings
     * @param int &$count Counter for replacements
     * @return mixed Updated array or value
     */
    private function replace_urls_in_array($data, $url_mappings, &$count) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->replace_urls_in_array($value, $url_mappings, $count);
            }
            return $data;
        } elseif (is_string($data)) {
            $new_value = $data;
            foreach ($url_mappings as $old_url => $new_url) {
                $replacement_count = substr_count($new_value, $old_url);
                if ($replacement_count > 0) {
                    $count += $replacement_count;
                    $new_value = str_replace($old_url, $new_url, $new_value);
                }
            }
            return $new_value;
        } else {
            return $data;
        }
    }
    
    /**
     * Redirect with URL replace results
     */
    private function redirect_with_url_replace_results($errors, $results, $success_message, $replacements) {
        // Store data in transient
        $transient_key = 'content_migrator_url_replace_results_' . wp_get_current_user()->ID;
        set_transient($transient_key, array(
            'errors' => $errors,
            'results' => $results,
            'success_message' => $success_message,
            'replacements' => $replacements
        ), 60 * 5); // 5 minutes expiration

        // Redirect back to admin page
        wp_redirect(admin_url('admin.php?page=content-migrator-url-replace&processed=1'));
        exit;
    }
} 