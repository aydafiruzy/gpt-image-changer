<?php
/**
 * Plugin Name: GPT Image Updater
 * Description: Optimize WooCommerce product image Names and Alt Texts with ChatGPT for better SEO.
 * Version: 2.2.1
 * Author: AydaFirouzy
 * Author URI: https://bondimarketing.au/
 * Text Domain: gpt-image-changer
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GIC_PLUGIN_FILE', __FILE__);
define('GIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('GIC_VERSION', '1.0.0');

// Check if WooCommerce is active
register_activation_hook(__FILE__, 'gic_activation_check');
function gic_activation_check() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and activated.', 'gpt-image-changer'));
    }
    
    // Call the static activation method
    GPT_Image_Changer::activate();
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'gic_deactivation');
function gic_deactivation() {
    // Call the static deactivation method
    GPT_Image_Changer::deactivate();
}

// Load plugin files
require_once GIC_PLUGIN_DIR . 'includes/class-gpt-image-changer.php';

// Global plugin instance
global $gpt_image_changer;

// Initialize the plugin
function gic_init() {
    global $gpt_image_changer;
    $gpt_image_changer = new GPT_Image_Changer();
    $gpt_image_changer->init();
}
add_action('plugins_loaded', 'gic_init', 10); 