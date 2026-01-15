<?php
/**
 * Availability Management View - Simplified with PHP-only delete
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'pose_availability';

// Handle DELETE (PHP-based, no JavaScript)
if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
    $delete_id = intval($_GET['delete']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_' . $delete_id)) {
        Pose_Availability::delete($delete_id);
        echo '<div class="notice notice-success is-dismissible"><p>تم حذف الوقت بنجاح ✓</p></div>';
    }
}

// Handle ADD (via POST form - no JavaScript)
if (isset($_POST['add_availability']) && isset($_POST['_wpnonce'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'add_availability')) {
        $session_type_id = intval($_POST['session_type_id']);
        $weekdays = isset($_POST['weekdays']) ? (array) $_POST['weekdays'] : array();
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);

        $added = 0;
        foreach ($weekdays as $day) {
            $day = sanitize_text_field($day);

            // Check duplicate
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE session_type_id = %d AND recurring_day = %s AND start_time = %s",
                $session_type_id,
                $day,
                $start_time
            ));

            if (!$exists) {
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
        }

        if ($added > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>تمت إضافة ' . $added . ' وقت بنجاح ✓</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>الأوقات موجودة مسبقاً</p></div>';
        }
    }
}

// Get session types
$sessions = Pose_Session_Types::get_all();

// Get slots
$slots = Pose_Availability::get_all();

// Day names in Arabic
$days_ar = array(
    'Saturday' => 'السبت',
    'Sunday' => 'الأحد',
    'Monday' => 'الاثنين',
    'Tuesday' => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday' => 'الخميس',
    'Friday' => 'الجمعة'
);
?>

<div class="wrap">
    <h1>إدارة الأوقات المتاحة</h1>

    <div style="display: flex; gap: 30px; margin-top: 20px;">

        <!-- Add Form -->
        <div
            style="flex: 0 0 350px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;">➕ إضافة وقت جديد</h2>

            <form method="post">
                <?php wp_nonce_field('add_availability'); ?>
                <input type="hidden" name="add_availability" value="1">

                <p>
                    <label><strong>أوقات العمل (للمصور)</strong></label><br>
                    <span style="color: #666; font-size: 13px;">هذه الأوقات ستكون متاحة لجميع أنواع الجلسات.</span>
                    <input type="hidden" name="session_type_id" value="0">
                </p>

                <p>
                    <label><strong>أيام الأسبوع</strong></label><br>
                    <?php foreach ($days_ar as $en => $ar): ?>
                        <label style="display: inline-block; margin: 3px 8px 3px 0;">
                            <input type="checkbox" name="weekdays[]" value="<?php echo $en; ?>">
                            <?php echo $ar; ?>
                        </label>
                    <?php endforeach; ?>
                </p>

                <p style="display: flex; gap: 10px;">
                    <span style="flex: 1;">
                        <label><strong>من</strong></label><br>
                        <select name="start_time" required style="width: 100%;">
                            <?php for ($h = 8; $h <= 22; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00:00', $h); ?>">
                                    <?php echo sprintf('%02d:00', $h); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span style="flex: 1;">
                        <label><strong>إلى</strong></label><br>
                        <select name="end_time" required style="width: 100%;">
                            <?php for ($h = 9; $h <= 23; $h++): ?>
                                <option value="<?php echo sprintf('%02d:00:00', $h); ?>">
                                    <?php echo sprintf('%02d:00', $h); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </span>
                </p>

                <p>
                    <button type="submit" class="button button-primary button-large" style="width: 100%;">
                        إضافة الوقت
                    </button>
                </p>
            </form>
        </div>

        <!-- Slots List -->
        <div
            style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;">📋 الأوقات المتاحة</h2>

            <?php if (empty($slots)): ?>
                <p style="color: #666;">لا توجد أوقات متاحة. أضف أوقات من النموذج على اليسار.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>اليوم</th>
                            <th>الوقت</th>
                            <th style="width: 80px;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot):
                            $day_name = isset($days_ar[$slot->recurring_day]) ? $days_ar[$slot->recurring_day] : $slot->recurring_day;
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=pose-booking&delete=' . $slot->id),
                                'delete_' . $slot->id
                            );
                            ?>
                            <tr>
                                <td><?php echo $day_name; ?></td>
                                <td><?php echo date('H:i', strtotime($slot->start_time)) . ' - ' . date('H:i', strtotime($slot->end_time)); ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-small"
                                        style="color: #dc2626;">
                                        🗑️ حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>