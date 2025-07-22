<?php
/*
Plugin Name: Cloud Uploader
Description: Upload files of any type to cloud storage
Version: 1.0
Author: Estehamul Hasan Z. Ansari
Author URI: https://xetroot.com
*/

defined('ABSPATH') or die('Direct access not allowed');

// Define constants
define('CLOUD_UPLOADER_VERSION', '1.0');
define('CLOUD_UPLOADER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLOUD_UPLOADER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CLOUD_UPLOADER_PLUGIN_DIR . 'includes/class-settings.php';
require_once CLOUD_UPLOADER_PLUGIN_DIR . 'includes/class-upload-handler.php';
require_once CLOUD_UPLOADER_PLUGIN_DIR . 'includes/class-shortcodes.php';

// Initialize classes
function cloud_uploader_init() {
    new Cloud_Uploader_Settings();
    new Cloud_Uploader_Handler();
    new Cloud_Uploader_Shortcodes();
}
add_action('plugins_loaded', 'cloud_uploader_init');

// Enqueue scripts and styles
function cloud_uploader_assets() {
    wp_enqueue_style(
        'cloud-uploader-css',
        CLOUD_UPLOADER_PLUGIN_URL . 'assets/css/cloud-uploader.css',
        array(),
        CLOUD_UPLOADER_VERSION
    );
    
    wp_enqueue_script(
        'cloud-uploader-js',
        CLOUD_UPLOADER_PLUGIN_URL . 'assets/js/cloud-uploader.js',
        array('jquery'),
        CLOUD_UPLOADER_VERSION,
        true
    );
    
    wp_localize_script('cloud-uploader-js', 'cloudUploader', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cloud_uploader_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'cloud_uploader_assets');
add_action('admin_enqueue_scripts', 'cloud_uploader_assets');