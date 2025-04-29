<?php
/**
 * Fired during plugin deactivation
 */
class Content_Migrator_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clean up transients
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_content_migrator_results_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_content_migrator_results_%'");
    }
}
