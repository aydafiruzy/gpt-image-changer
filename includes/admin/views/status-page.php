<?php
/**
 * Status page template
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get status handler
global $gpt_image_changer;
$status_handler = $gpt_image_changer->get_status_handler();

// Get processing statistics
$stats = $status_handler->get_processing_statistics();

// Get the latest log entries
$logs = array_slice(get_option('gic_process_log', array()), -20);
$logs = array_reverse($logs); // Most recent first

?>
<div class="wrap">
    <h1><?php esc_html_e('GPT Image Changer - Status', 'gpt-image-changer'); ?></h1>
    
    <div class="gic-status-dashboard">
        <div class="gic-status-toolbar">
            <div class="gic-status-actions">
                <button class="button gic-status-refresh">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh Status', 'gpt-image-changer'); ?>
                </button>
                
                <button class="button gic-reset-processing">
                    <?php esc_html_e('Reset Processing', 'gpt-image-changer'); ?>
                </button>
                
                <button class="button gic-requeue-failed">
                    <?php esc_html_e('Requeue Failed', 'gpt-image-changer'); ?>
                </button>
                
                <button class="button gic-clear-history">
                    <?php esc_html_e('Clear History', 'gpt-image-changer'); ?>
                </button>
            </div>
            
            <div class="gic-last-refresh">
                <?php esc_html_e('Last refresh:', 'gpt-image-changer'); ?> <span><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></span>
            </div>
        </div>
        
        <div class="gic-status-cards">
            <div class="gic-status-card">
                <h3><?php esc_html_e('Total Images', 'gpt-image-changer'); ?></h3>
                <div class="gic-card-count gic-total-count"><?php echo esc_html($stats['total']); ?></div>
            </div>
            
            <div class="gic-status-card">
                <h3><?php esc_html_e('Pending', 'gpt-image-changer'); ?></h3>
                <div class="gic-card-count gic-pending-count"><?php echo esc_html($stats['pending']); ?></div>
            </div>
            
            <div class="gic-status-card">
                <h3><?php esc_html_e('Processing', 'gpt-image-changer'); ?></h3>
                <div class="gic-card-count gic-processing-count"><?php echo esc_html($stats['processing']); ?></div>
            </div>
            
            <div class="gic-status-card">
                <h3><?php esc_html_e('Completed', 'gpt-image-changer'); ?></h3>
                <div class="gic-card-count gic-completed-count"><?php echo esc_html($stats['completed']); ?></div>
            </div>
            
            <div class="gic-status-card">
                <h3><?php esc_html_e('Failed', 'gpt-image-changer'); ?></h3>
                <div class="gic-card-count gic-failed-count"><?php echo esc_html($stats['failed']); ?></div>
            </div>
            
            <div class="gic-status-card gic-unprocessed-card">
                <h3><?php esc_html_e('Unprocessed WooCommerce Images', 'gpt-image-changer'); ?></h3>
                <div class="gic-card-count gic-unprocessed-count">...</div>
                <div class="gic-status-loading"></div>
                <div class="gic-card-action">
                    <button class="button button-small gic-queue-all">
                        <?php esc_html_e('Queue All', 'gpt-image-changer'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="gic-status-sections">
            <div class="gic-status-section">
                <h2><?php esc_html_e('Recently Processed Images', 'gpt-image-changer'); ?></h2>
                
                <?php
                // Get recently processed images
                $recent_images = $status_handler->get_recent_processed_images(20);
                
                if (empty($recent_images)) {
                    echo '<p>' . esc_html__('No images have been processed yet.', 'gpt-image-changer') . '</p>';
                } else {
                    ?>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Image', 'gpt-image-changer'); ?></th>
                                <th><?php esc_html_e('Status', 'gpt-image-changer'); ?></th>
                                <th><?php esc_html_e('Date Updated', 'gpt-image-changer'); ?></th>
                                <th><?php esc_html_e('Actions', 'gpt-image-changer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_images as $image) : ?>
                                <tr>
                                    <td>
                                        <?php
                                        $image_thumb = wp_get_attachment_image($image['image_id'], array(50, 50));
                                        $image_link = get_edit_post_link($image['image_id']);
                                        
                                        if ($image_thumb) {
                                            echo '<a href="' . esc_url($image_link) . '">' . $image_thumb . '</a>';
                                        } else {
                                            echo esc_html(sprintf(__('ID: %d (deleted)', 'gpt-image-changer'), $image['image_id']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="gic-status gic-status-<?php echo esc_attr($image['status']); ?>">
                                            <?php echo esc_html(ucfirst($image['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $image['updated'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($image['status'] === 'failed') : ?>
                                            <button class="button button-small gic-requeue-single" data-image-id="<?php echo esc_attr($image['image_id']); ?>">
                                                <?php esc_html_e('Requeue', 'gpt-image-changer'); ?>
                                            </button>
                                        <?php else : ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                ?>
            </div>
            
            <div class="gic-status-section">
                <h2><?php esc_html_e('Recent Activity Log', 'gpt-image-changer'); ?></h2>
                <div class="gic-log-container">
                    <?php if (empty($logs)): ?>
                        <p><?php esc_html_e('No activity logs found.', 'gpt-image-changer'); ?></p>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="gic-log-entry gic-log-<?php echo esc_attr($log['type']); ?>">
                                <span class="gic-log-time">[<?php echo esc_html($log['time']); ?>]</span>
                                <span class="gic-log-message"><?php echo esc_html($log['message']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="gic-log-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gpt-image-changer-logs')); ?>" class="button">
                        <?php esc_html_e('View Full Logs', 'gpt-image-changer'); ?>
                    </a>
                </div>
            </div>
            
            <div class="gic-status-section">
                <h2><?php esc_html_e('Scheduled Processing', 'gpt-image-changer'); ?></h2>
                
                <?php
                $next_scheduled = wp_next_scheduled('gic_process_scheduled_images');
                
                if ($next_scheduled) {
                    $time_diff = $next_scheduled - time();
                    $minutes = floor($time_diff / 60);
                    $seconds = $time_diff % 60;
                    
                    echo '<p>' . sprintf(
                        esc_html__('Next scheduled processing will run in %1$s minutes and %2$s seconds (at %3$s).', 'gpt-image-changer'),
                        $minutes,
                        $seconds,
                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)
                    ) . '</p>';
                    echo '<p>' . esc_html__('The plugin is set to process 5 images every 2 minutes.', 'gpt-image-changer') . '</p>';
                } else {
                    echo '<p>' . esc_html__('No scheduled processing is set up. Please deactivate and reactivate the plugin to fix this.', 'gpt-image-changer') . '</p>';
                }
                ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field('gic_run_processing', 'gic_nonce'); ?>
                    <p>
                        <button type="submit" name="gic_run_now" class="button button-primary">
                            <?php esc_html_e('Run Processing Now', 'gpt-image-changer'); ?>
                        </button>
                    </p>
                </form>
                
                <?php
                // Handle manual processing
                if (isset($_POST['gic_run_now']) && isset($_POST['gic_nonce']) && wp_verify_nonce($_POST['gic_nonce'], 'gic_run_processing')) {
                    // Get processor
                    $processor = $gpt_image_changer->get_processor();
                    
                    // Run processor
                    $processed = $processor->process_scheduled_images();
                    
                    if ($processed) {
                        echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Processing completed. Processed %d images.', 'gpt-image-changer'), count($processed)) . '</p></div>';
                    } else {
                        echo '<div class="notice notice-warning"><p>' . esc_html__('Processing completed. No images were processed.', 'gpt-image-changer') . '</p></div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
    .gic-status-dashboard {
        margin-top: 20px;
    }
    
    .gic-status-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .gic-status-actions {
        display: flex;
        gap: 10px;
    }
    
    .gic-status-refresh.loading .dashicons {
        animation: gic-spin 1s infinite linear;
    }
    
    @keyframes gic-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .gic-status-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .gic-status-card {
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border-radius: 4px;
        padding: 20px;
        flex: 1;
        min-width: 150px;
        text-align: center;
    }
    
    .gic-status-card h3 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 16px;
    }
    
    .gic-card-count {
        font-size: 32px;
        font-weight: 600;
        color: #2271b1;
    }
    
    .gic-card-action {
        margin-top: 10px;
    }
    
    .gic-status-sections {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    
    .gic-status-section {
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border-radius: 4px;
        padding: 20px;
    }
    
    .gic-status-section h2 {
        margin-top: 0;
        font-size: 18px;
        border-bottom: 1px solid #f0f0f1;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    
    .gic-status {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .gic-status-pending {
        background-color: #f0f6fc;
        color: #2271b1;
    }
    
    .gic-status-processing {
        background-color: #fcf9e8;
        color: #996800;
    }
    
    .gic-status-completed {
        background-color: #edfaef;
        color: #1a472a;
    }
    
    .gic-status-failed {
        background-color: #fcf0f1;
        color: #761919;
    }
    
    .gic-status-loading {
        width: 20px;
        height: 20px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-left-color: #2271b1;
        border-radius: 50%;
        animation: gic-spin 1s infinite linear;
        margin: 10px auto 0;
        display: inline-block;
    }
    
    .gic-log-container {
        background: #f6f7f7;
        border: 1px solid #c3c4c7;
        padding: 15px;
        max-height: 300px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 15px;
    }
    
    .gic-log-entry {
        margin-bottom: 5px;
    }
    
    .gic-log-time {
        color: #646970;
        margin-right: 10px;
    }
    
    .gic-log-info .gic-log-message {
        color: #2271b1;
    }
    
    .gic-log-error .gic-log-message {
        color: #d63638;
    }
    
    .gic-log-actions {
        text-align: right;
    }
    
    /* Responsive */
    @media screen and (max-width: 782px) {
        .gic-status-sections {
            grid-template-columns: 1fr;
        }
        
        .gic-status-card {
            min-width: 100px;
        }
    }
</style> 