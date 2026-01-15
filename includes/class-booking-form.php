<?php
/**
 * Booking Form Shortcode - Simplified
 */

if (!defined('ABSPATH')) {
    exit;
}

class Pose_Booking_Form
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
        add_shortcode('pose_booking', array($this, 'render_form'));
        add_shortcode('pose_booking_form', array($this, 'render_form')); // Backwards compatible
        add_action('wp_ajax_pose_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_pose_add_to_cart', array($this, 'ajax_add_to_cart'));

        // Show booking details in cart and order
        add_filter('woocommerce_get_item_data', array($this, 'show_in_cart'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_to_order'), 10, 4);
    }

    /**
     * Render booking form shortcode
     */
    public function render_form($atts)
    {
        $sessions = Pose_Session_Types::get_all();

        $options = get_option('pose_booking_options');
        $theme_mode = isset($options['theme_mode']) ? $options['theme_mode'] : 'light';
        $container_class = 'pose-booking-container';
        if ($theme_mode === 'dark') {
            $container_class .= ' pose-dark-mode';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($container_class); ?>">
            <div class="pose-booking-form">
                <!-- Step 1: Session Type -->
                <div class="booking-step active" data-step="1">
                    <h3 class="step-title">📸
                        <?php _e('اختر نوع الجلسة', 'pose-booking'); ?>
                    </h3>
                    <div class="session-types-grid">
                        <?php foreach ($sessions as $s):
                            $wc_product_id = isset($s['wc_product']) ? $s['wc_product'] : 0;
                            $price_html = $s['price'] . ' ' . __('د.ك', 'pose-booking');
                            $description = '';
                            $product_type = 'simple';
                            $product_url = '';

                            if ($wc_product_id && class_exists('WooCommerce')) {
                                $product = wc_get_product($wc_product_id);
                                if ($product) {
                                    $price_html = $product->get_price_html();
                                    $description = $product->get_short_description(); // Get short description
                                    $product_type = $product->get_type();
                                    $product_url = $product->get_permalink();
                                }
                            }
                            ?>
                            <div class="session-type-card" data-id="<?php echo $s['id']; ?>" data-wc="<?php echo $wc_product_id; ?>"
                                data-type="<?php echo esc_attr($product_type); ?>" data-url="<?php echo esc_url($product_url); ?>"
                                style="--card-color: <?php echo $s['color']; ?>">

                                <span class="session-icon">
                                    <?php echo $s['icon']; ?>
                                </span>
                                <h4>
                                    <?php echo esc_html($s['title']); ?>
                                </h4>

                                <?php if ($description): ?>
                                    <div class="session-desc" style="font-size: 0.9em; color: #666; margin: 10px 0; line-height: 1.4;">
                                        <?php echo wp_kses_post($description); ?>
                                    </div>
                                <?php endif; ?>

                                <p class="session-price" style="margin-top: auto;">
                                    <?php echo $price_html; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Date & Time -->
                <!-- Step 2: Removed (Direct Booking) -->

                <!-- Hidden fields -->
                <input type="hidden" id="h-session" value="">
                <input type="hidden" id="h-session-name" value="">
                <input type="hidden" id="h-wc-product" value="">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add to WooCommerce cart (AJAX)
     */
    public function ajax_add_to_cart()
    {
        check_ajax_referer('pose_booking_nonce', 'nonce');

        $wc_product = intval($_POST['wc_product']);
        $session_name = sanitize_text_field($_POST['session_name']);

        if (!$wc_product) {
            wp_send_json_error('بيانات ناقصة');
        }

        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce غير مفعل');
        }

        WC()->cart->empty_cart();

        $cart_data = array(
            'pose_booking' => true,
            'pose_session_name' => $session_name,
            'pose_date' => 'يحدد لاحقاً', // Placeholder
            'pose_time' => '-',
        );

        $added = WC()->cart->add_to_cart($wc_product, 1, 0, array(), $cart_data);

        if ($added) {
            wp_send_json_success(array('checkout_url' => wc_get_checkout_url()));
        } else {
            wp_send_json_error('حدث خطأ');
        }
    }

    /**
     * Show booking details in cart
     */
    public function show_in_cart($item_data, $cart_item)
    {
        if (!empty($cart_item['pose_booking'])) {
            $item_data[] = array('key' => __('الجلسة', 'pose-booking'), 'value' => $cart_item['pose_session_name']);
            $item_data[] = array('key' => __('التاريخ', 'pose-booking'), 'value' => $cart_item['pose_date']);
            $item_data[] = array('key' => __('الوقت', 'pose-booking'), 'value' => $cart_item['pose_time']);
        }
        return $item_data;
    }

    /**
     * Save booking details to order
     */
    public function save_to_order($item, $cart_item_key, $values, $order)
    {
        if (!empty($values['pose_booking'])) {
            $item->add_meta_data(__('الجلسة', 'pose-booking'), $values['pose_session_name']);
            $item->add_meta_data(__('التاريخ', 'pose-booking'), $values['pose_date']);
            $item->add_meta_data(__('الوقت', 'pose-booking'), $values['pose_time']);
        }
    }
}
