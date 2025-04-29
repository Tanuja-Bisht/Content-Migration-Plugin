<?php
/**
 * Create Document admin page display
 */
?>

<div class="wrap content-migrator-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="cm-content-wrapper">
        <div class="cm-main-content">
            <div class="card">
                <h2>Create New Document</h2>
                <p>Use this form to create a new document directly without the need for content migration from an external source.</p>
                
                <form method="post" id="create-document-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="document_type">Document Type</label></th>
                            <td>
                                <select name="document_type" id="document_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="page">Page</option>
                                    <option value="post">Post</option>
                                </select>
                                <p class="description">Select whether to create a page or blog post.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="document_title">Title</label></th>
                            <td>
                                <input type="text" name="document_title" id="document_title" class="regular-text" required>
                                <p class="description">The title of the page or post.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="document_slug">Slug</label></th>
                            <td>
                                <input type="text" name="document_slug" id="document_slug" class="regular-text">
                                <p class="description">The URL slug for the page or post. Leave blank to generate from title.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="document_content">Content</label></th>
                            <td>
                                <?php 
                                // Use WordPress built-in editor
                                wp_editor('', 'document_content', array(
                                    'textarea_name' => 'document_content',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                ));
                                ?>
                                <p class="description">The content of the page or post. You can use the editor to format text, add media, etc.</p>
                            </td>
                        </tr>
                        <tr class="post-options" style="display: none;">
                            <th scope="row"><label for="document_category">Category</label></th>
                            <td>
                                <?php
                                // Display categories dropdown (only for posts)
                                $categories = get_categories(array('hide_empty' => false));
                                if (!empty($categories)) {
                                    echo '<select name="document_category" id="document_category">';
                                    echo '<option value="">-- Select Category --</option>';
                                    foreach ($categories as $category) {
                                        echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                    }
                                    echo '</select>';
                                } else {
                                    echo '<p>No categories found. <a href="' . admin_url('edit-tags.php?taxonomy=category') . '">Create a category</a>.</p>';
                                }
                                ?>
                                <p class="description">Select a category for the post.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="meta_title">SEO Title</label></th>
                            <td>
                                <input type="text" name="meta_title" id="meta_title" class="regular-text">
                                <p class="description">SEO title for the page or post. Leave blank to use the document title.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="meta_description">Meta Description</label></th>
                            <td>
                                <textarea name="meta_description" id="meta_description" rows="3" class="large-text"></textarea>
                                <p class="description">Meta description for the page or post. Recommended length: 50-160 characters.</p>
                            </td>
                        </tr>
                        <tr class="page-options" style="display: none;">
                            <th scope="row"><label for="parent_page">Parent Page</label></th>
                            <td>
                                <?php
                                // Display parent page dropdown (only for pages)
                                wp_dropdown_pages(array(
                                    'name' => 'parent_page',
                                    'show_option_none' => '-- No Parent --',
                                    'option_none_value' => '0',
                                    'sort_column' => 'menu_order, post_title',
                                ));
                                ?>
                                <p class="description">Select a parent page if this is a child page.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="create-document-button">Create Document</button>
                    </p>
                </form>
                
                <div id="document-result" style="display: none;">
                    <div class="notice notice-success">
                        <p><strong>Document created successfully!</strong></p>
                        <p>
                            <a href="#" id="view-document" class="button" target="_blank">View Document</a>
                            <a href="#" id="edit-document" class="button" target="_blank">Edit Document</a>
                            <button id="create-another" class="button">Create Another Document</button>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="cm-sidebar">
            <div class="card">
                <h2>Instructions</h2>
                <ol>
                    <li><strong>Select document type</strong> (page or post).</li>
                    <li><strong>Enter a title</strong> for your document.</li>
                    <li><strong>Provide content</strong> using the WordPress editor.</li>
                    <li><strong>Add SEO details</strong> (optional) for better search engine visibility.</li>
                    <li><strong>Select a parent page</strong> (for pages) or <strong>category</strong> (for posts) if needed.</li>
                    <li><strong>Click "Create Document"</strong> to publish your content.</li>
                </ol>
            </div>
            
            <div class="card">
                <h2>Tips</h2>
                <ul>
                    <li>Use the media buttons to add images, videos or other media to your content.</li>
                    <li>You can format text using the rich text editor tools.</li>
                    <li>For pages, consider the hierarchical structure by selecting a parent page if appropriate.</li>
                    <li>For posts, always select a category to ensure proper organization.</li>
                    <li>Write compelling meta descriptions to improve click-through rates from search results.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide options based on document type
    $('#document_type').on('change', function() {
        var type = $(this).val();
        if (type === 'post') {
            $('.post-options').show();
            $('.page-options').hide();
        } else if (type === 'page') {
            $('.post-options').hide();
            $('.page-options').show();
        } else {
            $('.post-options, .page-options').hide();
        }
    });
    
    // Handle form submission
    $('#create-document-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        $('#create-document-button').prop('disabled', true).text('Creating...');
        
        // Get form data
        var formData = {
            'action': 'content_migrator_create_document',
            'document_type': $('#document_type').val(),
            'document_title': $('#document_title').val(),
            'document_slug': $('#document_slug').val(),
            'document_content': $('#document_content').val(),
            'meta_title': $('#meta_title').val(),
            'meta_description': $('#meta_description').val(),
            'nonce': '<?php echo wp_create_nonce('content_migrator_create_document'); ?>'
        };
        
        // Add type-specific fields
        if (formData.document_type === 'post') {
            formData.document_category = $('#document_category').val();
        } else if (formData.document_type === 'page') {
            formData.parent_page = $('#parent_page').val();
        }
        
        // Send AJAX request
        $.post(ajaxurl, formData, function(response) {
            // Reset button
            $('#create-document-button').prop('disabled', false).text('Create Document');
            
            if (response.success) {
                // Show success message
                $('#document-result').show();
                $('#create-document-form').hide();
                
                // Set links to view/edit
                $('#view-document').attr('href', response.data.view_url);
                $('#edit-document').attr('href', response.data.edit_url);
            } else {
                // Show error message
                alert('Error: ' + response.data.message);
            }
        }).fail(function() {
            $('#create-document-button').prop('disabled', false).text('Create Document');
            alert('An unexpected error occurred. Please try again.');
        });
    });
    
    // Reset form for creating another document
    $('#create-another').on('click', function(e) {
        e.preventDefault();
        $('#document-result').hide();
        $('#create-document-form').show();
        $('#create-document-form')[0].reset();
        $('#document_content').val(''); // Clear editor content
        $('.post-options, .page-options').hide();
    });
});
</script>

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
@media screen and (max-width: 960px) {
    .cm-main-content, .cm-sidebar {
        flex: 1 0 100%;
    }
}
</style> 