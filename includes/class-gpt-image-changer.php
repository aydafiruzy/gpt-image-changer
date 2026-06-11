<?php
/**
 * Main plugin class
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class GPT_Image_Changer {
    /**
     * Plugin instance
     *
     * @var GPT_Image_Changer
     */
    private static $instance = null;

    /**
     * Admin settings class instance
     *
     * @var GPT_Image_Changer_Admin
     */
    public $admin;

    /**
     * API handler class instance
     *
     * @var GPT_Image_Changer_API
     */
    public $api;

    /**
     * Image processor class instance
     *
     * @var GPT_Image_Changer_Processor
     */
    public $processor;

    /**
     * Status handler class instance
     *
     * @var GPT_Image_Changer_Status
     */
    public $status;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init() {
        global $gpt_image_changer;
        
        // Make sure we're registered in the global variable
        $gpt_image_changer = $this;
        
        // Initialize admin
        $this->admin = new GPT_Image_Changer_Admin();
        
        // Initialize API
        $this->api = new GPT_Image_Changer_API();
        
        // Initialize status
        $this->status = new GPT_Image_Changer_Status();
        
        // Initialize processor - after API and Status are available
        $this->processor = new GPT_Image_Changer_Processor();
        
        // Now initialize each component
        $this->api->init();
        $this->status->init();
        $this->admin->init();
        $this->processor->init();

        // Add cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Add cron job if not already scheduled
        if (!wp_next_scheduled('gic_process_images_cron')) {
            wp_schedule_event(time(), 'daily', 'gic_process_images_cron');
        }
        
        // Add cron job action
        add_action('gic_process_images_cron', array($this->processor, 'process_scheduled_images'));
    }

    /**
     * Load required dependencies
     *
     * @return void
     */
    private function load_dependencies() {
        // Include admin class
        require_once GIC_PLUGIN_DIR . 'includes/admin/class-gpt-image-changer-admin.php';
        
        // Include API class
        require_once GIC_PLUGIN_DIR . 'includes/api/class-gpt-image-changer-api.php';
        
        // Include processor class
        require_once GIC_PLUGIN_DIR . 'includes/processor/class-gpt-image-changer-processor.php';
        
        // Include status class
        require_once GIC_PLUGIN_DIR . 'includes/status/class-gpt-image-changer-status.php';
    }

    /**
     * Define the activation hook for the plugin
     */
    public static function activate() {
        // Set default options
        self::set_default_options();
        
        // Create necessary database tables
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for storing image processing status
        $table_name = $wpdb->prefix . 'gic_image_status';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            image_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            result longtext,
            PRIMARY KEY  (id),
            KEY image_id (image_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Clear any existing scheduled tasks
        wp_clear_scheduled_hook('gic_process_scheduled_images');
        wp_clear_scheduled_hook('gic_process_images_cron');
        
        // Schedule our task to run every 2 minutes with 5 images
        if (!wp_next_scheduled('gic_process_scheduled_images')) {
            wp_schedule_event(time(), 'two_minutes', 'gic_process_scheduled_images');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default options for the plugin
     */
    private static function set_default_options() {
        // Set default options if they don't exist
        if (get_option('gic_api_key') === false) {
            update_option('gic_api_key', '');
        }
        
        if (get_option('gic_api_model') === false) {
            update_option('gic_api_model', 'gpt-4-vision-preview');
        }
        
        if (get_option('gic_debug_mode') === false) {
            update_option('gic_debug_mode', false);
        }
    }
    
    /**
     * Define the deactivation hook for the plugin
     */
    public static function deactivate() {
        // Clear scheduled tasks
        wp_clear_scheduled_hook('gic_process_scheduled_images');
        wp_clear_scheduled_hook('gic_process_images_cron');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['gic_hourly'] = array(
            'interval' => 3600, // 1 hour in seconds
            'display'  => __('Every Hour', 'gpt-image-changer')
        );
        
        $schedules['two_minutes'] = array(
            'interval' => 120, // 2 minutes in seconds
            'display'  => __('Every 2 Minutes', 'gpt-image-changer'),
        );
        
        return $schedules;
    }

    /**
     * Get API handler instance
     *
     * @return GPT_Image_Changer_API
     */
    public function get_api_handler() {
        return $this->api;
    }

    /**
     * Get Status handler instance
     *
     * @return GPT_Image_Changer_Status
     */
    public function get_status_handler() {
        return $this->status;
    }

    /**
     * Get Processor instance
     *
     * @return GPT_Image_Changer_Processor
     */
    public function get_processor() {
        return $this->processor;
    }

    /**
     * Get Admin instance
     *
     * @return GPT_Image_Changer_Admin
     */
    public function get_admin() {
        return $this->admin;
    }
} 