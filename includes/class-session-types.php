<?php
/**
 * Session Types - Custom Post Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class Pose_Session_Types
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
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_pose_session_type', array($this, 'save_meta'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function register_post_type()
    {
        register_post_type('pose_session_type', array(
            'labels' => array(
                'name' => __('أنواع الجلسات', 'pose-booking'),
                'singular_name' => __('نوع جلسة', 'pose-booking'),
                'add_new' => __('إضافة نوع جديد', 'pose-booking'),
                'add_new_item' => __('إضافة نوع جلسة جديد', 'pose-booking'),
                'edit_item' => __('تعديل نوع الجلسة', 'pose-booking'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => array('title'),
            'has_archive' => false,
        ));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('حجز الجلسات', 'pose-booking'),
            __('حجز الجلسات', 'pose-booking'),
            'manage_options',
            'pose-booking',
            array($this, 'render_session_types_page'), // Direct to session types
            'dashicons-camera', // Changed icon
            30
        );

        add_submenu_page(
            'pose-booking',
            __('أنواع الجلسات', 'pose-booking'),
            __('أنواع الجلسات', 'pose-booking'),
            'manage_options',
            'edit.php?post_type=pose_session_type'
        );

        add_submenu_page(
            'pose-booking',
            __('الطلبات', 'pose-booking'),
            __('الطلبات (WooCommerce)', 'pose-booking'),
            'manage_options',
            'edit.php?post_type=shop_order'
        );
    }

    public function render_session_types_page()
    {
        wp_redirect(admin_url('edit.php?post_type=pose_session_type'));
        exit;
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'pose_session_details',
            __('تفاصيل الجلسة', 'pose-booking'),
            array($this, 'render_meta_box'),
            'pose_session_type',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('pose_session_meta', 'pose_session_nonce');

        $icon = get_post_meta($post->ID, '_icon', true) ?: '📸';
        $duration = get_post_meta($post->ID, '_duration', true) ?: 60;
        $price = get_post_meta($post->ID, '_price', true) ?: 50;
        $color = get_post_meta($post->ID, '_color', true) ?: '#6366f1';
        $wc_product = get_post_meta($post->ID, '_wc_product', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label>
                        <?php _e('الأيقونة (Emoji)', 'pose-booking'); ?>
                    </label></th>
                <td>
                    <input type="text" name="pose_icon" id="pose_icon" value="<?php echo esc_attr($icon); ?>"
                        style="font-size: 24px; width: 60px;">
                    <br>
                    <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 5px; max-width: 400px;">
                        <!-- Photography -->
                        <span>📸</span> <span>🎥</span> <span>📷</span> <span>📹</span> <span>🎞️</span> <span>🎬</span>
                        <!-- Weddings -->
                        <span>💍</span> <span>👰</span> <span>🤵</span> <span>💒</span> <span>💐</span> <span>🥂</span>
                        <span>🍾</span>
                        <!-- Birthdays & Party -->
                        <span>🎂</span> <span>🍰</span> <span>🎈</span> <span>🎉</span> <span>🎊</span> <span>🎁</span>
                        <span>🎀</span>
                        <!-- Other -->
                        <span>🚗</span> <span>👶</span> <span>🎓</span> <span>👗</span> <span>💄</span> <span>💇</span>
                        <!-- Art & Creativity -->
                        <span>🎨</span> <span>🖌️</span> <span>🖼️</span> <span>🎭</span> <span>✏️</span> <span>✒️</span>
                        <span>🧵</span> <span>🧶</span>
                    </div>
                    <script>
                        jQuery(document).ready(function ($) {
                            $('.form-table span').css({
                                'cursor': 'pointer',
                                'font-size': '20px',
                                'padding': '5px',
                                'border': '1px solid #ddd',
                                'border-radius': '4px',
                                'background': '#fff'
                            }).on('click', function () {
                                $('#pose_icon').val($(this).text());
                            });
                        });
                    </script>
                </td>
            </tr>
            <tr>
                <th><label>
                        <?php _e('اللون', 'pose-booking'); ?>
                    </label></th>
                <td><input type="color" name="pose_color" value="<?php echo esc_attr($color); ?>"></td>
            </tr>
            <tr>
                <th><label>
                        <?php _e('منتج WooCommerce', 'pose-booking'); ?>
                    </label></th>
                <td>
                    <select name="pose_wc_product">
                        <option value="">
                            <?php _e('-- اختر منتج --', 'pose-booking'); ?>
                        </option>
                        <?php
                        if (function_exists('wc_get_products')) {
                            $products = wc_get_products(array('limit' => -1, 'status' => 'publish'));
                            foreach ($products as $product) {
                                printf(
                                    '<option value="%d" %s>%s - %s</option>',
                                    $product->get_id(),
                                    selected($wc_product, $product->get_id(), false),
                                    esc_html($product->get_name()),
                                    $product->get_price() . ' ' . __('د.ك', 'pose-booking')
                                );
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta($post_id)
    {
        if (!isset($_POST['pose_session_nonce']) || !wp_verify_nonce($_POST['pose_session_nonce'], 'pose_session_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $fields = array('icon', 'color', 'wc_product', 'custom_icon_url');
        foreach ($fields as $field) {
            if (isset($_POST['pose_' . $field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST['pose_' . $field]));
            }
        }
    }

    /**
     * Get all session types
     */
    public static function get_all()
    {
        $sessions = get_posts(array(
            'post_type' => 'pose_session_type',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));

        $result = array();
        foreach ($sessions as $session) {
            $result[] = array(
                'id' => $session->ID,
                'title' => $session->post_title,
                'icon' => get_post_meta($session->ID, '_icon', true) ?: '📸',
                'duration' => get_post_meta($session->ID, '_duration', true) ?: 60,
                'price' => get_post_meta($session->ID, '_price', true) ?: 50,
                'color' => get_post_meta($session->ID, '_color', true) ?: '#6366f1',
                'wc_product' => get_post_meta($session->ID, '_wc_product', true),
                'custom_icon_url' => get_post_meta($session->ID, '_custom_icon_url', true),
            );
        }
        return $result;
    }
}
