<?php
/**
 * Admin page display
 */

// Get results from transient if available
$results_data = array('errors' => array(), 'results' => array(), 'success_message' => '');
$transient_key = 'content_migrator_results_' . get_current_user_id();

if (isset($_GET['processed']) && $_GET['processed'] == 1) {
    $results_data = get_transient($transient_key);
    if ($results_data) {
        delete_transient($transient_key);
    }
}

// Add notification about batch processing
// Commented out until Batch_Integration class is implemented
// echo Batch_Integration::add_batch_link_to_upload_page('');
?>

<div class="wrap content-migrator-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Search and Replace Button -->
    <div style="margin: 20px 0 10px 0;" class="module-navigation">
        <a href="<?php echo esc_url(admin_url('admin.php?page=custom-search-replace-migrator')); ?>" class="button button-primary button-hero" style="background-color: #2271b1; border-color: #2271b1; color: white; text-shadow: none; font-weight: bold;">
            Search and Replace Tool
        </a>
    </div>
    
    <?php if (!empty($results_data['success_message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php echo esc_html($results_data['success_message']); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($results_data['errors'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>Errors:</strong></p>
            <ul>
                <?php foreach ($results_data['errors'] as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="cm-content-wrapper">
        <div class="cm-main-content">
    <div class="card">
                <h2>Upload File</h2>
                <p>Upload a CSV (.csv) or Excel (.xlsx) file containing the content to migrate. Each row should contain the necessary information for a page or post.</p>
        
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="content_migrator_upload">
            <?php wp_nonce_field('content_migrator_upload', 'content_migrator_nonce'); ?>
            
            <table class="form-table">
                <tr>
                            <th scope="row"><label for="excel_file">File</label></th>
                    <td>
                                <input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx" required>
                                <p class="description">Upload a CSV (.csv) or Excel (.xlsx) file with the content to migrate.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_overwrite" value="yes">
                            Allow overwrite if slug exists
                        </label>
                        <p class="description">If checked, existing pages/posts with the same slug will be updated. Otherwise, they will be skipped.</p>
                        
                        <br>
                        
                        <label>
                            <input type="checkbox" name="use_batch_processing" value="yes">
                            Process in background
                        </label>
                        <p class="description">If checked, the file will be processed in batches in the background, allowing you to continue working without timeouts.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Upload and Process</button>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=content_migrator_download_sample&nonce=' . wp_create_nonce('content_migrator_download_sample'))); ?>" class="button">Download Sample CSV</a>
            </p>
        </form>
    </div>
    
    <?php if (!empty($results_data['results'])): ?>
        <?php
        // Check if there are any valid results
        $has_valid_results = false;
        foreach ($results_data['results'] as $result) {
            if (is_array($result) && isset($result['status']) && $result['status'] !== 'unknown') {
                $has_valid_results = true;
                break;
            }
        }
        
        // Only show results table if there are valid results
        if ($has_valid_results):
        ?>
        <div class="card">
            <h2>Migration Results</h2>
                    <div style="max-height: 400px; overflow: auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Directly handle array values to prevent PHP errors
                    foreach ($results_data['results'] as $result): 
                        // Skip items with array access errors
                        if (is_array($result) && isset($result['message']) && strpos($result['message'], 'access array') !== false) {
                            continue;
                        }
                        
                        // Ensure all required keys exist to prevent notices
                        if (!is_array($result)) {
                            continue; // Skip non-array items
                        }
                        
                        // Skip items with unknown status
                        if (isset($result['status']) && $result['status'] === 'unknown') {
                            continue;
                        }
                        
                        $result_row = isset($result['row']) ? $result['row'] : '-';
                        $result_title = isset($result['title']) ? $result['title'] : '-';
                        $result_slug = isset($result['slug']) ? $result['slug'] : '-';
                        $result_status = isset($result['status']) ? $result['status'] : 'unknown';
                        $result_message = isset($result['message']) ? $result['message'] : '-';
                    ?>
                        <tr>
                            <td><?php echo esc_html($result_row); ?></td>
                            <td><?php echo esc_html($result_title); ?></td>
                            <td><?php echo esc_html($result_slug); ?></td>
                            <td>
                                <?php 
                                $status_class = '';
                                switch ($result_status) {
                                    case 'success':
                                        $status_class = 'success';
                                        break;
                                    case 'error':
                                        $status_class = 'error';
                                        break;
                                    case 'skipped':
                                        $status_class = 'warning';
                                        break;
                                    default:
                                        $status_class = 'warning';
                                }
                                ?>
                                <span class="status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($result_status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($result_message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                    </div>
        </div>
        <?php else: ?>
            <?php if (isset($_GET['processed']) && $_GET['processed'] == 1): ?>
            <div class="card">
                <h2>Migration Results</h2>
                <p>No valid migration results were found. Please check that your CSV file is properly formatted and contains valid data.</p>
                <p>Remember to use the "Download Sample CSV" button to get a template with the correct format.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
        </div>
    
        <div class="cm-sidebar">
    <div class="card">
        <h2>Instructions</h2>
        <ol>
                    <li><strong>Download the sample CSV</strong> file to see the required format.</li>
                    <li><strong>Prepare your file</strong> with the following columns:
                <ul>
                    <li><strong>Migrate:</strong> Set to "MIGRATE" for rows you want to process.</li>
                    <li><strong>Type:</strong> Either "page" or "post".</li>
                    <li><strong>Old URL:</strong> The URL of the existing content to scrape.</li>
                    <li><strong>New URL:</strong> The slug for the new page/post. Use path format for hierarchical pages (e.g., parent/child).</li>
                    <li><strong>Parent URL:</strong> For pages only - The URL of the parent page (e.g., "/parent" for a child at "/parent/child").</li>
                    <li><strong>Categories:</strong> For posts only - Comma-separated list of categories (e.g., "Blog, News, Legal").</li>
                    <li><strong>H1:</strong> The main heading to add at the top of the content for pages, or the post title for posts.</li>
                    <li><strong>Page/Post Title:</strong> The title of the page (for pages) or alternative title (for posts).</li>
                    <li><strong>Meta Title:</strong> The SEO title for the page/post.</li>
                    <li><strong>Image:</strong> Set to "Yes" to extract the first image as featured image, or "No" to skip. You can also provide a direct image URL.</li>
                    <li><strong>Process Images:</strong> Set to "Yes" to process images in content.</li>
                </ul>
            </li>
                    <li><strong>Upload your file</strong> and choose whether to allow overwriting existing content.</li>
                    <li><strong>Click "Upload and Process"</strong> to start the migration.</li>
                    <li><strong>Review the results</strong> table for any errors or issues.</li>
        </ol>
            </div>
            
            <div class="card">
                <h2>Excel Formula for Parent URLs</h2>
                <p>To automatically generate parent_url values in Excel or Google Sheets:</p>
                <ol>
                    <li>Copy this formula to the parent_url column (adjust cell references if needed):</li>
                    <code style="display:block; background:#f6f6f6; padding:8px; margin:8px 0; word-break:break-all; font-size:12px;">
                        =IF(LEN(D2)-LEN(SUBSTITUTE(D2,"/",""))<=2, "", LEFT(D2,FIND("~",SUBSTITUTE(D2,"/","~",LEN(D2)-LEN(SUBSTITUTE(D2,"/",""))-1))-1) & "/")
                    </code>
                    <li>Apply the formula to all rows (the formula extracts parent path from new_url)</li>
                    <li>Copy the formula column and "Paste as Values" before saving as CSV</li>
                </ol>
            </div>
        </div>
        
        <div class="cm-sidebar-right">
            <div class="card">
                <h2>Hierarchical Structure</h2>
                <p>Pages will be organized in a hierarchical structure:</p>
                <ul>
                    <li>Process rows are sorted to ensure parent pages are created first</li>
                    <li>If a parent_url is specified, it will be used directly</li>
                    <li>If no parent_url is provided, it will determine parents based on URL structure</li>
                </ul>
                <p>For posts, use categories instead of parent_url to organize content.</p>
            </div>
            
            <div class="card">
                <h2>Special Rules for Posts</h2>
                <p>When the <strong>Type</strong> column is set to "post", the following special rules apply:</p>
                <ul>
                    <li>The <strong>H1</strong> value is used as the post title, and is NOT added to the content.</li>
                    <li>Any H1 tags in the scraped content are converted to H2 tags.</li>
                    <li>The publication date is extracted from the source page when possible.</li>
                    <li>Use the <strong>Categories</strong> column to assign categories (comma-separated list).</li>
                    <li>If no categories are provided, they are determined based on URL structure.</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Migration Results Explained</h2>
                <p>The "Migration completed" message shows three counts:</p>
                <ul>
                    <li><strong>Created:</strong> Only newly created pages or posts</li>
                    <li><strong>Skipped:</strong> All existing content that was found (nothing will be overwritten)</li>
                    <li><strong>Failed:</strong> Content that could not be processed due to errors</li>
                </ul>
                <p>To protect your content, any pages or posts that already exist will be skipped rather than updated.</p>
            </div>
            
            <div class="card">
                <h2>How It Works</h2>
                <p>This tool:</p>
                <ul>
                    <li>Reads content from source URLs in your CSV file</li>
                    <li>Extracts and cleans content to remove navigation, headers, footers</li>
                    <li>Creates pages/posts with the proper hierarchy</li>
                    <li>Handles images, links, and other media elements</li>
                    <li>Ensures parents are created before children</li>
                    <li>Organizes posts by categories</li>
                </ul>
                <p><strong>Note:</strong> Always backup your database before running in non-simulation mode!</p>
            </div>
        </div>
    </div>
</div>

<style>
.cm-content-wrapper {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
    width: 100%;
    max-width: 100%;
}
.cm-main-content {
    flex: 1;
    min-width: 500px;
    padding: 0 10px;
    box-sizing: border-box;
}
.cm-sidebar, .cm-sidebar-right {
    flex: 1;
    min-width: 300px;
    padding: 0 10px;
    box-sizing: border-box;
}
.status-success {
    color: #46b450;
    font-weight: bold;
}
.status-error {
    color: #dc3232;
    font-weight: bold;
}
.status-warning {
    color: #ffb900;
    font-weight: bold;
}
.card {
    padding: 15px 20px;
    box-sizing: border-box;
}
.form-table {
    width: 100%;
}
.form-table th {
    width: 180px;
    vertical-align: top;
    text-align: left;
    padding: 15px 10px 15px 0;
}
.form-table td {
    padding: 15px 0;
}
.wp-list-table {
    width: 100%;
    border-spacing: 0;
}
ol, ul {
    padding-left: 20px;
    margin-left: 0;
}
li {
    margin-bottom: 8px;
}
p.description {
    margin-top: 5px;
}
@media screen and (max-width: 1400px) {
    .cm-content-wrapper {
        flex-wrap: wrap;
    }
    .cm-main-content {
        flex: 1 0 100%;
        margin-bottom: 20px;
    }
    .cm-sidebar, .cm-sidebar-right {
        flex: 1 0 45%;
    }
}
@media screen and (max-width: 900px) {
    .cm-sidebar, .cm-sidebar-right {
        flex: 1 0 100%;
    }
}
#wpfooter {
    display: none;
}
</style>