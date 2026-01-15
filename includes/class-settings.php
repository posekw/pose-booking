<?php

class Pose_Settings
{
    private $options;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function add_plugin_page()
    {
        add_submenu_page(
            'pose-booking',
            __('الإعدادات', 'pose-booking'),
            __('الإعدادات', 'pose-booking'),
            'manage_options',
            'pose-settings',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page()
    {
        $this->options = get_option('pose_booking_options');
        ?>
        <div class="wrap">
            <h1>
                <?php _e('إعدادات الحجز', 'pose-booking'); ?>
            </h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('pose_booking_group');
                do_settings_sections('pose-settings-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init()
    {
        register_setting(
            'pose_booking_group',
            'pose_booking_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'pose_branding_section',
            __('تخصيص المظهر', 'pose-booking'),
            array($this, 'section_callback'),
            'pose-settings-admin'
        );

        add_settings_field(
            'primary_color',
            __('اللون الرئيسي', 'pose-booking'),
            array($this, 'primary_color_callback'),
            'pose-settings-admin',
            'pose_branding_section'
        );

        add_settings_field(
            'theme_mode',
            __('نمط التصميم', 'pose-booking'),
            array($this, 'theme_mode_callback'),
            'pose-settings-admin',
            'pose_branding_section'
        );
    }

    public function sanitize($input)
    {
        $new_input = array();
        if (isset($input['primary_color']))
            $new_input['primary_color'] = sanitize_hex_color($input['primary_color']);

        if (isset($input['theme_mode']))
            $new_input['theme_mode'] = sanitize_text_field($input['theme_mode']);

        return $new_input;
    }

    public function primary_color_callback()
    {
        $val = isset($this->options['primary_color']) ? esc_attr($this->options['primary_color']) : '#6366f1';
        printf(
            '<input type="color" id="primary_color" name="pose_booking_options[primary_color]" value="%s" />',
            $val
        );
    }

    public function theme_mode_callback()
    {
        $val = isset($this->options['theme_mode']) ? esc_attr($this->options['theme_mode']) : 'light';
        ?>
        <select name="pose_booking_options[theme_mode]">
            <option value="light" <?php selected($val, 'light'); ?>>Light (فاتح)</option>
            <option value="dark" <?php selected($val, 'dark'); ?>>Dark Glass (داكن زجاجي)</option>
        </select>
        <p class="description">اختر "Dark Glass" إذا كانت خلفية موقعك غامقة.</p>
        <?php
    }
    public function section_callback()
    {
        echo '<p>' . __('يمكنك هنا تخصيص ألوان وتصميم نموذج الحجز.', 'pose-booking') . '</p>';
    }
}
