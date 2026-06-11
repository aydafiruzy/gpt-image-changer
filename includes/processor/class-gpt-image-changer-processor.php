<?php
/**
 * Image processor class
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling image processing operations
 */
class GPT_Image_Changer_Processor {

    /**
     * API handler instance
     *
     * @var GPT_Image_Changer_API
     */
    private $api;

    /**
     * Status handler instance
     *
     * @var GPT_Image_Changer_Status
     */
    private $status;

    /**
     * Maximum batch size for processing
     *
     * @var int
     */
    private $batch_size = 5;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug_mode = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the processor
     */
    public function init() {
        global $gpt_image_changer;

        // Only try to get handlers if the main plugin instance is available
        if ($gpt_image_changer !== null) {
            if (method_exists($gpt_image_changer, 'get_api_handler')) {
                $this->api = $gpt_image_changer->get_api_handler();
            }
            
            if (method_exists($gpt_image_changer, 'get_status_handler')) {
                $this->status = $gpt_image_changer->get_status_handler();
            }
        }
        
        // Get debug mode from settings
        $this->debug_mode = get_option('gic_debug_mode', false);

        // Add AJAX handlers
        add_action('wp_ajax_gic_process_queue', array($this, 'ajax_process_queue'));
        
        // Schedule processor hook
        add_action('gic_process_scheduled_images', array($this, 'process_scheduled_images'));
        
        // Add WP-Cron schedule if not exists
        if (!wp_next_scheduled('gic_process_scheduled_images')) {
            wp_schedule_event(time(), 'two_minutes', 'gic_process_scheduled_images');
        }
    }

    /**
     * AJAX handler for processing the queue
     */
    public function ajax_process_queue() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $result = $this->process_pending_images();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => sprintf(__('Processed %d images', 'gpt-image-changer'), count($result)),
                'processed' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No images were processed', 'gpt-image-changer')
            ));
        }
    }

    /**
     * Process scheduled images
     */
    public function process_scheduled_images() {
        // Log that scheduled processing has started
        $this->log_message('Starting scheduled image processing (5 images every 2 minutes)', 'info');
        
        // Process pending images
        $processed = $this->process_pending_images();
        
        // Log completion
        if ($processed) {
            $this->log_message(sprintf('Scheduled processing completed, processed %d images', count($processed)), 'info');
        } else {
            $this->log_message('Scheduled processing completed, no images processed', 'info');
        }
        
        return $processed;
    }

    /**
     * Process pending images
     *
     * @return array|false Array of processed image IDs or false if none processed
     */
    public function process_pending_images() {
        // Check if required dependencies are available
        if (!$this->api || !$this->status) {
            global $gpt_image_changer;
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_api_handler')) {
                $this->api = $gpt_image_changer->get_api_handler();
            }
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_status_handler')) {
                $this->status = $gpt_image_changer->get_status_handler();
            }
            
            // If still not available, log an error and return
            if (!$this->api || !$this->status) {
                $this->log_message('Required dependencies are missing. Cannot process images.', 'error');
                return false;
            }
        }
    
        // Get pending images
        $pending_images = $this->get_pending_images();
        
        if (empty($pending_images)) {
            return false;
        }
        
        $processed = array();
        
        foreach ($pending_images as $image_id) {
            // Mark as processing
            $this->status->set_image_status($image_id, 'processing');
            
            // Process image
            $result = $this->process_image($image_id);
            
            if ($result) {
                // Mark as completed
                $this->status->set_image_status($image_id, 'completed');
                $processed[] = $image_id;
            } else {
                // Mark as failed
                $this->status->set_image_status($image_id, 'failed');
            }
        }
        
        return !empty($processed) ? $processed : false;
    }

    /**
     * Get pending images for processing
     *
     * @return array Array of image IDs
     */
    public function get_pending_images() {
        // Check if status handler is available
        if (!$this->status) {
            global $gpt_image_changer;
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_status_handler')) {
                $this->status = $gpt_image_changer->get_status_handler();
            }
            
            // If still not available, return empty array
            if (!$this->status) {
                $this->log_message('Status handler is missing. Cannot get pending images.', 'error');
                return array();
            }
        }
        
        // Get pending image IDs from status handler
        $pending_images = $this->status->get_images_by_status('pending', $this->batch_size);
        
        // If not enough pending images, queue more
        if (count($pending_images) < $this->batch_size) {
            $this->queue_woocommerce_images($this->batch_size - count($pending_images));
            
            // Get updated list of pending images
            $pending_images = $this->status->get_images_by_status('pending', $this->batch_size);
        }
        
        return $pending_images;
    }

    /**
     * Queue WooCommerce product images for processing
     *
     * @param int $limit Maximum number of images to queue
     * @return array Array of queued image IDs
     */
    public function queue_woocommerce_images($limit = 10) {
        global $wpdb;
        
        // Check if status handler is available
        if (!$this->status) {
            global $gpt_image_changer;
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_status_handler')) {
                $this->status = $gpt_image_changer->get_status_handler();
            }
            
            // If still not available, return empty array
            if (!$this->status) {
                $this->log_message('Status handler is missing. Cannot queue images.', 'error');
                return array();
            }
        }
        
        // Log that we're queuing images
        $this->log_message('Queuing WooCommerce product images for processing', 'info');
        
        // Get images that are already in the system
        $processed_images = $this->status->get_all_processed_image_ids();
        
        // Get featured images from WooCommerce products
        $query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value > 0
            ORDER BY p.post_date DESC
            LIMIT %d
        ";
        
        $featured_images = $wpdb->get_col($wpdb->prepare($query, $limit * 2));
        
        // Get featured images from WooCommerce product variations
        $query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value > 0
            ORDER BY p.post_date DESC
            LIMIT %d
        ";
        
        $variation_featured_images = $wpdb->get_col($wpdb->prepare($query, $limit * 2));
        
        // Get gallery images from WooCommerce products
        $query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_product_image_gallery'
            AND pm.meta_value <> ''
            ORDER BY p.post_date DESC
            LIMIT %d
        ";
        
        $gallery_image_strings = $wpdb->get_col($wpdb->prepare($query, $limit * 2));
        
        // Extract gallery image IDs
        $gallery_images = array();
        foreach ($gallery_image_strings as $gallery_string) {
            $ids = explode(',', $gallery_string);
            $gallery_images = array_merge($gallery_images, $ids);
        }
        
        // Combine all images - include variation featured images
        $all_images = array_merge($featured_images, $variation_featured_images, $gallery_images);
        
        // Filter out already processed images
        $new_images = array_diff($all_images, $processed_images);
        
        // Limit to requested number
        $new_images = array_slice($new_images, 0, $limit);
        
        // Add each image to the queue
        $queued = array();
        foreach ($new_images as $image_id) {
            if ($this->status->add_image_to_queue($image_id)) {
                $queued[] = $image_id;
                
                if ($this->debug_mode) {
                    $this->log_message(sprintf('Queued image ID %d for processing', $image_id), 'info');
                }
            }
        }
        
        $this->log_message(sprintf('Queued %d new images for processing', count($queued)), 'info');
        
        return $queued;
    }

    /**
     * Process a single image
     *
     * @param int $image_id Image ID
     * @return bool Success or failure
     */
    public function process_image($image_id) {
        // Check if API handler is available
        if (!$this->api) {
            global $gpt_image_changer;
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_api_handler')) {
                $this->api = $gpt_image_changer->get_api_handler();
            }
            
            // If still not available, log an error and return
            if (!$this->api) {
                $this->log_message(sprintf('Failed to process image ID %d: API handler missing', $image_id), 'error');
                return false;
            }
        }
        
        // Log image processing start
        $this->log_message(sprintf('Processing image ID %d', $image_id), 'info');
        
        // Get product ID if this is a product image
        $product_id = $this->get_product_id_from_image($image_id);
        
        // Get product information for context
        $product_context = '';
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_context = sprintf(
                    "Product Title: %s\nProduct Description: %s",
                    $product->get_name(),
                    wp_strip_all_tags($product->get_description())
                );
                
                // Check if this is a variation image
                global $wpdb;
                $variation_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_thumbnail_id' 
                    AND meta_value = %d
                    AND post_id IN (
                        SELECT ID FROM {$wpdb->posts}
                        WHERE post_type = 'product_variation'
                        AND post_parent = %d
                    )",
                    $image_id,
                    $product_id
                ));
                
                if ($variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        // Add variation attributes to context
                        $variation_attributes = $variation->get_attributes();
                        $attribute_details = array();
                        
                        foreach ($variation_attributes as $attribute_name => $attribute_value) {
                            $taxonomy = str_replace('attribute_', '', $attribute_name);
                            
                            // If it's a taxonomy attribute
                            if (taxonomy_exists($taxonomy)) {
                                $term = get_term_by('slug', $attribute_value, $taxonomy);
                                if ($term) {
                                    $attribute_details[] = wc_attribute_label($taxonomy) . ': ' . $term->name;
                                }
                            } else {
                                // If it's a custom attribute
                                $attribute_details[] = wc_attribute_label($taxonomy) . ': ' . $attribute_value;
                            }
                        }
                        
                        if (!empty($attribute_details)) {
                            $product_context .= "\nVariation Details: " . implode(', ', $attribute_details);
                        }
                    }
                }
            }
        }
        
        // Process image with API
        $result = $this->api->process_image($image_id, $product_context);
        
        // Check if the result is a WP_Error object
        if (is_wp_error($result)) {
            $this->log_message(sprintf('Failed to process image ID %d: %s', $image_id, $result->get_error_message()), 'error');
            return false;
        }
        
        // Check if the result is empty or invalid
        if (!$result || !is_array($result)) {
            $this->log_message(sprintf('Failed to process image ID %d: Invalid API result', $image_id), 'error');
            return false;
        }
        
        // Attempt to update image metadata
        $updated = $this->update_image_metadata($image_id, $result);
        
        if ($updated) {
            $this->log_message(sprintf('Successfully updated metadata for image ID %d', $image_id), 'info');
            return true;
        } else {
            $this->log_message(sprintf('Failed to update metadata for image ID %d', $image_id), 'error');
            return false;
        }
    }

    /**
     * Test process an image without saving changes
     *
     * @param int $image_id Image ID
     * @return array|false Results of the test or false on failure
     */
    public function test_process_image($image_id) {
        // Check if API handler is available
        if (!$this->api) {
            global $gpt_image_changer;
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_api_handler')) {
                $this->api = $gpt_image_changer->get_api_handler();
            }
            
            // If still not available, log an error and return
            if (!$this->api) {
                $this->log_message('API handler is missing. Cannot process image.', 'error');
                return false;
            }
        }
        
        // Log test processing
        $this->log_message(sprintf('TEST: Processing image ID %d', $image_id), 'info');
        
        // Get product ID if this is a product image
        $product_id = $this->get_product_id_from_image($image_id);
        
        // Get product information for context
        $product_context = '';
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_context = sprintf(
                    "Product Title: %s\nProduct Description: %s",
                    $product->get_name(),
                    wp_strip_all_tags($product->get_description())
                );
                
                // Check if this is a variation image
                global $wpdb;
                $variation_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_thumbnail_id' 
                    AND meta_value = %d
                    AND post_id IN (
                        SELECT ID FROM {$wpdb->posts}
                        WHERE post_type = 'product_variation'
                        AND post_parent = %d
                    )",
                    $image_id,
                    $product_id
                ));
                
                if ($variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        // Add variation attributes to context
                        $variation_attributes = $variation->get_attributes();
                        $attribute_details = array();
                        
                        foreach ($variation_attributes as $attribute_name => $attribute_value) {
                            $taxonomy = str_replace('attribute_', '', $attribute_name);
                            
                            // If it's a taxonomy attribute
                            if (taxonomy_exists($taxonomy)) {
                                $term = get_term_by('slug', $attribute_value, $taxonomy);
                                if ($term) {
                                    $attribute_details[] = wc_attribute_label($taxonomy) . ': ' . $term->name;
                                }
                            } else {
                                // If it's a custom attribute
                                $attribute_details[] = wc_attribute_label($taxonomy) . ': ' . $attribute_value;
                            }
                        }
                        
                        if (!empty($attribute_details)) {
                            $product_context .= "\nVariation Details: " . implode(', ', $attribute_details);
                        }
                    }
                }
            }
        }
        
        // Process image with API
        $result = $this->api->process_image($image_id, $product_context);
        
        // Check if the result is a WP_Error object
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $this->log_message(sprintf('TEST: Failed to process image ID %d: %s', $image_id, $error_message), 'error');
            
            // Return the error information in a structured way
            return array(
                'success' => false,
                'error' => $error_message,
                'debug' => $this->api->get_last_debug_info()
            );
        }
        
        // Check if the result is empty or invalid
        if (!$result || !is_array($result)) {
            $this->log_message(sprintf('TEST: Failed to process image ID %d: Invalid API result', $image_id), 'error');
            return false;
        }
        
        // Get the last debug info from API
        $debug_info = $this->api->get_last_debug_info();
        
        // Get current file path and filename
        $current_file = get_attached_file($image_id);
        $current_filename = basename($current_file);
        $current_filename_no_ext = pathinfo($current_filename, PATHINFO_FILENAME);
        
        // Prepare the "would update" filename information
        $new_filename = '';
        if (!empty($result['filename'])) {
            $file_ext = pathinfo($current_filename, PATHINFO_EXTENSION);
            $new_filename = sanitize_file_name($result['filename']) . '.' . $file_ext;
        }
        
        // Prepare return data
        $return_data = array(
            'success' => true,
            'image_id' => $image_id,
            'result' => $result,
            'current_metadata' => $this->get_current_image_metadata($image_id),
            'would_update' => $this->preview_image_metadata_update($image_id, $result),
            'current_filename' => $current_filename,
            'new_filename' => $new_filename,
            'debug' => $debug_info
        );
        
        $this->log_message(sprintf('TEST: Successfully processed image ID %d', $image_id), 'info');
        
        return $return_data;
    }

    /**
     * Get current image metadata
     *
     * @param int $image_id Image ID
     * @return array Current metadata
     */
    private function get_current_image_metadata($image_id) {
        $current = array(
            'title' => get_the_title($image_id),
            'alt_text' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
            'caption' => wp_get_attachment_caption($image_id),
            'description' => get_post_field('post_content', $image_id),
            'filename' => basename(get_attached_file($image_id))
        );
        
        return $current;
    }

    /**
     * Preview metadata update without actually updating
     *
     * @param int $image_id Image ID
     * @param array $api_result API result data
     * @return array Preview of updates
     */
    private function preview_image_metadata_update($image_id, $api_result) {
        $current = $this->get_current_image_metadata($image_id);
        $updates = array();
        
        // Check title update
        if (!empty($api_result['title']) && $api_result['title'] !== $current['title']) {
            $updates['title'] = $api_result['title'];
        }
        
        // Check alt text update
        if (!empty($api_result['alt_text']) && $api_result['alt_text'] !== $current['alt_text']) {
            $updates['alt_text'] = $api_result['alt_text'];
        }
        
        // Check caption update
        if (!empty($api_result['caption']) && $api_result['caption'] !== $current['caption']) {
            $updates['caption'] = $api_result['caption'];
        }
        
        // Check description update
        if (!empty($api_result['description']) && $api_result['description'] !== $current['description']) {
            $updates['description'] = $api_result['description'];
        }
        
        // Check filename update
        if (!empty($api_result['filename'])) {
            $current_filename = pathinfo($current['filename'], PATHINFO_FILENAME);
            if ($api_result['filename'] !== $current_filename) {
                $file_ext = pathinfo($current['filename'], PATHINFO_EXTENSION);
                $updates['filename'] = $api_result['filename'] . '.' . $file_ext;
            }
        }
        
        return $updates;
    }

    /**
     * Update image metadata with AI-generated content
     *
     * @param int $image_id Image ID
     * @param array $api_result API result data
     * @return bool Success or failure
     */
    private function update_image_metadata($image_id, $api_result) {
        // Validate that api_result is an array
        if (!is_array($api_result)) {
            $this->log_message(sprintf('Cannot update metadata for image ID %d: API result is not an array', $image_id), 'error');
            return false;
        }
        
        // Get the current metadata first to log changes
        $current_metadata = $this->get_current_image_metadata($image_id);
        
        // Start with a successful result
        $success = true;
        $updates = array();
        
        // Update title if provided
        if (!empty($api_result['title'])) {
            $post_data = array(
                'ID' => $image_id,
                'post_title' => sanitize_text_field($api_result['title'])
            );
            
            if (wp_update_post($post_data)) {
                $updates['title'] = array(
                    'from' => $current_metadata['title'],
                    'to' => $api_result['title']
                );
            } else {
                $success = false;
                $this->log_message(sprintf('Failed to update title for image ID %d', $image_id), 'error');
            }
        }
        
        // Update alt text if provided
        if (!empty($api_result['alt_text'])) {
            $alt_text = sanitize_text_field($api_result['alt_text']);
            if (update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text)) {
                $updates['alt_text'] = array(
                    'from' => $current_metadata['alt_text'],
                    'to' => $alt_text
                );
            } else {
                $success = false;
                $this->log_message(sprintf('Failed to update alt text for image ID %d', $image_id), 'error');
            }
        }
        
        // Update caption if provided
        if (!empty($api_result['caption'])) {
            $post_data = array(
                'ID' => $image_id,
                'post_excerpt' => sanitize_text_field($api_result['caption'])
            );
            
            if (wp_update_post($post_data)) {
                $updates['caption'] = array(
                    'from' => $current_metadata['caption'],
                    'to' => $api_result['caption']
                );
            } else {
                $success = false;
                $this->log_message(sprintf('Failed to update caption for image ID %d', $image_id), 'error');
            }
        }
        
        // Update description if provided
        if (!empty($api_result['description'])) {
            $post_data = array(
                'ID' => $image_id,
                'post_content' => wp_kses_post($api_result['description'])
            );
            
            if (wp_update_post($post_data)) {
                $updates['description'] = array(
                    'from' => $current_metadata['description'],
                    'to' => $api_result['description']
                );
            } else {
                $success = false;
                $this->log_message(sprintf('Failed to update description for image ID %d', $image_id), 'error');
            }
        }
        
        // Update filename if provided
        if (!empty($api_result['filename'])) {
            $updated_filename = $this->update_image_filename($image_id, $api_result['filename']);
            
            if ($updated_filename) {
                $updates['filename'] = array(
                    'from' => $current_metadata['filename'],
                    'to' => $updated_filename
                );
            } else {
                // Don't set success to false as filename update is non-critical
                $this->log_message(sprintf('Failed to update filename for image ID %d', $image_id), 'error');
            }
        }
        
        // If debug mode is enabled, log the metadata changes
        if ($this->debug_mode && !empty($updates)) {
            $this->log_message(sprintf('Updated metadata for image ID %d: %s', $image_id, json_encode($updates)), 'info');
        }
        
        return $success;
    }
    
    /**
     * Update image filename
     *
     * @param int $image_id Image ID
     * @param string $new_filename New filename without extension
     * @return string|false New filename with extension or false on failure
     */
    private function update_image_filename($image_id, $new_filename) {
        // Get the current file path
        $current_path = get_attached_file($image_id);
        if (!$current_path || !file_exists($current_path)) {
            return false;
        }
        
        // Get the path information
        $path_info = pathinfo($current_path);
        $upload_dir = wp_upload_dir();
        
        // Sanitize the new filename
        $new_filename = sanitize_file_name($new_filename);
        
        // Make sure we have a valid filename
        if (empty($new_filename)) {
            return false;
        }
        
        // Create the new filename with the same extension
        $new_filename_with_extension = $new_filename . '.' . $path_info['extension'];
        $new_path = $path_info['dirname'] . '/' . $new_filename_with_extension;
        
        // Check if the new path already exists (to avoid overwriting)
        if (file_exists($new_path) && $new_path !== $current_path) {
            // Add a unique suffix to avoid conflicts
            $counter = 1;
            do {
                $new_filename_with_suffix = $new_filename . '-' . $counter . '.' . $path_info['extension'];
                $new_path = $path_info['dirname'] . '/' . $new_filename_with_suffix;
                $counter++;
            } while (file_exists($new_path) && $counter < 100); // Prevent infinite loop
            
            $new_filename_with_extension = $new_filename_with_suffix;
        }
        
        // Attempt to rename the file
        $renamed = rename($current_path, $new_path);
        
        if (!$renamed) {
            return false;
        }
        
        // Update the attachment metadata in the database
        update_attached_file($image_id, $new_path);
        
        // Get all attachment sizes and update their metadata
        $metadata = wp_get_attachment_metadata($image_id);
        
        if (is_array($metadata) && isset($metadata['file'])) {
            // Update the main file
            $old_file = basename($metadata['file']);
            $metadata['file'] = str_replace($old_file, $new_filename_with_extension, $metadata['file']);
            
            // Update the sizes
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_info) {
                    if (isset($size_info['file'])) {
                        $parts = pathinfo($size_info['file']);
                        $new_size_filename = $new_filename . '-' . $parts['filename'] . '.' . $parts['extension'];
                        
                        // Rename the resized file
                        $old_size_path = $path_info['dirname'] . '/' . $size_info['file'];
                        $new_size_path = $path_info['dirname'] . '/' . $new_size_filename;
                        
                        if (file_exists($old_size_path)) {
                            rename($old_size_path, $new_size_path);
                        }
                        
                        // Update the metadata
                        $metadata['sizes'][$size]['file'] = $new_size_filename;
                    }
                }
            }
            
            // Save the updated metadata
            wp_update_attachment_metadata($image_id, $metadata);
            
            // Log the filename change
            $this->log_message(
                sprintf('Updated filename for image ID %d from %s to %s', 
                    $image_id, 
                    basename($current_path), 
                    $new_filename_with_extension
                ), 
                'info'
            );
            
            return $new_filename_with_extension;
        }
        
        return false;
    }

    /**
     * Get product ID that this image belongs to
     *
     * @param int $image_id Image ID
     * @return int|false Product ID or false if not found
     */
    private function get_product_id_from_image($image_id) {
        global $wpdb;
        
        // Check featured image
        $featured_query = $wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_thumbnail_id' 
            AND meta_value = %d
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product'
                AND post_status = 'publish'
            )
            LIMIT 1
        ", $image_id);
        
        $product_id = $wpdb->get_var($featured_query);
        
        if ($product_id) {
            return $product_id;
        }
        
        // Check variation featured image
        $variation_featured_query = $wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_thumbnail_id' 
            AND meta_value = %d
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product_variation'
                AND post_status = 'publish'
            )
            LIMIT 1
        ", $image_id);
        
        $variation_id = $wpdb->get_var($variation_featured_query);
        
        if ($variation_id) {
            // Get parent product ID from variation
            $parent_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d",
                $variation_id
            ));
            
            if ($parent_id) {
                return $parent_id;
            }
        }
        
        // Check gallery images
        $gallery_query = $wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_product_image_gallery'
            AND (meta_value = %d OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product'
                AND post_status = 'publish'
            )
            LIMIT 1
        ", 
            $image_id,
            '%,' . $image_id . ',%', // in the middle
            $image_id . ',%',        // at the beginning
            '%,' . $image_id         // at the end
        );
        
        $product_id = $wpdb->get_var($gallery_query);
        
        return $product_id ? $product_id : false;
    }

    /**
     * Log a message to the process log
     *
     * @param string $message Message to log
     * @param string $type Type of message (info, error)
     * @return void
     */
    public function log_message($message, $type = 'info') {
        $logs = get_option('gic_process_log', array());
        
        // Add new log entry
        $logs[] = array(
            'time' => current_time('mysql'),
            'message' => $message,
            'type' => $type
        );
        
        // Keep only the latest 200 entries
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        
        // Save updated logs
        update_option('gic_process_log', $logs);
    }

    /**
     * Get total count of unprocessed WooCommerce product images
     *
     * @return int Count of unprocessed images
     */
    public static function get_unprocessed_image_count() {
        global $wpdb, $gpt_image_changer;
        
        // Get status handler
        $status = null;
        if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_status_handler')) {
            $status = $gpt_image_changer->get_status_handler();
        }
        
        // If status handler not available, return 0
        if (!$status) {
            return 0;
        }
        
        // Get images that are already in the system
        $processed_images = $status->get_all_processed_image_ids();
        
        // Get all product featured images
        $featured_query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value > 0
        ";
        
        $featured_images = $wpdb->get_col($featured_query);
        
        // Get all variation featured images
        $variation_featured_query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value > 0
        ";
        
        $variation_images = $wpdb->get_col($variation_featured_query);
        
        // Get all gallery images
        $gallery_query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_product_image_gallery'
            AND pm.meta_value <> ''
        ";
        
        $gallery_image_strings = $wpdb->get_col($gallery_query);
        
        // Extract gallery image IDs
        $gallery_images = array();
        foreach ($gallery_image_strings as $gallery_string) {
            $ids = explode(',', $gallery_string);
            $gallery_images = array_merge($gallery_images, $ids);
        }
        
        // Combine all images
        $all_images = array_merge($featured_images, $variation_images, $gallery_images);
        $all_images = array_unique(array_filter($all_images)); // Remove duplicates and empty values
        
        // Filter out already processed images
        $unprocessed_images = array_diff($all_images, $processed_images);
        
        return count($unprocessed_images);
    }

    /**
     * Queue all unprocessed WooCommerce images
     *
     * @param int $limit Maximum number of images to queue at once
     * @return array Array of queued image IDs
     */
    public function queue_all_unprocessed_images($limit = 700) {
        global $wpdb;
        
        // Check if status handler is available
        if (!$this->status) {
            global $gpt_image_changer;
            if ($gpt_image_changer && method_exists($gpt_image_changer, 'get_status_handler')) {
                $this->status = $gpt_image_changer->get_status_handler();
            }
            
            // If still not available, return empty array
            if (!$this->status) {
                $this->log_message('Status handler is missing. Cannot queue images.', 'error');
                return array();
            }
        }
        
        // Log that we're queuing all unprocessed images
        $this->log_message('Queuing all unprocessed WooCommerce product images for processing', 'info');
        
        // Get images that are already in the system
        $processed_images = $this->status->get_all_processed_image_ids();
        
        // Get all product featured images
        $featured_query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value > 0
        ";
        
        $featured_images = $wpdb->get_col($featured_query);
        
        // Get all variation featured images
        $variation_featured_query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product_variation'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value > 0
        ";
        
        $variation_images = $wpdb->get_col($variation_featured_query);
        
        // Get all gallery images
        $gallery_query = "
            SELECT pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_product_image_gallery'
            AND pm.meta_value <> ''
        ";
        
        $gallery_image_strings = $wpdb->get_col($gallery_query);
        
        // Extract gallery image IDs
        $gallery_images = array();
        foreach ($gallery_image_strings as $gallery_string) {
            $ids = explode(',', $gallery_string);
            $gallery_images = array_merge($gallery_images, $ids);
        }
        
        // Combine all images
        $all_images = array_merge($featured_images, $variation_images, $gallery_images);
        $all_images = array_unique(array_filter($all_images)); // Remove duplicates and empty values
        
        // Filter out already processed images
        $unprocessed_images = array_diff($all_images, $processed_images);
        
        // Limit to requested number
        $unprocessed_images = array_slice($unprocessed_images, 0, $limit);
        
        // Add each image to the queue
        $queued = array();
        foreach ($unprocessed_images as $image_id) {
            if ($this->status->add_image_to_queue($image_id)) {
                $queued[] = $image_id;
                
                if ($this->debug_mode) {
                    $this->log_message(sprintf('Queued image ID %d for processing', $image_id), 'info');
                }
            }
        }
        
        $this->log_message(sprintf('Queued %d unprocessed images for processing', count($queued)), 'info');
        
        return $queued;
    }
}