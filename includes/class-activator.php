<?php
/**
 * Fired during plugin activation
 */
class Content_Migrator_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check for required PHP extensions
        $missing_extensions = array();
        
        // Check for ZipArchive (needed to process XLSX files)
        if (!class_exists('ZipArchive')) {
            $missing_extensions[] = 'ZIP';
        }
        
        // Check for SimpleXML (needed to process XLSX files)
        if (!function_exists('simplexml_load_file')) {
            $missing_extensions[] = 'SimpleXML';
        }
        
        if (!empty($missing_extensions)) {
            // Add admin notice
            add_action('admin_notices', function() use ($missing_extensions) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Content Migrator:</strong> The following PHP extensions are required but not installed: 
                        <?php echo implode(', ', $missing_extensions); ?>.
                        Please contact your server administrator to install these extensions.
                    </p>
                </div>
                <?php
            });
        }
    }
}
