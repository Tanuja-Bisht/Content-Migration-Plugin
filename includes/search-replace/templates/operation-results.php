<?php
/**
 * Template for showing operation results
 */

// Prevent direct access
if (!defined('WPINC') && !defined('WP_USE_THEMES')) {
    die;
}
?>

<h2>Search and Replace Results</h2>
<p style="color: #155724; font-weight: bold;">Operation completed successfully.</p>

<table class="widefat">
    <tr>
        <th>Tables Processed:</th>
        <td><?php echo esc_html($results['tables_processed']); ?></td>
    </tr>
    <tr>
        <th>Rows Processed:</th>
        <td><?php echo esc_html($results['rows_processed']); ?></td>
    </tr>
    <tr>
        <th>Changes Made:</th>
        <td><?php echo esc_html($results['changes']); ?></td>
    </tr>
    <tr>
        <th>Errors:</th>
        <td><?php echo esc_html($results['errors']); ?></td>
    </tr>
</table>

<div class="notice notice-success" style="background-color: #d4edda; color: #155724; border-color: #c3e6cb;">
    <p>Changes have been applied to your database. To perform another search and replace operation, click the button below.</p>
</div>

<p>
    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=custom-search-replace-migrator')); ?>">
        <?php wp_nonce_field('wp_content_migrator_reset', 'wp_content_migrator_reset_nonce'); ?>
        <input type="hidden" name="reset_search_replace" value="1">
        <button type="submit" class="button button-primary">Start New Search/Replace</button>
    </form>
</p> 