/**
 * GPT Image Changer Admin JavaScript
 */
jQuery(document).ready(function($) {
    var testingInProgress = false;
    
    // Initialize
    if ($('.gic-status-dashboard').length) {
        setupStatusRefresh();
        setupActionButtons();
    }
    
    setupImageTesting();
    
    // Refresh status data every 30 seconds
    var statusRefreshTimer;
    
    function setupStatusRefresh() {
        refreshStatusData();
        
        // Clear any existing timer
        if (statusRefreshTimer) {
            clearInterval(statusRefreshTimer);
        }
        
        // Set up automatic refresh
        statusRefreshTimer = setInterval(function() {
            refreshStatusData();
        }, 30000); // 30 seconds
        
        // Manual refresh button
        $('.gic-status-refresh').on('click', function(e) {
            e.preventDefault();
            refreshStatusData();
        });
        
        // Fetch unprocessed image count
        fetchUnprocessedCount();
    }
    
    function fetchUnprocessedCount() {
        const $countElement = $('.gic-unprocessed-count');
        const $loadingElement = $('.gic-unprocessed-card .gic-status-loading');
        
        $countElement.hide();
        $loadingElement.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gic_get_unprocessed_count',
                nonce: gicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $countElement.text(response.data.count).show();
                } else {
                    $countElement.text('?').show();
                }
            },
            error: function() {
                $countElement.text('?').show();
            },
            complete: function() {
                $loadingElement.hide();
            }
        });
    }
    
    function refreshStatusData() {
        const $container = $('.gic-status-dashboard');
        const $refreshButton = $('.gic-status-refresh');
        
        if ($container.hasClass('is-loading')) {
            return; // Already loading
        }
        
        $container.addClass('is-loading');
        $refreshButton.addClass('loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gic_refresh_status',
                nonce: gicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update status cards
                    $.each(response.data, function(key, value) {
                        if (['total', 'pending', 'processing', 'completed', 'failed'].includes(key)) {
                            $('.gic-status-card .gic-' + key + '-count').text(value);
                        }
                    });
                    
                    // Update last refresh time
                    $('.gic-last-refresh span').text(response.data.time);
                    
                    // Update recent images table if available
                    if (response.data.hasOwnProperty('recent_images_html')) {
                        $('.gic-status-section:first-child table').closest('div').html(response.data.recent_images_html);
                    }
                    
                    // Update logs if they exist
                    if (response.data.hasOwnProperty('log') && $('.gic-log-container').length) {
                        const $logsContainer = $('.gic-log-container');
                        const wasScrolledToBottom = $logsContainer[0].scrollHeight - $logsContainer.scrollTop() === $logsContainer.outerHeight();
                        
                        // Build log HTML
                        let logHtml = '';
                        $.each(response.data.log, function(index, log) {
                            logHtml += '<div class="gic-log-entry gic-log-' + log.type + '">';
                            logHtml += '<span class="gic-log-time">[' + log.time + ']</span>';
                            logHtml += '<span class="gic-log-message">' + log.message + '</span>';
                            logHtml += '</div>';
                        });
                        
                        $logsContainer.html(logHtml);
                        
                        // If was scrolled to bottom, keep it scrolled to bottom
                        if (wasScrolledToBottom) {
                            $logsContainer.scrollTop($logsContainer[0].scrollHeight);
                        }
                    }
                } else {
                    console.error('Error refreshing status data:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            },
            complete: function() {
                $container.removeClass('is-loading');
                $refreshButton.removeClass('loading');
            }
        });
    }
    
    // Set up action buttons
    function setupActionButtons() {
        // Reset processing button
        $('.gic-reset-processing').on('click', function(e) {
            e.preventDefault();
            if (confirm(gicAdmin.confirmResetProcessing)) {
                performAction('gic_reset_processing');
            }
        });
        
        // Requeue failed button
        $('.gic-requeue-failed').on('click', function(e) {
            e.preventDefault();
            if (confirm(gicAdmin.confirmRequeueFailed)) {
                performAction('gic_requeue_failed');
            }
        });
        
        // Clear history button
        $('.gic-clear-history').on('click', function(e) {
            e.preventDefault();
            if (confirm(gicAdmin.confirmClearHistory)) {
                performAction('gic_clear_history');
            }
        });
        
        // Clear logs button
        $('#gic-clear-logs').on('click', function(e) {
            e.preventDefault();
            if (confirm(gicAdmin.confirmClearLogs)) {
                performAction('gic_clear_logs');
            }
        });
        
        // Requeue single image - use delegation for dynamically added buttons
        $(document).on('click', '.gic-requeue-single', function(e) {
            e.preventDefault();
            const imageId = $(this).data('image-id');
            if (imageId) {
                if (confirm(gicAdmin.confirmRequeueSingle)) {
                    requeueSingleImage(imageId);
                }
            }
        });
        
        // Queue all unprocessed images
        $('.gic-queue-all').on('click', function(e) {
            e.preventDefault();
            if (confirm(gicAdmin.confirmQueueAll)) {
                queueAllUnprocessedImages();
            }
        });
    }
    
    function performAction(action) {
        const $container = $('.gic-status-dashboard');
        
        if ($container.hasClass('is-loading')) {
            return; // Already loading
        }
        
        $container.addClass('is-loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: gicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    refreshStatusData();
                } else {
                    let message = response.data;
                    if (typeof response.data === 'object' && response.data.message) {
                        message = response.data.message;
                    }
                    alert('Error: ' + message);
                    $container.removeClass('is-loading');
                }
            },
            error: function() {
                alert(gicAdmin.errorOccurred);
                $container.removeClass('is-loading');
            }
        });
    }
    
    function requeueSingleImage(imageId) {
        const $container = $('.gic-status-dashboard');
        
        if ($container.hasClass('is-loading')) {
            return; // Already loading
        }
        
        $container.addClass('is-loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gic_requeue_single',
                image_id: imageId,
                nonce: gicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    refreshStatusData();
                } else {
                    let message = response.data;
                    if (typeof response.data === 'object' && response.data.message) {
                        message = response.data.message;
                    }
                    alert('Error: ' + message);
                    $container.removeClass('is-loading');
                }
            },
            error: function() {
                alert(gicAdmin.errorOccurred);
                $container.removeClass('is-loading');
            }
        });
    }
    
    function queueAllUnprocessedImages() {
        const $container = $('.gic-status-dashboard');
        
        if ($container.hasClass('is-loading')) {
            return; // Already loading
        }
        
        $container.addClass('is-loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'gic_queue_all_unprocessed',
                limit: 700, // Process up to 700 at a time
                nonce: gicAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    refreshStatusData();
                    fetchUnprocessedCount(); // Update the unprocessed count
                } else {
                    let message = response.data;
                    if (typeof response.data === 'object' && response.data.message) {
                        message = response.data.message;
                    }
                    alert('Error: ' + message);
                    $container.removeClass('is-loading');
                }
            },
            error: function() {
                alert(gicAdmin.errorOccurred);
                $container.removeClass('is-loading');
            }
        });
    }
    
    // Setup image testing functionality
    function setupImageTesting() {
        // Media picker for test image
        $('#gic-select-test-image').on('click', function(e) {
            e.preventDefault();
            
            var mediaFrame = wp.media({
                title: gicAdmin.selectImage,
                multiple: false,
                library: {
                    type: 'image'
                },
                button: {
                    text: gicAdmin.selectImage
                }
            });
            
            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $('#gic_test_image_id').val(attachment.id);
                $('#gic-test-image-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" alt="' + attachment.title + '">');
                
                // Enable the process button
                $('#gic-process-test-image').prop('disabled', false);
            });
            
            mediaFrame.open();
        });
        
        // Test Image Processing
        $('#gic-test-process-image').on('click', function(e) {
            e.preventDefault();
            
            if (testingInProgress) {
                return;
            }
            
            var $button = $(this);
            var imageId = $('#gic_test_image_id').val();
            
            if (!imageId) {
                alert(gicAdmin.selectImage);
                return;
            }
            
            testingInProgress = true;
            $button.prop('disabled', true).text(gicAdmin.processingImage);
            $('#gic-test-results').html('<div class="gic-loading">' + gicAdmin.processingImage + '...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gic_test_image_processing',
                    image_id: imageId,
                    nonce: gicAdmin.nonce
                },
                success: function(response) {
                    var html = '';
                    
                    if (response.success) {
                        var data = response.data;
                        var currentMetadata = data.current_metadata || {};
                        var wouldUpdate = data.would_update || {};
                        var currentFilename = data.current_filename || '';
                        var newFilename = data.new_filename || '';
                        
                        html += '<h3>' + gicAdmin.metadataChanges + '</h3>';
                        html += '<table class="gic-results-table">';
                        html += '<tr><th>' + gicAdmin.field + '</th><th>' + gicAdmin.currentValue + '</th><th>' + gicAdmin.newValue + '</th></tr>';
                        
                        // Title row
                        html += '<tr>';
                        html += '<td><strong>' + gicAdmin.title + '</strong></td>';
                        html += '<td>' + (currentMetadata.title || '-') + '</td>';
                        html += '<td class="' + (wouldUpdate.title ? 'gic-changed' : '') + '">' + (wouldUpdate.title || '-') + '</td>';
                        html += '</tr>';
                        
                        // Alt text row
                        html += '<tr>';
                        html += '<td><strong>' + gicAdmin.altText + '</strong></td>';
                        html += '<td>' + (currentMetadata.alt_text || '-') + '</td>';
                        html += '<td class="' + (wouldUpdate.alt_text ? 'gic-changed' : '') + '">' + (wouldUpdate.alt_text || '-') + '</td>';
                        html += '</tr>';
                        
                        // Caption row
                        html += '<tr>';
                        html += '<td><strong>' + gicAdmin.caption + '</strong></td>';
                        html += '<td>' + (currentMetadata.caption || '-') + '</td>';
                        html += '<td class="' + (wouldUpdate.caption ? 'gic-changed' : '') + '">' + (wouldUpdate.caption || '-') + '</td>';
                        html += '</tr>';
                        
                        // Description row
                        html += '<tr>';
                        html += '<td><strong>' + gicAdmin.description + '</strong></td>';
                        html += '<td>' + (currentMetadata.description || '-') + '</td>';
                        html += '<td class="' + (wouldUpdate.description ? 'gic-changed' : '') + '">' + (wouldUpdate.description || '-') + '</td>';
                        html += '</tr>';
                        
                        // Filename row
                        html += '<tr>';
                        html += '<td><strong>' + gicAdmin.filename + '</strong></td>';
                        html += '<td>' + (currentFilename || '-') + '</td>';
                        html += '<td class="' + (newFilename && currentFilename !== newFilename ? 'gic-changed' : '') + '">' + (newFilename || '-') + '</td>';
                        html += '</tr>';
                        
                        html += '</table>';
                        
                        // Add debug information if available
                        if (data.debug_info) {
                            html += '<div class="gic-debug-info">';
                            html += '<h4>' + gicAdmin.debugInformation + '</h4>';
                            html += '<pre>' + JSON.stringify(data.debug_info, null, 2) + '</pre>';
                            html += '</div>';
                        }
                    } else {
                        // Handle error response
                        var errorMessage = response.data;
                        var debugInfo = null;
                        
                        // Check if the error data is an object with message and debug_info
                        if (typeof response.data === 'object') {
                            if (response.data.message) {
                                errorMessage = response.data.message;
                            }
                            if (response.data.debug_info) {
                                debugInfo = response.data.debug_info;
                            }
                        }
                        
                        html += '<div class="gic-error">';
                        html += '<h3>Error</h3>';
                        html += '<p>' + errorMessage + '</p>';
                        
                        // Display debug info if available
                        if (debugInfo) {
                            html += '<div class="gic-debug-info">';
                            html += '<h4>' + gicAdmin.debugInformation + '</h4>';
                            html += '<pre>' + JSON.stringify(debugInfo, null, 2) + '</pre>';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                    }
                    
                    $('#gic-test-results').html(html);
                },
                error: function() {
                    $('#gic-test-results').html('<div class="gic-error">' + gicAdmin.ajaxError + '</div>');
                },
                complete: function() {
                    testingInProgress = false;
                    $button.prop('disabled', false).text(gicAdmin.testProcessImage);
                }
            });
        });
        
        // Test API Key
        $('#gic-test-api-key').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var apiKey = $('#gic_api_key').val();
            
            if (!apiKey) {
                alert(gicAdmin.apiKeyEmpty);
                return;
            }
            
            $button.prop('disabled', true).text(gicAdmin.testing);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gic_test_api_key',
                    api_key: apiKey,
                    nonce: gicAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(gicAdmin.apiKeyValid);
                    } else {
                        var errorMessage = response.data;
                        if (typeof response.data === 'object' && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        alert(gicAdmin.apiKeyInvalid + ': ' + errorMessage);
                    }
                },
                error: function() {
                    alert(gicAdmin.ajaxError);
                },
                complete: function() {
                    $button.prop('disabled', false).text(gicAdmin.testApiKey);
                }
            });
        });
    }
}); 