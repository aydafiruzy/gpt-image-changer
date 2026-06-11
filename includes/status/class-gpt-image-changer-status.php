<?php
/**
 * Status tracking class
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling status tracking
 */
class GPT_Image_Changer_Status {

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gic_image_status';
        
        // Initialize
        $this->init();
    }

    /**
     * Initialize the status handler
     */
    public function init() {
        // Create tables if they don't exist
        $this->create_tables();
        
        // Register AJAX handlers
        add_action('wp_ajax_gic_refresh_status', array($this, 'ajax_refresh_status'));
        add_action('wp_ajax_gic_reset_processing', array($this, 'ajax_reset_processing'));
        add_action('wp_ajax_gic_requeue_failed', array($this, 'ajax_requeue_failed'));
        add_action('wp_ajax_gic_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_gic_requeue_single', array($this, 'ajax_requeue_single'));
        add_action('wp_ajax_gic_queue_all_unprocessed', array($this, 'ajax_queue_all_unprocessed'));
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            image_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            result longtext,
            PRIMARY KEY  (id),
            KEY image_id (image_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * AJAX handler for refreshing status data
     */
    public function ajax_refresh_status() {
        // Verify user can access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get statistics
        $stats = $this->get_processing_statistics();
        
        // Get recent logs
        $logs = array_slice(get_option('gic_process_log', array()), -10);
        $logs = array_reverse($logs); // Most recent first
        
        // Get recent images
        $recent_images = $this->get_recent_processed_images(20);
        
        // Generate recent images HTML
        ob_start();
        if (!empty($recent_images)) {
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
        } else {
            echo '<p>' . esc_html__('No images have been processed yet.', 'gpt-image-changer') . '</p>';
        }
        $recent_images_html = ob_get_clean();
        
        wp_send_json_success(array(
            'total' => $stats['total'],
            'pending' => $stats['pending'],
            'processing' => $stats['processing'],
            'completed' => $stats['completed'],
            'failed' => $stats['failed'],
            'time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            'log' => $logs,
            'recent_images_html' => $recent_images_html
        ));
    }

    /**
     * AJAX handler for resetting processing images
     */
    public function ajax_reset_processing() {
        // Verify user can access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Reset processing images
        $reset = $this->reset_processing_images();
        
        if ($reset) {
            // Log the action
            $processor = new GPT_Image_Changer_Processor();
            $processor->log_message(sprintf('Reset %d processing images', $reset), 'info');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Reset %d processing images', 'gpt-image-changer'), $reset),
                'count' => $reset
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No processing images to reset', 'gpt-image-changer')
            ));
        }
    }

    /**
     * AJAX handler for requeuing failed images
     */
    public function ajax_requeue_failed() {
        // Verify user can access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Requeue failed images
        $requeued = $this->requeue_failed_images();
        
        if ($requeued) {
            // Log the action
            $processor = new GPT_Image_Changer_Processor();
            $processor->log_message(sprintf('Requeued %d failed images', $requeued), 'info');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Requeued %d failed images', 'gpt-image-changer'), $requeued),
                'count' => $requeued
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No failed images to requeue', 'gpt-image-changer')
            ));
        }
    }

    /**
     * AJAX handler for clearing history
     */
    public function ajax_clear_history() {
        // Verify user can access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Clear history of completed and failed images
        $cleared = $this->clear_processing_history();
        
        if ($cleared) {
            // Log the action
            $processor = new GPT_Image_Changer_Processor();
            $processor->log_message(sprintf('Cleared %d images from processing history', $cleared), 'info');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Cleared %d images from processing history', 'gpt-image-changer'), $cleared),
                'count' => $cleared
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No history to clear', 'gpt-image-changer')
            ));
        }
    }

    /**
     * AJAX handler for requeuing a single image
     */
    public function ajax_requeue_single() {
        // Verify user can access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get image ID
        $image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;
        
        if (!$image_id) {
            wp_send_json_error(array(
                'message' => __('Invalid image ID', 'gpt-image-changer')
            ));
            return;
        }
        
        // Update status to pending
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'pending'),
            array('image_id' => $image_id, 'status' => 'failed'),
            array('%s'),
            array('%d', '%s')
        );
        
        if ($result) {
            // Log the action
            $processor = new GPT_Image_Changer_Processor();
            $processor->log_message(sprintf('Requeued image ID %d', $image_id), 'info');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Image ID %d has been requeued', 'gpt-image-changer'), $image_id)
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to requeue image ID %d', 'gpt-image-changer'), $image_id)
            ));
        }
    }

    /**
     * Reset processing images to failed
     *
     * @return int Number of images reset
     */
    public function reset_processing_images() {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s WHERE status = %s",
            'failed',
            'processing'
        ));
        
        return $result;
    }

    /**
     * Requeue failed images to pending
     *
     * @return int Number of images requeued
     */
    public function requeue_failed_images() {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s WHERE status = %s",
            'pending',
            'failed'
        ));
        
        return $result;
    }

    /**
     * Clear processing history (completed and failed)
     *
     * @return int Number of records cleared
     */
    public function clear_processing_history() {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE status IN (%s, %s)",
            'completed',
            'failed'
        ));
        
        return $result;
    }

    /**
     * Get processing statistics
     *
     * @return array Statistics data
     */
    public function get_processing_statistics() {
        global $wpdb;
        
        // Get counts for each status
        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
            FROM {$this->table_name} 
            GROUP BY status",
            OBJECT_K
        );
        
        // Initialize default values
        $result = array(
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        );
        
        // Update with actual counts
        if (!empty($stats)) {
            foreach ($stats as $status => $data) {
                if (isset($result[$status])) {
                    $result[$status] = (int) $data->count;
                    $result['total'] += (int) $data->count;
                }
            }
        }
        
        return $result;
    }

    /**
     * Get images by status
     *
     * @param string $status Status to filter by
     * @param int $limit Maximum number of images to return
     * @return array Array of image IDs
     */
    public function get_images_by_status($status, $limit = 10) {
        global $wpdb;
        
        $images = $wpdb->get_col($wpdb->prepare(
            "SELECT image_id FROM {$this->table_name} WHERE status = %s ORDER BY updated ASC LIMIT %d",
            $status,
            $limit
        ));
        
        return $images;
    }

    /**
     * Get recently processed images
     *
     * @param int $limit Maximum number of images to return
     * @return array Array of processed image data
     */
    public function get_recent_processed_images($limit = 20) {
        global $wpdb;
        
        $images = $wpdb->get_results($wpdb->prepare(
            "SELECT id, image_id, status, UNIX_TIMESTAMP(updated) as updated
            FROM {$this->table_name}
            ORDER BY updated DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $images;
    }

    /**
     * Get all processed image IDs
     *
     * @return array Array of all processed image IDs
     */
    public function get_all_processed_image_ids() {
        global $wpdb;
        
        $images = $wpdb->get_col(
            "SELECT image_id FROM {$this->table_name}"
        );
        
        return $images;
    }

    /**
     * Add an image to the processing queue
     *
     * @param int $image_id Image ID to add
     * @return bool True if added, false if already in queue
     */
    public function add_image_to_queue($image_id) {
        global $wpdb;
        
        // Check if image already exists in the queue
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE image_id = %d",
            $image_id
        ));
        
        if ($exists) {
            return false;
        }
        
        // Add to queue with pending status
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'image_id' => $image_id,
                'status' => 'pending'
            ),
            array('%d', '%s')
        );
        
        return (bool) $result;
    }

    /**
     * Set status for an image
     *
     * @param int $image_id Image ID
     * @param string $status New status
     * @param array $result Optional result data
     * @return bool Success or failure
     */
    public function set_image_status($image_id, $status, $result = null) {
        global $wpdb;
        
        $data = array(
            'status' => $status
        );
        
        $formats = array('%s');
        
        if ($result !== null) {
            $data['result'] = json_encode($result);
            $formats[] = '%s';
        }
        
        $updated = $wpdb->update(
            $this->table_name,
            $data,
            array('image_id' => $image_id),
            $formats,
            array('%d')
        );
        
        return (bool) $updated;
    }

    /**
     * Get the status of an image
     *
     * @param int $image_id Image ID
     * @return string|false Status or false if not found
     */
    public function get_image_status($image_id) {
        global $wpdb;
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->table_name} WHERE image_id = %d",
            $image_id
        ));
        
        return $status;
    }

    /**
     * Get result data for an image
     *
     * @param int $image_id Image ID
     * @return array|false Result data or false if not found
     */
    public function get_image_result($image_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT result FROM {$this->table_name} WHERE image_id = %d",
            $image_id
        ));
        
        return $result ? json_decode($result, true) : false;
    }

    /**
     * Clear API usage logs
     *
     * @return bool Success or failure
     */
    public function clear_api_logs() {
        return update_option('gic_api_usage_log', array());
    }

    /**
     * Clear process logs
     *
     * @return bool Success or failure
     */
    public function clear_process_logs() {
        return update_option('gic_process_log', array());
    }

    /**
     * AJAX handler for queuing all unprocessed WooCommerce product images
     */
    public function ajax_queue_all_unprocessed() {
        // Verify user can access
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gic_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Get processor instance
        global $gpt_image_changer;
        $processor = $gpt_image_changer->get_processor();
        
        if (!$processor) {
            wp_send_json_error('Processor not available');
        }
        
        // Get limit
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 700;
        
        // Queue all unprocessed images
        $queued = $processor->queue_all_unprocessed_images($limit);
        
        if (count($queued) > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('Queued %d unprocessed WooCommerce product images for processing.', 'gpt-image-changer'), count($queued)),
                'count' => count($queued)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No new images were queued. All WooCommerce product images may already be queued or processed.', 'gpt-image-changer')
            ));
        }
    }
} 