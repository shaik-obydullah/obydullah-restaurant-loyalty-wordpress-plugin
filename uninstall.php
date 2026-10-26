<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * WordPress loads this file on its own, without the plugin's main file, so the
 * constants defined there are not present. The table prefix is not among the
 * problems that creates, because includes/class-obrl-schema.php below defines
 * OBYDRELO_TABLE_PREFIX itself, guarded, since it is the class that reads it. The cron hook
 * further down still has to be written as a literal for the same reason.
 */
require_once __DIR__ . '/includes/class-obrl-schema.php';
require_once __DIR__ . '/includes/class-obrl-settings.php';
require_once __DIR__ . '/includes/class-obrl-roles.php';

$obydrelo_table = OBYDRELO_Schema::table('program_settings');
$obydrelo_delete_data = 0;
if ($obydrelo_table) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs while the plugin is being deleted, when nothing may be served from a cache.
    $obydrelo_value = $wpdb->get_var(
        $wpdb->prepare('SELECT setting_value FROM %i WHERE setting_key = %s', $obydrelo_table, 'delete_data_on_uninstall')
    );
    $obydrelo_delete_data = (int) $obydrelo_value;
}

wp_clear_scheduled_hook('obydrelo_daily_maintenance');
wp_clear_scheduled_hook('obydrelo_daily_maintenance'); // Legacy pre-1.0.0 hook name.
OBYDRELO_Roles::uninstall();

$obydrelo_user_ids = array();
if ($obydrelo_delete_data) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs while the plugin is being deleted, when nothing may be served from a cache.
    $obydrelo_user_ids = $wpdb->get_col(
        $wpdb->prepare('SELECT DISTINCT user_id FROM %i WHERE meta_key = %s AND meta_value = %s', $wpdb->usermeta, '_obydrelo_created_by_plugin', '1')
    );
    if (!OBYDRELO_Schema::drop()) {
        return;
    }
}

foreach ((array) $obydrelo_user_ids as $obydrelo_user_id) {
    $obydrelo_user_id = absint($obydrelo_user_id);
    $obydrelo_user = $obydrelo_user_id ? get_userdata($obydrelo_user_id) : null;
    if (!$obydrelo_user || array_diff((array) $obydrelo_user->roles, array('obydrelo_member'))) {
        continue;
    }
    $obydrelo_authored = get_posts(
        array(
            'author'     => $obydrelo_user_id,
            'post_type'  => 'any',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'     => 'ids',
        )
    );
    if ($obydrelo_authored) {
        continue;
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $obydrelo_deleted = wp_delete_user($obydrelo_user_id);
    if (is_wp_error($obydrelo_deleted) || !$obydrelo_deleted) {
        return;
    }
}

if (!$obydrelo_delete_data) {
    return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs while the plugin is being deleted, when nothing may be served from a cache.
$obydrelo_cleared = $wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key IN (%s, %s)',
        $wpdb->usermeta,
        '_obydrelo_created_by_plugin',
        '_obydrelo_member_id'
    )
);
if (false === $obydrelo_cleared) {
    return;
}
