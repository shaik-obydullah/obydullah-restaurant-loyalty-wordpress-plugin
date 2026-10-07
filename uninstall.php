<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * WordPress loads this file on its own, without the plugin's main file, so the
 * constants defined there are not present. The table prefix is not among the
 * problems that creates, because includes/class-obrl-schema.php below defines
 * OBRL itself, guarded, since it is the class that reads it. The cron hook
 * further down still has to be written as a literal for the same reason.
 */
require_once __DIR__ . '/includes/class-obrl-schema.php';
require_once __DIR__ . '/includes/class-obrl-settings.php';
require_once __DIR__ . '/includes/class-obrl-roles.php';

$obrl_table = OBRL_Schema::table('program_settings');
$obrl_delete_data = 0;
if ($obrl_table) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs while the plugin is being deleted, when nothing may be served from a cache.
    $obrl_value = $wpdb->get_var(
        $wpdb->prepare('SELECT setting_value FROM %i WHERE setting_key = %s', $obrl_table, 'delete_data_on_uninstall')
    );
    $obrl_delete_data = (int) $obrl_value;
}

wp_clear_scheduled_hook('obrl_daily_maintenance');
wp_clear_scheduled_hook('orl_daily_maintenance'); // Legacy pre-1.0.0 hook name.
OBRL_Roles::uninstall();

$obrl_user_ids = array();
if ($obrl_delete_data) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs while the plugin is being deleted, when nothing may be served from a cache.
    $obrl_user_ids = $wpdb->get_col(
        $wpdb->prepare('SELECT DISTINCT user_id FROM %i WHERE meta_key = %s AND meta_value = %s', $wpdb->usermeta, '_orl_created_by_plugin', '1')
    );
    if (!OBRL_Schema::drop()) {
        return;
    }
}

foreach ((array) $obrl_user_ids as $obrl_user_id) {
    $obrl_user_id = absint($obrl_user_id);
    $obrl_user = $obrl_user_id ? get_userdata($obrl_user_id) : null;
    if (!$obrl_user || array_diff((array) $obrl_user->roles, array('orl_member'))) {
        continue;
    }
    $obrl_authored = get_posts(
        array(
            'author'     => $obrl_user_id,
            'post_type'  => 'any',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'     => 'ids',
        )
    );
    if ($obrl_authored) {
        continue;
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $obrl_deleted = wp_delete_user($obrl_user_id);
    if (is_wp_error($obrl_deleted) || !$obrl_deleted) {
        return;
    }
}

if (!$obrl_delete_data) {
    return;
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs while the plugin is being deleted, when nothing may be served from a cache.
$obrl_cleared = $wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key IN (%s, %s)',
        $wpdb->usermeta,
        '_orl_created_by_plugin',
        '_orl_member_id'
    )
);
if (false === $obrl_cleared) {
    return;
}
