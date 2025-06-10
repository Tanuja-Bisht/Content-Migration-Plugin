/**
 * Batch Processing Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Constants
    const REFRESH_INTERVAL = 10000; // 10 seconds
    
    // Store active batch information
    let activeBatchId = 0;
    let refreshTimer = null;
    let isPolling = false;
    
    /**
     * Initialize the batch admin UI
     */
    function init() {
        // Tab switching
        $('.wcm-batch-tab-link').on('click', switchTab);
        
        // Form submission
        $('#wcm-batch-upload-form').on('submit', handleFormSubmit);
        
        // Batch table actions
        $('#wcm-batch-table-container').on('click', '.wcm-batch-view', viewBatchDetails);
        
        // Batch detail actions
        $('#wcm-batch-back-to-list').on('click', backToList);
        $('#wcm-batch-refresh').on('click', refreshBatchStatus);
        $('#wcm-batch-retry-all').on('click', retryFailedItems);
        $('#wcm-batch-cancel').on('click', cancelBatch);
        
        // Upload result actions
        $('#wcm-batch-upload-another').on('click', resetUploadForm);
        $('#wcm-batch-view-status').on('click', function() {
            // Switch to batches tab and view the newly created batch
            $('.wcm-batch-tab-link[data-tab="batches"]').trigger('click');
            viewBatchDetails(null, activeBatchId);
        });
        
        // Error retry action
        $('#wcm-batch-try-again').on('click', resetUploadForm);
        
        // Initialize any URL parameters
        initFromUrlParams();
    }
    
    /**
     * Check URL parameters for batch ID
     */
    function initFromUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const batchId = urlParams.get('batch_id');
        
        if (batchId) {
            $('.wcm-batch-tab-link[data-tab="batches"]').trigger('click');
            viewBatchDetails(null, batchId);
        }
    }
    
    /**
     * Switch between tabs
     */
    function switchTab(e) {
        e.preventDefault();
        
        const $this = $(this);
        const tab = $this.data('tab');
        
        // Update active tab link
        $('.wcm-batch-tab-link').removeClass('active');
        $this.addClass('active');
        
        // Show active tab content
        $('.wcm-batch-tab-content').removeClass('active');
        $('#wcm-batch-tab-' + tab).addClass('active');
        
        // If switching to batches tab, refresh the list
        if (tab === 'batches') {
            refreshBatchList();
        }
        
        // Clear any polling if switching away from batch details
        if (tab === 'upload' && refreshTimer) {
            clearTimeout(refreshTimer);
            isPolling = false;
        }
    }
    
    /**
     * Handle form submission (file upload)
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const $form = $(this);
        const formData = new FormData($form[0]);
        
        // Show upload progress
        $('#wcm-batch-upload-button').hide();
        $('#wcm-batch-upload-progress').show();
        
        // Submit via AJAX
        $.ajax({
            url: wcm_batch.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Store the active batch ID for reference
                    activeBatchId = response.data.batch_id;
                    
                    // Show success message
                    $('#wcm-batch-upload-form').hide();
                    $('#wcm-batch-upload-result').show();
                } else {
                    // Show error message
                    $('#wcm-batch-upload-error-message').text(response.data.message);
                    $('#wcm-batch-upload-error').show();
                    resetUploadForm();
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                $('#wcm-batch-upload-error-message').text('Ajax error: ' + error);
                $('#wcm-batch-upload-error').show();
                resetUploadForm();
            }
        });
    }
    
    /**
     * Reset the upload form
     */
    function resetUploadForm() {
        // Reset form
        $('#wcm-batch-upload-form')[0].reset();
        $('#wcm-batch-upload-form').show();
        $('#wcm-batch-upload-button').show();
        $('#wcm-batch-upload-progress').hide();
        
        // Hide result/error messages
        $('#wcm-batch-upload-result').hide();
        $('#wcm-batch-upload-error').hide();
    }
    
    /**
     * Refresh the batch list
     */
    function refreshBatchList() {
        // Show loading state
        $('#wcm-batch-table-container').addClass('loading');
        
        // Make AJAX request to get the latest batch list
        $.ajax({
            url: wcm_batch.ajax_url,
            type: 'POST',
            data: {
                action: 'wcm_get_batch_list',
                nonce: wcm_batch.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the batch table container
                    $('#wcm-batch-table-container').html(response.data.html);
                }
                $('#wcm-batch-table-container').removeClass('loading');
            },
            error: function() {
                $('#wcm-batch-table-container').removeClass('loading');
            }
        });
    }
    
    /**
     * View batch details
     */
    function viewBatchDetails(e, batchId) {
        if (e) {
            e.preventDefault();
            batchId = $(this).data('batch-id');
        }
        
        // Store the active batch ID
        activeBatchId = batchId;
        
        // Update URL with batch ID for bookmarking
        const url = new URL(window.location);
        url.searchParams.set('batch_id', batchId);
        window.history.pushState({}, '', url);
        
        // Show the batch details section, hide the list
        $('.wcm-batch-list').hide();
        $('#wcm-batch-details').show();
        
        // Reset batch items table
        $('#wcm-batch-items-table').html('<div class="spinner is-active"></div><p>Loading batch items...</p>');
        
        // Load batch details
        loadBatchDetails(batchId);
        
        // Start polling for updates
        startPolling();
    }
    
    /**
     * Back to batch list
     */
    function backToList(e) {
        e.preventDefault();
        
        // Stop polling for updates
        stopPolling();
        
        // Remove batch ID from URL
        const url = new URL(window.location);
        url.searchParams.delete('batch_id');
        window.history.pushState({}, '', url);
        
        // Show the batch list, hide details
        $('.wcm-batch-list').show();
        $('#wcm-batch-details').hide();
        
        // Refresh the batch list
        refreshBatchList();
    }
    
    /**
     * Load batch details
     */
    function loadBatchDetails(batchId) {
        $.ajax({
            url: wcm_batch.ajax_url,
            type: 'POST',
            data: {
                action: 'wcm_get_batch_status',
                nonce: wcm_batch.nonce,
                batch_id: batchId
            },
            success: function(response) {
                if (response.success) {
                    updateBatchDetailsUI(response.data);
                } else {
                    showBatchError('Failed to load batch details');
                }
            },
            error: function() {
                showBatchError('AJAX error when loading batch details');
            }
        });
    }
    
    /**
     * Update batch details UI
     */
    function updateBatchDetailsUI(data) {
        const batch = data.batch;
        const stats = data.stats;
        
        // Update basic info
        $('#wcm-batch-details-title').text(batch.file_name);
        $('#wcm-batch-status').text(batch.status.charAt(0).toUpperCase() + batch.status.slice(1));
        $('#wcm-batch-status').attr('class', 'wcm-status-' + batch.status);
        
        // Update statistics
        $('#wcm-batch-total').text(stats.total_items);
        $('#wcm-batch-processed').text(parseInt(stats.success_items) + parseInt(stats.skipped_items));
        $('#wcm-batch-failed').text(stats.failed_items);
        
        // Calculate progress
        const totalProcessed = parseInt(stats.success_items) + parseInt(stats.skipped_items) + parseInt(stats.failed_items);
        const progressPercent = stats.total_items > 0 ? Math.round((totalProcessed / stats.total_items) * 100) : 0;
        
        // Update progress
        $('#wcm-batch-progress').text(progressPercent + '%');
        $('#wcm-progress-bar-fill').css('width', progressPercent + '%');
        
        // Update action buttons based on status
        if (batch.status === 'processing' || batch.status === 'pending') {
            $('#wcm-batch-cancel').show();
            $('#wcm-batch-retry-all').hide();
        } else if (batch.status === 'completed' && parseInt(stats.failed_items) > 0) {
            $('#wcm-batch-cancel').hide();
            $('#wcm-batch-retry-all').show();
        } else {
            $('#wcm-batch-cancel').hide();
            $('#wcm-batch-retry-all').hide();
        }
        
        // Update items table
        updateBatchItemsTable(data.items);
    }
    
    /**
     * Update batch items table
     */
    function updateBatchItemsTable(items) {
        if (!items || items.length === 0) {
            $('#wcm-batch-items-table').html('<p>No items found in this batch.</p>');
            return;
        }
        
        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>ID</th>';
        html += '<th>Status</th>';
        html += '<th>Started</th>';
        html += '<th>Completed</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        items.forEach(function(item) {
            html += '<tr data-item-id="' + item.id + '">';
            html += '<td>' + item.id + '</td>';
            html += '<td><span class="wcm-status wcm-status-' + item.status + '">' + getStatusLabel(item.status) + '</span></td>';
            html += '<td>' + (item.started_at ? formatTimestamp(item.started_at) : '-') + '</td>';
            html += '<td>' + (item.completed_at ? formatTimestamp(item.completed_at) : '-') + '</td>';
            html += '<td>';
            
            // Add action buttons based on status
            if (item.status === 'failed') {
                html += '<button class="button wcm-retry-item" data-item-id="' + item.id + '">' + wcm_batch.i18n.retry + '</button>';
                
                // If we have an error message, add a details button
                if (item.error_message) {
                    html += ' <button class="button wcm-view-error" data-error="' + escapeHtml(item.error_message) + '">' + wcm_batch.i18n.error + '</button>';
                }
            } else if (item.status === 'success' && item.post_id) {
                html += '<a href="' + getEditUrl(item.post_id) + '" target="_blank" class="button">' + wcm_batch.i18n.view + '</a>';
            }
            
            html += '</td></tr>';
        });
        
        html += '</tbody></table>';
        
        $('#wcm-batch-items-table').html(html);
        
        // Add event handlers for the new buttons
        $('.wcm-retry-item').on('click', function() {
            retryItem($(this).data('item-id'));
        });
        
        $('.wcm-view-error').on('click', function() {
            alert($(this).data('error'));
        });
    }
    
    /**
     * Get edit URL for a post
     */
    function getEditUrl(postId) {
        return ajaxurl.replace('admin-ajax.php', 'post.php?post=' + postId + '&action=edit');
    }
    
    /**
     * Get status label
     */
    function getStatusLabel(status) {
        if (wcm_batch.i18n[status]) {
            return wcm_batch.i18n[status];
        }
        return status.charAt(0).toUpperCase() + status.slice(1);
    }
    
    /**
     * Format timestamp
     */
    function formatTimestamp(timestamp) {
        if (!timestamp) return '-';
        const date = new Date(timestamp.replace(' ', 'T'));
        return date.toLocaleString();
    }
    
    /**
     * Show batch error
     */
    function showBatchError(message) {
        $('#wcm-batch-items-table').html('<div class="notice notice-error"><p>' + message + '</p></div>');
    }
    
    /**
     * Refresh batch status
     */
    function refreshBatchStatus(e) {
        if (e) e.preventDefault();
        
        if (!activeBatchId) return;
        
        loadBatchDetails(activeBatchId);
    }
    
    /**
     * Start polling for batch status updates
     */
    function startPolling() {
        if (isPolling) return;
        
        isPolling = true;
        refreshTimer = setTimeout(function pollStatus() {
            refreshBatchStatus();
            
            // Continue polling if still on details page and batch is processing
            const statusText = $('#wcm-batch-status').text().toLowerCase();
            if ($('#wcm-batch-details').is(':visible') && (statusText === 'processing' || statusText === 'pending')) {
                refreshTimer = setTimeout(pollStatus, REFRESH_INTERVAL);
            } else {
                isPolling = false;
            }
        }, REFRESH_INTERVAL);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }
        isPolling = false;
    }
    
    /**
     * Retry all failed items in a batch
     */
    function retryFailedItems(e) {
        e.preventDefault();
        
        if (!activeBatchId) return;
        
        $(this).prop('disabled', true).text(wcm_batch.i18n.processing);
        
        $.ajax({
            url: wcm_batch.ajax_url,
            type: 'POST',
            data: {
                action: 'wcm_retry_batch',
                nonce: wcm_batch.nonce,
                batch_id: activeBatchId
            },
            success: function(response) {
                if (response.success) {
                    refreshBatchStatus();
                    startPolling();
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to retry items'));
                }
                $('#wcm-batch-retry-all').prop('disabled', false).text('Retry Failed Items');
            },
            error: function() {
                alert('AJAX error when retrying items');
                $('#wcm-batch-retry-all').prop('disabled', false).text('Retry Failed Items');
            }
        });
    }
    
    /**
     * Retry a single item
     */
    function retryItem(itemId) {
        if (!itemId) return;
        
        const $button = $('.wcm-retry-item[data-item-id="' + itemId + '"]');
        $button.prop('disabled', true).text(wcm_batch.i18n.processing);
        
        $.ajax({
            url: wcm_batch.ajax_url,
            type: 'POST',
            data: {
                action: 'wcm_retry_failed_item',
                nonce: wcm_batch.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    refreshBatchStatus();
                    startPolling();
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to retry item'));
                    $button.prop('disabled', false).text(wcm_batch.i18n.retry);
                }
            },
            error: function() {
                alert('AJAX error when retrying item');
                $button.prop('disabled', false).text(wcm_batch.i18n.retry);
            }
        });
    }
    
    /**
     * Cancel a batch
     */
    function cancelBatch(e) {
        e.preventDefault();
        
        if (!activeBatchId) return;
        
        if (!confirm(wcm_batch.i18n.confirm_cancel)) {
            return;
        }
        
        $(this).prop('disabled', true).text(wcm_batch.i18n.processing);
        
        $.ajax({
            url: wcm_batch.ajax_url,
            type: 'POST',
            data: {
                action: 'wcm_cancel_batch',
                nonce: wcm_batch.nonce,
                batch_id: activeBatchId
            },
            success: function(response) {
                if (response.success) {
                    refreshBatchStatus();
                    stopPolling();
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to cancel batch'));
                }
                $('#wcm-batch-cancel').prop('disabled', false).text('Cancel Batch');
            },
            error: function() {
                alert('AJAX error when cancelling batch');
                $('#wcm-batch-cancel').prop('disabled', false).text('Cancel Batch');
            }
        });
    }
    
    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Initialize when the document is ready
    $(document).ready(init);
    
})(jQuery); 