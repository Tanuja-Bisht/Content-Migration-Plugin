
/**
 * Sheet Converter JavaScript
 */
(function($) {
    'use strict';
    
    // Variables to store data between steps
    let fileId = '';
    let conversionId = '';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // File upload form handler
        $('#wcm-upload-form').on('submit', function(e) {
            e.preventDefault();
            
            const fileInput = $('#wcm-client-file')[0];
            if (!fileInput.files.length) {
                alert(wcm_sheet_converter.i18n.empty_file);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'wcm_upload_client_sheet');
            formData.append('nonce', wcm_sheet_converter.nonce);
            formData.append('wcm_client_file', fileInput.files[0]);
            
            // Show progress indicator
            $('#wcm-upload-button').hide();
            $('#wcm-upload-progress').show();
            
            // Send AJAX request
            $.ajax({
                url: wcm_sheet_converter.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Hide progress indicator
                    $('#wcm-upload-button').show();
                    $('#wcm-upload-progress').hide();
                    
                    if (response.success) {
                        // Store file ID for later
                        fileId = response.data.file_id;
                        
                        // Update UI with preview
                        $('#wcm-preview-content').html(response.data.preview);
                        $('#wcm-file-preview').show();
                        $('#wcm-conversion-options').show();
                        
                        // If domain detected, fill the domain field
                        if (response.data.detected_domain) {
                            $('#wcm-strip-domain').val(response.data.detected_domain);
                        }
                    } else {
                        alert(wcm_sheet_converter.i18n.error + ' ' + response.data);
                    }
                },
                error: function() {
                    // Hide progress indicator
                    $('#wcm-upload-button').show();
                    $('#wcm-upload-progress').hide();
                    
                    alert(wcm_sheet_converter.i18n.error + ' ' + 'Server error');
                }
            });
        });
        
        // Show/hide domain field based on conversion type
        $('#wcm-conversion-type').on('change', function() {
            const conversionType = $(this).val();
            
            if (conversionType) {
                $('#wcm-domain-field').show();
            } else {
                $('#wcm-domain-field').hide();
            }
        });
        
        // Convert form handler
        $('#wcm-convert-form').on('submit', function(e) {
            e.preventDefault();
            
            const conversionType = $('#wcm-conversion-type').val();
            if (!conversionType) {
                alert(wcm_sheet_converter.i18n.error + ' ' + 'Please select a conversion type');
                return;
            }
            
            // Show progress indicator
            $('#wcm-convert-button').hide();
            $('#wcm-convert-progress').show();
            
            // Send AJAX request
            $.ajax({
                url: wcm_sheet_converter.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcm_convert_sheet',
                    nonce: wcm_sheet_converter.nonce,
                    file_id: fileId,
                    conversion_type: conversionType,
                    strip_domain: $('#wcm-strip-domain').val()
                },
                success: function(response) {
                    // Hide progress indicator
                    $('#wcm-convert-button').show();
                    $('#wcm-convert-progress').hide();
                    
                    if (response.success) {
                        // Store conversion ID for later
                        conversionId = response.data.conversion_id;
                        
                        // Update UI with preview
                        $('#wcm-result-message').html('<div class="notice notice-success"><p>' + wcm_sheet_converter.i18n.success + '</p></div>');
                        $('#wcm-result-preview').html(response.data.preview);
                        $('#wcm-conversion-result').show();
                        
                        // Scroll to result
                        $('html, body').animate({
                            scrollTop: $('#wcm-conversion-result').offset().top - 100
                        }, 500);
                    } else {
                        alert(wcm_sheet_converter.i18n.error + ' ' + response.data);
                    }
                },
                error: function() {
                    // Hide progress indicator
                    $('#wcm-convert-button').show();
                    $('#wcm-convert-progress').hide();
                    
                    alert(wcm_sheet_converter.i18n.error + ' ' + 'Server error');
                }
            });
        });
        
        // Download button handler
        $('#wcm-download-button').on('click', function() {
            if (!conversionId) {
                alert(wcm_sheet_converter.i18n.error + ' ' + 'No conversion data available');
                return;
            }
            
            window.location.href = wcm_sheet_converter.ajax_url + '?action=wcm_download_converted_file&nonce=' + wcm_sheet_converter.nonce + '&conversion_id=' + conversionId;
        });
        
        // Start over button handler
        $('#wcm-start-over-button').on('click', function() {
            // Reset UI
            $('#wcm-file-preview').hide();
            $('#wcm-conversion-options').hide();
            $('#wcm-conversion-result').hide();
            $('#wcm-preview-content').html('');
            $('#wcm-result-preview').html('');
            $('#wcm-result-message').html('');
            $('#wcm-conversion-type').val('');
            $('#wcm-strip-domain').val('');
            $('#wcm-client-file').val('');
            
            // Reset variables
            fileId = '';
            conversionId = '';
            
            // Scroll to top
            $('html, body').animate({
                scrollTop: $('.wcm-sheet-converter-wrap').offset().top - 50
            }, 500);
        });
    });
})(jQuery);
