<?php
/**
 * Admin settings class
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings class
 */
class GPT_Image_Changer_Admin {
    /**
     * Initialize admin
     *
     * @return void
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add action links to plugins page
        add_filter('plugin_action_links_' . GIC_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        // Add Ajax actions
        add_action('wp_ajax_gic_test_api_key', array($this, 'ajax_test_api_key'));
        add_action('wp_ajax_gic_test_image_processing', array($this, 'ajax_test_image_processing'));
        add_action('wp_ajax_gic_get_unprocessed_count', array($this, 'ajax_get_unprocessed_count'));
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('GPT Image Changer', 'gpt-image-changer'),
            __('GPT Image Changer', 'gpt-image-changer'),
            'manage_options',
            'gpt-image-changer',
            array($this, 'render_settings_page'),
            'dashicons-format-image',
            100
        );
        
        // Settings submenu
        add_submenu_page(
            'gpt-image-changer',
            __('Settings', 'gpt-image-changer'),
            __('Settings', 'gpt-image-changer'),
            'manage_options',
            'gpt-image-changer',
            array($this, 'render_settings_page')
        );
        
        // Status submenu
        add_submenu_page(
            'gpt-image-changer',
            __('Status', 'gpt-image-changer'),
            __('Status', 'gpt-image-changer'),
            'manage_options',
            'gpt-image-changer-status',
            array($this, 'render_status_page')
        );
        
        // Logs submenu
        add_submenu_page(
            'gpt-image-changer',
            __('Logs', 'gpt-image-changer'),
            __('Logs', 'gpt-image-changer'),
            'manage_options',
            'gpt-image-changer-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings() {
        // Register setting
        register_setting(
            'gic_settings',
            'gic_settings',
            array($this, 'sanitize_settings')
        );
        
        // Add settings section - API Settings
        add_settings_section(
            'gic_api_settings_section',
            __('API Settings', 'gpt-image-changer'),
            array($this, 'render_api_settings_section'),
            'gpt-image-changer'
        );
        
        // Add API Key field
        add_settings_field(
            'gic_api_key',
            __('ChatGPT API Key', 'gpt-image-changer'),
            array($this, 'render_api_key_field'),
            'gpt-image-changer',
            'gic_api_settings_section'
        );
        
        // Add GPT Model field
        add_settings_field(
            'gic_gpt_model',
            __('GPT Model', 'gpt-image-changer'),
            array($this, 'render_gpt_model_field'),
            'gpt-image-changer',
            'gic_api_settings_section'
        );
        
        // Add settings section - Processing Settings
        add_settings_section(
            'gic_processing_settings_section',
            __('Processing Settings', 'gpt-image-changer'),
            array($this, 'render_processing_settings_section'),
            'gpt-image-changer'
        );
        
        // Add Batch Size field
        add_settings_field(
            'gic_batch_size',
            __('Batch Size', 'gpt-image-changer'),
            array($this, 'render_batch_size_field'),
            'gpt-image-changer',
            'gic_processing_settings_section'
        );
        
        // Add Schedule field
        add_settings_field(
            'gic_schedule',
            __('Processing Schedule', 'gpt-image-changer'),
            array($this, 'render_schedule_field'),
            'gpt-image-changer',
            'gic_processing_settings_section'
        );
        
        // Add Enable/Disable field
        add_settings_field(
            'gic_enabled',
            __('Enable Processing', 'gpt-image-changer'),
            array($this, 'render_enabled_field'),
            'gpt-image-changer',
            'gic_processing_settings_section'
        );
        
        // Add Debug mode field
        add_settings_field(
            'gic_debug_mode',
            __('Debug Mode', 'gpt-image-changer'),
            array($this, 'render_debug_mode_field'),
            'gpt-image-changer',
            'gic_processing_settings_section'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize API key
        $sanitized['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        
        // Sanitize GPT model
        $sanitized['gpt_model'] = isset($input['gpt_model']) ? sanitize_text_field($input['gpt_model']) : 'gpt-4-vision-preview';
        
        // Sanitize batch size
        $sanitized['batch_size'] = isset($input['batch_size']) ? absint($input['batch_size']) : 5;
        if ($sanitized['batch_size'] < 1) {
            $sanitized['batch_size'] = 1;
        }
        
        // Sanitize schedule
        $valid_schedules = array('hourly', 'daily', 'twicedaily', 'weekly');
        $sanitized['schedule'] = isset($input['schedule']) && in_array($input['schedule'], $valid_schedules)
            ? $input['schedule']
            : 'daily';
        
        // Sanitize enabled
        $sanitized['enabled'] = isset($input['enabled']) && $input['enabled'] ? true : false;
        
        // Sanitize debug mode
        $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] ? true : false;
        
        // Preserve last run time
        $current_settings = get_option('gic_settings', array());
        $sanitized['last_run'] = isset($current_settings['last_run']) ? $current_settings['last_run'] : null;
        
        return $sanitized;
    }

    /**
     * Render API settings section
     *
     * @return void
     */
    public function render_api_settings_section() {
        echo '<p>' . esc_html__('Configure your ChatGPT API settings.', 'gpt-image-changer') . '</p>';
    }

    /**
     * Render processing settings section
     *
     * @return void
     */
    public function render_processing_settings_section() {
        echo '<p>' . esc_html__('Configure how the image processing works.', 'gpt-image-changer') . '</p>';
    }

    /**
     * Render API key field
     *
     * @return void
     */
    public function render_api_key_field() {
        $settings = get_option('gic_settings', array());
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        
        echo '<div class="gic-api-key-field">';
        echo '<input type="password" id="gic_api_key" name="gic_settings[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<button type="button" id="gic-test-api-key" class="button">' . esc_html__('Test API Key', 'gpt-image-changer') . '</button>';
        echo '<span class="spinner" style="float:none;"></span>';
        echo '<div id="gic-api-key-result"></div>';
        echo '<p class="description">' . esc_html__('Enter your ChatGPT API key from OpenAI.', 'gpt-image-changer') . '</p>';
        echo '</div>';
    }

    /**
     * Render GPT model field
     *
     * @return void
     */
    public function render_gpt_model_field() {
        $settings = get_option('gic_settings', array());
        $gpt_model = isset($settings['gpt_model']) ? $settings['gpt_model'] : 'gpt-4-vision-preview';
        
        $models = array(
            'gpt-4-vision-preview' => __('GPT-4 Vision Preview', 'gpt-image-changer'),
            'gpt-4o' => __('GPT-4o', 'gpt-image-changer'),
        );
        
        echo '<select id="gic_gpt_model" name="gic_settings[gpt_model]">';
        foreach ($models as $model_id => $model_name) {
            echo '<option value="' . esc_attr($model_id) . '" ' . selected($gpt_model, $model_id, false) . '>' . esc_html($model_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Select the GPT model to use for image processing.', 'gpt-image-changer') . '</p>';
    }

    /**
     * Render batch size field
     *
     * @return void
     */
    public function render_batch_size_field() {
        $settings = get_option('gic_settings', array());
        $batch_size = isset($settings['batch_size']) ? absint($settings['batch_size']) : 5;
        
        echo '<input type="number" id="gic_batch_size" name="gic_settings[batch_size]" value="' . esc_attr($batch_size) . '" class="small-text" min="1" max="20" />';
        echo '<p class="description">' . esc_html__('Number of images to process in each batch.', 'gpt-image-changer') . '</p>';
    }

    /**
     * Render schedule field
     *
     * @return void
     */
    public function render_schedule_field() {
        $settings = get_option('gic_settings', array());
        $schedule = isset($settings['schedule']) ? $settings['schedule'] : 'daily';
        
        $schedules = array(
            'hourly' => __('Every Hour', 'gpt-image-changer'),
            'daily' => __('Once Daily', 'gpt-image-changer'),
            'twicedaily' => __('Twice Daily', 'gpt-image-changer'),
            'weekly' => __('Once Weekly', 'gpt-image-changer'),
        );
        
        echo '<select id="gic_schedule" name="gic_settings[schedule]">';
        foreach ($schedules as $schedule_id => $schedule_name) {
            echo '<option value="' . esc_attr($schedule_id) . '" ' . selected($schedule, $schedule_id, false) . '>' . esc_html($schedule_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('How often should the automated processing run.', 'gpt-image-changer') . '</p>';
    }

    /**
     * Render enabled field
     *
     * @return void
     */
    public function render_enabled_field() {
        $settings = get_option('gic_settings', array());
        $enabled = isset($settings['enabled']) ? (bool) $settings['enabled'] : false;
        
        echo '<label for="gic_enabled">';
        echo '<input type="checkbox" id="gic_enabled" name="gic_settings[enabled]" value="1" ' . checked($enabled, true, false) . ' />';
        echo esc_html__('Enable automatic image processing', 'gpt-image-changer');
        echo '</label>';
        echo '<p class="description">' . esc_html__('When enabled, images will be automatically processed on the selected schedule.', 'gpt-image-changer') . '</p>';
    }
    
    /**
     * Render debug mode field
     *
     * @return void
     */
    public function render_debug_mode_field() {
        $settings = get_option('gic_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? (bool) $settings['debug_mode'] : false;
        
        echo '<label for="gic_debug_mode">';
        echo '<input type="checkbox" id="gic_debug_mode" name="gic_settings[debug_mode]" value="1" ' . checked($debug_mode, true, false) . ' />';
        echo esc_html__('Enable debug mode', 'gpt-image-changer');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Records detailed logs of API requests and responses for troubleshooting.', 'gpt-image-changer') . '</p>';
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add a manual processing button
        $manual_process_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page' => 'gpt-image-changer',
                    'action' => 'manual_process',
                ),
                admin_url('admin.php')
            ),
            'gic_manual_process'
        );
        
        // Check for manual processing action
        if (isset($_GET['action']) && $_GET['action'] === 'manual_process' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'gic_manual_process')) {
            // Trigger manual processing
            do_action('gic_process_images_cron');
            
            // Add admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Manual processing triggered. Check the Status page for results.', 'gpt-image-changer') . '</p></div>';
            });
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('gic_settings');
                do_settings_sections('gpt-image-changer');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Manual Processing', 'gpt-image-changer'); ?></h2>
            <p><?php esc_html_e('Click the button below to manually process a batch of images.', 'gpt-image-changer'); ?></p>
            <a href="<?php echo esc_url($manual_process_url); ?>" class="button button-primary"><?php esc_html_e('Process Images Now', 'gpt-image-changer'); ?></a>
            
            <hr>
            
            <h2><?php esc_html_e('Test Image Processing', 'gpt-image-changer'); ?></h2>
            <p><?php esc_html_e('Use this tool to test the image processing functionality with a specific image.', 'gpt-image-changer'); ?></p>
            
            <div class="gic-test-section">
                <div class="gic-test-image-picker">
                    <div id="gic-test-image-preview"></div>
                    <input type="hidden" id="gic-test-image-id" value="">
                    <button type="button" id="gic-select-test-image" class="button"><?php esc_html_e('Select Image', 'gpt-image-changer'); ?></button>
                </div>
                
                <div class="gic-test-controls">
                    <button type="button" id="gic-process-test-image" class="button button-primary" disabled><?php esc_html_e('Process Test Image', 'gpt-image-changer'); ?></button>
                </div>
                
                <div id="gic-test-results" class="gic-test-results"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render status page
     *
     * @return void
     */
    public function render_status_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the status page template
        require_once GIC_PLUGIN_DIR . 'includes/admin/views/status-page.php';
    }
    
    /**
     * Render logs page
     *
     * @return void
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include the logs page template
        require_once GIC_PLUGIN_DIR . 'includes/admin/views/logs-page.php';
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on plugin pages
        if (strpos($hook, 'gpt-image-changer') === false) {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'gic-admin-styles',
            GIC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GIC_VERSION
        );
        
        // Enqueue WordPress media scripts
        wp_enqueue_media();
        
        // Enqueue admin JS
        wp_enqueue_script(
            'gic-admin-scripts',
            GIC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            GIC_VERSION,
            true
        );
        
        // Localize admin script
        wp_localize_script('gic-admin-scripts', 'gicAdmin', array(
            'nonce' => wp_create_nonce('gic_admin_nonce'),
            'testing' => __('Testing...', 'gpt-image-changer'),
            'testApiKey' => __('Test API Key', 'gpt-image-changer'),
            'processingImage' => __('Processing image...', 'gpt-image-changer'),
            'selectImage' => __('Select or upload an image', 'gpt-image-changer'),
            'testProcessImage' => __('Process Test Image', 'gpt-image-changer'),
            'metadataChanges' => __('Metadata Changes', 'gpt-image-changer'),
            'field' => __('Field', 'gpt-image-changer'),
            'currentValue' => __('Current Value', 'gpt-image-changer'),
            'newValue' => __('New Value', 'gpt-image-changer'),
            'title' => __('Title', 'gpt-image-changer'),
            'altText' => __('Alt Text', 'gpt-image-changer'),
            'caption' => __('Caption', 'gpt-image-changer'),
            'description' => __('Description', 'gpt-image-changer'),
            'filename' => __('Filename', 'gpt-image-changer'),
            'debugInformation' => __('Debug Information', 'gpt-image-changer'),
            'apiKeyEmpty' => __('Please enter an API key.', 'gpt-image-changer'),
            'apiKeyValid' => __('API key is valid.', 'gpt-image-changer'),
            'apiKeyInvalid' => __('API key is invalid', 'gpt-image-changer'),
            'ajaxError' => __('An error occurred while communicating with the server.', 'gpt-image-changer'),
            'noChanges' => __('No changes', 'gpt-image-changer'),
            'noMetadataChanges' => __('No metadata changes were suggested.', 'gpt-image-changer'),
            'errorOccurred' => __('An error occurred.', 'gpt-image-changer'),
            'confirmResetProcessing' => __('Are you sure you want to reset all processing images? This will mark them as failed.', 'gpt-image-changer'),
            'confirmRequeueFailed' => __('Are you sure you want to requeue all failed images? This will mark them as pending again.', 'gpt-image-changer'),
            'confirmRequeueSingle' => __('Are you sure you want to requeue this image? This will mark it as pending again.', 'gpt-image-changer'),
            'confirmClearHistory' => __('Are you sure you want to clear the processing history? This action cannot be undone.', 'gpt-image-changer'),
            'confirmClearLogs' => __('Are you sure you want to clear all logs? This action cannot be undone.', 'gpt-image-changer'),
            'confirmQueueAll' => __('Are you sure you want to queue all unprocessed WooCommerce product images? This may add many images to the queue.', 'gpt-image-changer')
        ));
    }

    /**
     * Add action links to plugins page
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=gpt-image-changer') . '">' . __('Settings', 'gpt-image-changer') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Handle AJAX API key test
     *
     * @return void
     */
    public function ajax_test_api_key() {
        check_ajax_referer('gic_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You don\'t have permission to do this.', 'gpt-image-changer'));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(__('API key is empty.', 'gpt-image-changer'));
        }
        
        // Create API instance
        $api = new GPT_Image_Changer_API();
        
        // Test the key
        $result = $api->validate_api_key($api_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('API key is valid! Connection to OpenAI was successful.', 'gpt-image-changer'));
        }
    }
    
    /**
     * AJAX handler for testing image processing
     */
    public function ajax_test_image_processing() {
        check_ajax_referer('gic_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You don\'t have permission to do this.', 'gpt-image-changer'));
        }
        
        $image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;
        
        if (empty($image_id)) {
            wp_send_json_error(__('No image selected.', 'gpt-image-changer'));
        }
        
        // Check if image exists
        $image = get_post($image_id);
        
        if (!$image || $image->post_type !== 'attachment' || strpos($image->post_mime_type, 'image/') !== 0) {
            wp_send_json_error(__('Invalid image selected.', 'gpt-image-changer'));
        }
        
        // Get settings
        $settings = get_option('gic_settings', array());
        
        // Check if API key is set
        if (empty($settings['api_key'])) {
            wp_send_json_error(__('API key is not set. Please save your API key in the settings.', 'gpt-image-changer'));
        }
        
        // Create processor instance
        $processor = new GPT_Image_Changer_Processor();
        
        // Test process the image
        $result = $processor->test_process_image($image_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else if (!$result) {
            wp_send_json_error(__('Failed to process image. Check the logs for more details.', 'gpt-image-changer'));
        } else if (isset($result['success']) && $result['success'] === false) {
            // Handle the new error format that includes debug info
            $error_message = isset($result['error']) ? $result['error'] : __('Unknown error occurred.', 'gpt-image-changer');
            $debug_info = isset($result['debug']) ? $result['debug'] : null;
            
            wp_send_json_error(array(
                'message' => $error_message,
                'debug_info' => $debug_info
            ));
        } else {
            // Return the successful test result
            wp_send_json_success(array(
                'success' => true,
                'image_id' => $image_id,
                'current_metadata' => isset($result['current_metadata']) ? $result['current_metadata'] : array(),
                'would_update' => isset($result['would_update']) ? $result['would_update'] : array(),
                'current_filename' => isset($result['current_filename']) ? $result['current_filename'] : '',
                'new_filename' => isset($result['new_filename']) ? $result['new_filename'] : '',
                'result' => isset($result['result']) ? $result['result'] : array(),
                'debug_info' => isset($result['debug']) ? $result['debug'] : null
            ));
        }
    }

    /**
     * AJAX handler to get unprocessed image count
     *
     * @return void
     */
    public function ajax_get_unprocessed_count() {
        check_ajax_referer('gic_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You don\'t have permission to do this.', 'gpt-image-changer'));
        }
        
        // Get unprocessed image count
        $count = GPT_Image_Changer_Processor::get_unprocessed_image_count();
        
        wp_send_json_success(array(
            'count' => $count
        ));
    }
} 