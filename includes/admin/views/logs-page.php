<?php
/**
 * Logs page template
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get logs
$api_logs = get_option('gic_api_usage_log', array());

// Get process logs
$process_logs = get_option('gic_process_log', array());

// Sort logs by timestamp descending
usort($api_logs, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Limit to 50 entries for display
$api_logs = array_slice($api_logs, 0, 50);

// Clear logs action
$clear_logs_url = wp_nonce_url(
    add_query_arg(
        array(
            'page' => 'gpt-image-changer-logs',
            'action' => 'clear_logs',
        ),
        admin_url('admin.php')
    ),
    'gic_clear_logs'
);

// Check for clear logs action
if (isset($_GET['action']) && $_GET['action'] === 'clear_logs' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'gic_clear_logs')) {
    // Clear logs
    update_option('gic_api_usage_log', array());
    update_option('gic_process_log', array());
    
    // Redirect to logs page
    wp_redirect(admin_url('admin.php?page=gpt-image-changer-logs&cleared=1'));
    exit;
}

// Show notice if logs were cleared
if (isset($_GET['cleared'])) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logs cleared successfully.', 'gpt-image-changer') . '</p></div>';
    });
}

// Get log type filter
$log_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : 'api';

?>
<div class="wrap gic-logs-page">
    <h1><?php esc_html_e('GPT Image Changer - Logs', 'gpt-image-changer'); ?></h1>
    
    <div class="gic-logs-tabs">
        <a href="<?php echo esc_url(add_query_arg('log_type', 'api')); ?>" class="<?php echo $log_type === 'api' ? 'active' : ''; ?>"><?php esc_html_e('API Logs', 'gpt-image-changer'); ?></a>
        <a href="<?php echo esc_url(add_query_arg('log_type', 'process')); ?>" class="<?php echo $log_type === 'process' ? 'active' : ''; ?>"><?php esc_html_e('Process Logs', 'gpt-image-changer'); ?></a>
    </div>
    
    <div class="gic-logs-actions">
        <a href="<?php echo esc_url($clear_logs_url); ?>" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs? This cannot be undone.', 'gpt-image-changer'); ?>');"><?php esc_html_e('Clear All Logs', 'gpt-image-changer'); ?></a>
    </div>
    
    <?php if ($log_type === 'api'): ?>
        <h2><?php esc_html_e('API Usage Logs', 'gpt-image-changer'); ?></h2>
        
        <?php if (empty($api_logs)): ?>
            <p><?php esc_html_e('No API logs found.', 'gpt-image-changer'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped gic-logs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'gpt-image-changer'); ?></th>
                        <th><?php esc_html_e('Image', 'gpt-image-changer'); ?></th>
                        <th><?php esc_html_e('Model', 'gpt-image-changer'); ?></th>
                        <th><?php esc_html_e('Tokens', 'gpt-image-changer'); ?></th>
                        <th><?php esc_html_e('Status', 'gpt-image-changer'); ?></th>
                        <th><?php esc_html_e('Actions', 'gpt-image-changer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_logs as $index => $log): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $log['timestamp'])); ?></td>
                            <td>
                                <?php 
                                    $image_id = isset($log['image_id']) ? $log['image_id'] : 0;
                                    if ($image_id) {
                                        $image_thumb = wp_get_attachment_image($image_id, array(50, 50));
                                        $image_link = get_edit_post_link($image_id);
                                        if ($image_thumb) {
                                            echo '<a href="' . esc_url($image_link) . '">' . $image_thumb . '</a>';
                                        } else {
                                            echo esc_html(sprintf(__('ID: %d (deleted)', 'gpt-image-changer'), $image_id));
                                        }
                                    } else {
                                        echo '—';
                                    }
                                ?>
                            </td>
                            <td><?php echo esc_html($log['model']); ?></td>
                            <td><?php echo esc_html($log['tokens']); ?></td>
                            <td>
                                <span class="gic-status gic-status-<?php echo esc_attr($log['status']); ?>">
                                    <?php echo esc_html(ucfirst($log['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($log['debug']) && !empty($log['debug'])): ?>
                                    <button type="button" class="button button-small gic-view-details" data-log-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('View Details', 'gpt-image-changer'); ?></button>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Debug Details Modal -->
            <div id="gic-log-details-modal" class="gic-modal">
                <div class="gic-modal-content">
                    <span class="gic-modal-close">&times;</span>
                    <h2><?php esc_html_e('API Request Details', 'gpt-image-changer'); ?></h2>
                    <div id="gic-log-details-content"></div>
                </div>
            </div>
            
            <script>
                // Prepare log data for JS
                var gicLogs = <?php echo wp_json_encode($api_logs); ?>;
            </script>
            
        <?php endif; ?>
    <?php else: ?>
        <h2><?php esc_html_e('Process Logs', 'gpt-image-changer'); ?></h2>
        
        <?php if (empty($process_logs)): ?>
            <p><?php esc_html_e('No process logs found.', 'gpt-image-changer'); ?></p>
        <?php else: ?>
            <div class="gic-process-logs">
                <?php foreach ($process_logs as $log): ?>
                    <div class="gic-log-entry gic-log-<?php echo esc_attr($log['type']); ?>">
                        <span class="gic-log-time">[<?php echo esc_html($log['time']); ?>]</span>
                        <span class="gic-log-message"><?php echo esc_html($log['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .gic-logs-tabs {
        margin: 20px 0;
        border-bottom: 1px solid #ccd0d4;
    }
    
    .gic-logs-tabs a {
        display: inline-block;
        padding: 10px 15px;
        text-decoration: none;
        color: #646970;
        margin-bottom: -1px;
    }
    
    .gic-logs-tabs a.active {
        border: 1px solid #ccd0d4;
        border-bottom-color: #fff;
        background: #fff;
        color: #1d2327;
        font-weight: 600;
    }
    
    .gic-logs-actions {
        margin: 20px 0;
    }
    
    .gic-logs-table img {
        vertical-align: middle;
        max-width: 50px;
        max-height: 50px;
    }
    
    .gic-process-logs {
        background: #f6f7f7;
        border: 1px solid #c3c4c7;
        padding: 15px;
        max-height: 500px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 13px;
        line-height: 1.5;
        margin-top: 20px;
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
    
    .gic-status-success {
        background-color: #edfaef;
        color: #1a472a;
    }
    
    .gic-status-failed, .gic-status-parse_error, .gic-status-error {
        background-color: #fcf0f1;
        color: #761919;
    }
    
    /* Modal styles */
    .gic-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }
    
    .gic-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 1000px;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .gic-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .gic-modal-close:hover,
    .gic-modal-close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
    
    .gic-log-details-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .gic-log-details-table th {
        text-align: left;
        background: #f0f0f1;
    }
    
    .gic-log-details-table th,
    .gic-log-details-table td {
        border: 1px solid #ccd0d4;
        padding: 8px;
    }
    
    .gic-code-block {
        white-space: pre-wrap;
        overflow-x: auto;
        background: #f6f7f7;
        border: 1px solid #ddd;
        padding: 10px;
        max-height: 400px;
        overflow-y: auto;
        font-family: monospace;
    }
</style> 