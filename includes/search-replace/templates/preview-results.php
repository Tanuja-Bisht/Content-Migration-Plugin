<?php
/**
 * Template for showing preview results
 */

// Prevent direct access
if (!defined('WPINC') && !defined('WP_USE_THEMES')) {
    die;
}
?>

<h2>Preview Changes</h2>
<p>These are the changes that will be made. Please review them carefully before applying.</p>

<table class="widefat">
    <tr>
        <th>Tables Affected:</th>
        <td><?php echo esc_html($results['tables_processed']); ?></td>
    </tr>
    <tr>
        <th>Rows Processed:</th>
        <td><?php echo esc_html($results['rows_processed']); ?></td>
    </tr>
    <tr>
        <th>Potential Changes:</th>
        <td><?php echo esc_html($results['changes']); ?></td>
    </tr>
    <?php if (isset($results['errors']) && $results['errors'] > 0): ?>
    <tr>
        <th>Errors:</th>
        <td><?php echo esc_html($results['errors']); ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if (!empty($results['previews'])): ?>
    <h3>Preview Changes</h3>
    <p class="description">Showing <?php echo count($results['previews']); ?> sample changes out of <?php echo esc_html($results['changes']); ?> total.</p>
    <div style="max-height: 300px; overflow-y: auto;">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Column</th>
                    <th>ID</th>
                    <th>Old Value</th>
                    <th>New Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['previews'] as $preview): ?>
                    <tr>
                        <td><?php echo esc_html($preview['table']); ?></td>
                        <td><?php echo esc_html($preview['column']); ?></td>
                        <td><?php echo esc_html($preview['id']); ?></td>
                        <td><?php 
                            // Display shortened value if it's too long
                            echo strlen($preview['old']) > 50 
                                ? esc_html(substr($preview['old'], 0, 50) . '...') 
                                : esc_html($preview['old']); 
                        ?></td>
                        <td><?php 
                            // Display shortened value if it's too long
                            echo strlen($preview['new']) > 50 
                                ? esc_html(substr($preview['new'], 0, 50) . '...') 
                                : esc_html($preview['new']); 
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (isset($results['previews_limited']) && $results['previews_limited']): ?>
        <p class="description">Due to the large number of changes, only the first 10 are shown for preview.</p>
    <?php endif; ?>
    
    <p>
        <!-- Form to execute changes -->
        <form method="post" action="" enctype="multipart/form-data">
            <!-- Include nonce for security -->
            <?php wp_nonce_field('wp_content_migrator_search_replace_execute', 'wp_content_migrator_search_replace_execute_nonce'); ?>
            
            <div class="notice notice-info" style="background-color: #e6f6ff; color: #0c5460; border-color: #bee5eb;">
                <p><strong>Important:</strong> Review the changes above before proceeding.</p>
            </div>
            
            <input type="submit" name="execute_changes" class="button button-primary" value="Execute Changes">
            <button type="button" onclick="document.getElementById('cancel_form').submit();" class="button">Cancel</button>
            
            <!-- Hidden cancel form -->
            <form id="cancel_form" method="post" action="<?php echo esc_url(admin_url('admin.php?page=custom-search-replace-migrator')); ?>" style="display:none;">
                <?php wp_nonce_field('wp_content_migrator_reset', 'wp_content_migrator_reset_nonce'); ?>
                <input type="hidden" name="reset_search_replace" value="1">
            </form>
        </form>
    </p>
<?php else: ?>
    <div class="notice notice-warning" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;">
        <p>No changes were detected for the provided search patterns.</p>
        
        <p><strong>Possible reasons:</strong></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li>The search text doesn't exist in your database</li>
            <li>Case sensitivity might be affecting matches</li>
            <li>Special characters in the search patterns might need escaping</li>
            <li>The search strings might not be exactly matching content in the database</li>
        </ul>
        
        <p><strong>Suggestions:</strong></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li>Make sure your CSV file has the correct format with header row</li>
            <li>Try with a simple, common word that definitely exists in your content</li>
            <li>Use the Debug Mode to test if your patterns exist in the database</li>
            <li>Check the WordPress debug.log for more information</li>
        </ul>
    </div>
    <p>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=custom-search-replace-migrator')); ?>">
            <?php wp_nonce_field('wp_content_migrator_reset', 'wp_content_migrator_reset_nonce'); ?>
            <input type="hidden" name="reset_search_replace" value="1">
            <button type="submit" class="button button-primary">Start Over</button>
        </form>
        <a href="?page=custom-search-replace-migrator&debug=1" class="button">Debug Mode</a>
    </p>
<?php endif; ?>

<?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
<div class="card">
    <h2>Debug Information</h2>
    
    <h3>WordPress Database Info</h3>
    <?php
    global $wpdb;
    $db_prefix = $wpdb->prefix;
    $db_charset = $wpdb->charset;
    $db_collate = $wpdb->collate ?: 'Default';
    ?>
    <table class="widefat">
        <tr>
            <th>Database Prefix:</th>
            <td><?php echo esc_html($db_prefix); ?></td>
        </tr>
        <tr>
            <th>Database Charset:</th>
            <td><?php echo esc_html($db_charset); ?></td>
        </tr>
        <tr>
            <th>Database Collation:</th>
            <td><?php echo esc_html($db_collate); ?></td>
        </tr>
    </table>
    
    <h3>Tables Status</h3>
    <table class="widefat">
        <thead>
            <tr>
                <th>Table Name</th>
                <th>Exists</th>
                <th>Row Count</th>
                <th>Sample Content</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tables = array(
                $wpdb->posts => 'Posts',
                $wpdb->postmeta => 'Post Meta',
                $wpdb->options => 'Options',
                $wpdb->comments => 'Comments'
            );
            
            foreach ($tables as $table => $name):
                $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") ? true : false;
                $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;
                
                // Get sample content if table exists
                $sample = '';
                if ($exists) {
                    if ($table == $wpdb->posts) {
                        $sample_row = $wpdb->get_row("SELECT post_title, post_content FROM {$table} WHERE post_status = 'publish' ORDER BY post_date DESC LIMIT 1", ARRAY_A);
                        $sample = isset($sample_row['post_title']) ? esc_html(substr($sample_row['post_title'], 0, 100)) : 'No content found';
                    } elseif ($table == $wpdb->postmeta) {
                        $sample_row = $wpdb->get_row("SELECT meta_value FROM {$table} WHERE meta_value IS NOT NULL AND meta_value != '' AND meta_value NOT LIKE 'a:%' LIMIT 1", ARRAY_A);
                        $sample = isset($sample_row['meta_value']) ? esc_html(substr($sample_row['meta_value'], 0, 100)) : 'No text content found';
                    } elseif ($table == $wpdb->options) {
                        $sample_row = $wpdb->get_row("SELECT option_value FROM {$table} WHERE option_value IS NOT NULL AND option_value != '' AND option_name = 'blogname' LIMIT 1", ARRAY_A);
                        $sample = isset($sample_row['option_value']) ? esc_html($sample_row['option_value']) : 'No text content found';
                    } elseif ($table == $wpdb->comments) {
                        $sample_row = $wpdb->get_row("SELECT comment_content FROM {$table} WHERE comment_approved = '1' ORDER BY comment_date DESC LIMIT 1", ARRAY_A);
                        $sample = isset($sample_row['comment_content']) ? esc_html(substr($sample_row['comment_content'], 0, 100)) : 'No comments found';
                    }
                }
            ?>
            <tr>
                <td><?php echo esc_html($table); ?> (<?php echo esc_html($name); ?>)</td>
                <td><?php echo $exists ? 'Yes' : 'No'; ?></td>
                <td><?php echo esc_html($count); ?></td>
                <td><?php echo $sample; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h3>Search Patterns</h3>
    <?php
    $search_replace = WP_Content_Migrator_Search_Replace::get_instance();
    $replacements = $search_replace->get_replacements();
    
    if (!empty($replacements) && is_array($replacements)): 
    ?>
    <table class="widefat">
        <thead>
            <tr>
                <th>Search For</th>
                <th>Replace With</th>
                <th>Length</th>
                <th>Special Chars</th>
                <th>Sample Test</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($replacements as $replacement): 
                $search_term = $replacement['search_for'];
                $test_result = "Not tested";
                
                // Simple test for term existence
                if (!empty($search_term)) {
                    $found = false;
                    foreach ($tables as $table => $name) {
                        if ($exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
                            // Get table columns
                            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
                            foreach ($columns as $column) {
                                $column_name = $column['Field'];
                                $column_type = strtolower($column['Type']);
                                
                                // Only check text columns
                                if (strpos($column_type, 'text') !== false || 
                                    strpos($column_type, 'varchar') !== false || 
                                    strpos($column_type, 'char') !== false || 
                                    strpos($column_type, 'json') !== false || 
                                    strpos($column_type, 'longtext') !== false) {
                                    
                                    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$column_name} LIKE '%" . esc_sql($search_term) . "%'");
                                    if ($count > 0) {
                                        $found = true;
                                        $test_result = "FOUND in {$table}.{$column_name}: {$count} matches";
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$found) {
                        $test_result = "Not found in database tables";
                    }
                }
            ?>
            <tr>
                <td><?php echo esc_html($replacement['search_for']); ?></td>
                <td><?php echo esc_html($replacement['replace_with']); ?></td>
                <td><?php echo esc_html(strlen($replacement['search_for'])); ?></td>
                <td><?php echo preg_match('/[\\^$.*+?()[\]{}|\/]/', $replacement['search_for']) ? 'Yes' : 'No'; ?></td>
                <td><?php echo $test_result; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No search patterns loaded.</p>
    <?php endif; ?>
    
    <h3>Quick Search Test</h3>
    <form method="post" action="">
        <input type="hidden" name="test_quick_search" value="1">
        <p>
            <input type="text" name="quick_search_term" placeholder="Enter text to search for">
            <input type="submit" class="button" value="Test Quick Search">
        </p>
    </form>
    
    <?php
    // Process quick search
    if (isset($_POST['test_quick_search']) && !empty($_POST['quick_search_term'])) {
        $search_term = trim($_POST['quick_search_term']);
        echo '<div class="notice notice-info"><p><strong>Quick Search Results for: "' . esc_html($search_term) . '"</strong></p>';
        
        $found_anywhere = false;
        
        echo '<ul>';
        foreach ($tables as $table => $name) {
            if ($exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
                // Get text columns
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
                foreach ($columns as $column) {
                    $column_name = $column['Field'];
                    $column_type = strtolower($column['Type']);
                    
                    // Only check text columns
                    if (strpos($column_type, 'text') !== false || 
                        strpos($column_type, 'varchar') !== false || 
                        strpos($column_type, 'char') !== false || 
                        strpos($column_type, 'json') !== false || 
                        strpos($column_type, 'longtext') !== false) {
                        
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$column_name} LIKE '%" . esc_sql($search_term) . "%'");
                        if ($count > 0) {
                            echo '<li><strong>FOUND</strong> in ' . esc_html($table) . '.' . esc_html($column_name) . ': ' . esc_html($count) . ' matches</li>';
                            $found_anywhere = true;
                            
                            // Show a sample match
                            $sample_row = $wpdb->get_row("SELECT * FROM {$table} WHERE {$column_name} LIKE '%" . esc_sql($search_term) . "%' LIMIT 1", ARRAY_A);
                            if ($sample_row) {
                                $sample_text = esc_html(substr($sample_row[$column_name], 0, 100));
                                echo '<li>Sample: "' . $sample_text . '..."</li>';
                            }
                        }
                    }
                }
            }
        }
        
        if (!$found_anywhere) {
            echo '<li><strong>No matches found</strong> for: "' . esc_html($search_term) . '"</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    ?>
</div>
<?php endif; ?> 