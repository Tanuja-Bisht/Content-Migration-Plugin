/**
 * WordPress Content Migrator Admin JS
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Add any JavaScript functionality here if needed
        
        // Example: Confirm before submitting form
        $('#wp-content-migrator-form').on('submit', function() {
            if (confirm('Are you sure you want to process this file? This may take some time depending on the size of your data.')) {
                return true;
            }
            return false;
        });
    });
})(jQuery); 