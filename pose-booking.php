<?php
/**
 * Plugin Name: Pose Booking - Photography Sessions
 * Plugin URI: https://posekw.com
 * Description: نظام حجز جلسات التصوير - بسيط ومتكامل مع WooCommerce
 * Version: 2.0.1
 * Author: Pose Media
 * Author URI: https://posekw.com
 * Text Domain: pose-booking
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('POSE_BOOKING_VERSION', '2.0.1');
define('POSE_BOOKING_PATH', plugin_dir_path(__FILE__));
define('POSE_BOOKING_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class - Simplified
 */
class Pose_Booking
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components(); // Load components early
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        require_once POSE_BOOKING_PATH . 'includes/class-session-types.php';
        require_once POSE_BOOKING_PATH . 'includes/class-availability.php';
        require_once POSE_BOOKING_PATH . 'includes/class-booking-form.php';
    }

    private function init_components()
    {
        // Instantiate classes so they can register their hooks (e.g. 'init' for register_post_type)
        Pose_Session_Types::get_instance();
        Pose_Availability::get_instance();
        Pose_Booking_Form::get_instance();
    }

    private function init_hooks()
    {
        // General plugin hooks
        add_action('admin_init', array($this, 'maybe_create_tables'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function activate()
    {
        $this->create_tables();

        // Register post type and flush rewrites on activation
        Pose_Session_Types::get_instance()->register_post_type();
        flush_rewrite_rules();
    }

    public function maybe_create_tables()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pose_availability';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $this->create_tables();
        }
    }

    private function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . 'pose_availability';
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_type_id BIGINT UNSIGNED NOT NULL,
            recurring_day VARCHAR(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            max_bookings INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_type_id),
            INDEX idx_day (recurring_day)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function enqueue_public_assets()
    {
        if (!is_singular() && !has_shortcode(get_post()->post_content ?? '', 'pose_booking') && !has_shortcode(get_post()->post_content ?? '', 'pose_booking_form')) {
            return;
        }

        wp_enqueue_style(
            'pose-booking-public',
            POSE_BOOKING_URL . 'public/css/booking-style.css',
            array(),
            POSE_BOOKING_VERSION
        );

        wp_enqueue_script(
            'pose-booking-public',
            POSE_BOOKING_URL . 'public/js/booking-form.js',
            array('jquery'),
            POSE_BOOKING_VERSION,
            true
        );

        wp_localize_script('pose-booking-public', 'poseBooking', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pose_booking_nonce'),
            'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : ''
        ));
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'pose-booking') === false && get_post_type() !== 'pose_session_type') {
            return;
        }

        wp_enqueue_style(
            'pose-booking-admin',
            POSE_BOOKING_URL . 'admin/css/admin-style.css',
            array(),
            POSE_BOOKING_VERSION
        );
    }
}

// Initialize
add_action('plugins_loaded', function () {
    Pose_Booking::get_instance();
});
