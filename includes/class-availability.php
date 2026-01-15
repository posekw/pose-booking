<?php
/**
 * Availability Management - Simplified
 */

if (!defined('ABSPATH')) {
    exit;
}

class Pose_Availability
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
        // AJAX for adding availability (admin)
        add_action('wp_ajax_pose_add_availability', array($this, 'ajax_add'));

        // AJAX for getting availability (public)
        add_action('wp_ajax_pose_get_slots', array($this, 'ajax_get_slots'));
        add_action('wp_ajax_nopriv_pose_get_slots', array($this, 'ajax_get_slots'));
    }

    /**
     * Add new availability slot (AJAX) - Global Availability
     */
    public function ajax_add()
    {
        check_ajax_referer('pose_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('غير مصرح');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pose_availability';

        // Session Type ID is 0 for Global Availability
        $session_type_id = 0;

        $weekdays = isset($_POST['weekdays']) ? (array) $_POST['weekdays'] : array();
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);

        if (empty($weekdays) || !$start_time || !$end_time) {
            wp_send_json_error('جميع الحقول مطلوبة');
        }

        $added = 0;
        foreach ($weekdays as $day) {
            $day = sanitize_text_field($day);

            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE session_type_id = %d AND recurring_day = %s AND start_time = %s",
                $session_type_id,
                $day,
                $start_time
            ));

            if ($exists)
                continue;

            $wpdb->insert($table, array(
                'session_type_id' => $session_type_id,
                'recurring_day' => $day,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'max_bookings' => 1,
            ), array('%d', '%s', '%s', '%s', '%d'));

            if ($wpdb->insert_id)
                $added++;
        }

        if ($added > 0) {
            wp_send_json_success(array('added' => $added));
        } else {
            wp_send_json_error('الأوقات موجودة مسبقاً');
        }
    }

    /**
     * Get available slots for date (AJAX - Public) - Global Availability
     */
    public function ajax_get_slots()
    {
        check_ajax_referer('pose_booking_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'pose_availability';

        // Ignore posted session_type_id, verify availability globally
        //$session_type_id = intval($_POST['session_type_id']);
        $session_type_id = 0; // Global Slots

        $date = sanitize_text_field($_POST['date']);
        $day_of_week = date('l', strtotime($date));

        $slots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE session_type_id = %d AND recurring_day = %s
             ORDER BY start_time ASC",
            $session_type_id,
            $day_of_week
        ));

        // Check for existing bookings (WooCommerce Orders)
        $booked_slots = array();

        if (function_exists('wc_get_orders')) {
            // Get orders for this specific date only using Meta Query
            $orders = wc_get_orders(array(
                'limit' => -1,
                'status' => array('wc-processing', 'wc-completed', 'wc-on-hold'),
                'type' => 'shop_order',
                'meta_query' => array(
                    array(
                        'key' => __('التاريخ', 'pose-booking'),
                        'value' => $date,
                        'compare' => '='
                    )
                )
            ));

            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $item_date = $item->get_meta(__('التاريخ', 'pose-booking'));
                    $item_time = $item->get_meta(__('الوقت', 'pose-booking'));

                    if ($item_date === $date && !empty($item_time)) {
                        $formatted_time = date('H:i', strtotime($item_time));
                        $booked_slots[] = $formatted_time;
                    }
                }
            }
        }

        $result = array();
        foreach ($slots as $slot) {
            $start_time_formatted = date('H:i', strtotime($slot->start_time));
            $is_booked = in_array($start_time_formatted, $booked_slots);

            $result[] = array(
                'id' => $slot->id,
                'start_time' => $start_time_formatted,
                'end_time' => date('H:i', strtotime($slot->end_time)),
                'is_booked' => $is_booked
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Delete slot (called from PHP, not AJAX)
     */
    public static function delete($slot_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pose_availability';
        return $wpdb->delete($table, array('id' => $slot_id), array('%d'));
    }

    /**
     * Get all slots
     */
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pose_availability';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY recurring_day, start_time");
    }
}
