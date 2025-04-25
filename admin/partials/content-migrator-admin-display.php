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
?>

<div class="wrap content-migrator-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
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
                <p>Upload an Excel (.xlsx) or CSV (.csv) file containing the content to migrate. Each row should contain the necessary information for a page or post.</p>
        
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="content_migrator_upload">
            <?php wp_nonce_field('content_migrator_upload', 'content_migrator_nonce'); ?>
            
            <table class="form-table">
                <tr>
                            <th scope="row"><label for="excel_file">File</label></th>
                    <td>
                                <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.csv" required>
                                <p class="description">Upload an Excel (.xlsx) or CSV (.csv) file with the content to migrate.</p>
                                <p class="description"><strong>Tip:</strong> If you're having trouble with Excel files, try downloading our sample CSV and using that format.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_overwrite" value="1">
                            Allow overwrite if slug exists
                        </label>
                        <p class="description">If checked, existing pages/posts with the same slug will be updated. Otherwise, they will be skipped.</p>
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
                    <?php foreach ($results_data['results'] as $result): ?>
                        <tr>
                            <td><?php echo esc_html($result['row']); ?></td>
                            <td><?php echo esc_html($result['title']); ?></td>
                            <td><?php echo esc_html($result['slug']); ?></td>
                            <td>
                                <?php 
                                $status_class = '';
                                switch ($result['status']) {
                                    case 'success':
                                        $status_class = 'success';
                                        break;
                                    case 'error':
                                        $status_class = 'error';
                                        break;
                                    case 'skipped':
                                        $status_class = 'warning';
                                        break;
                                }
                                ?>
                                <span class="status-<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($result['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($result['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                    </div>
        </div>
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
                    <li><strong>Menu Name:</strong> The name to use in menus (for reference only).</li>
                    <li><strong>Old URL:</strong> The URL of the existing content to scrape.</li>
                    <li><strong>New URL:</strong> The slug for the new page/post. Use path format for hierarchical pages (e.g., parent/child).</li>
                    <li><strong>Meta Title:</strong> The SEO title for the page/post.</li>
                            <li><strong>H1:</strong> The main heading to add at the top of the content for pages, or the post title for posts.</li>
                            <li><strong>Page/Post Title:</strong> The title of the page (for pages) or alternative title (for posts).</li>
                    <li><strong>Type:</strong> Either "page" or "post".</li>
                </ul>
            </li>
                    <li><strong>Upload your file</strong> and choose whether to allow overwriting existing content.</li>
                    <li><strong>Click "Upload and Process"</strong> to start the migration.</li>
                    <li><strong>Review the results</strong> table for any errors or issues.</li>
        </ol>
            </div>
            
            <div class="card">
                <h2>Special Rules for Posts</h2>
                <p>When the <strong>Type</strong> column is set to "post", the following special rules apply:</p>
                <ul>
                    <li>The <strong>H1</strong> value is used as the post title, and is NOT added to the content.</li>
                    <li>Any H1 tags in the scraped content are converted to H2 tags.</li>
                    <li>The publication date is extracted from the source page when possible.</li>
                    <li>Categories are automatically assigned based on the URL structure.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.cm-content-wrapper {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
}
.cm-main-content {
    flex: 3;
    min-width: 500px;
    padding: 0 10px;
    box-sizing: border-box;
}
.cm-sidebar {
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
@media screen and (max-width: 960px) {
    .cm-main-content, .cm-sidebar {
        flex: 1 0 100%;
    }
}
</style>
