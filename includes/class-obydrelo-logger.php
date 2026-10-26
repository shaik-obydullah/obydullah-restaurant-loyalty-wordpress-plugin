<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Logger
{
    /**
     * Records an audit entry. Logging never interrupts the caller, so a
     * failure is reported to debug.log and otherwise ignored.
     */
    public static function log($action, $args = array())
    {
        if (!OBYDRELO_Schema::is_installed()) {
            return false;
        }

        $table = OBYDRELO_Schema::table('activity_logs');
        if (!$table) {
            return false;
        }

        $args = wp_parse_args(
            $args,
            array(
                'member_id'   => 0,
                'object_type' => '',
                'object_id'   => 0,
                'severity'    => 'info',
                'message'     => '',
                'context'     => array(),
                'actor_type'  => '',
            )
        );

        if (!$args['actor_type']) {
            $args['actor_type'] = is_user_logged_in() ? 'user' : 'guest';
        }

        $user = wp_get_current_user();
        $context = $args['context'];
        if (!is_string($context)) {
            // An empty array must still be encoded, otherwise wpdb::insert()
            // receives an array and the query is silently malformed.
            $context = empty($context) ? '' : wp_json_encode($context);
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Append-only audit writes, which are by definition not re-read through this path.
        $result = $wpdb->insert(
            $table,
            array(
                'member_id'   => $args['member_id'] ? (int) $args['member_id'] : null,
                'user_id'     => $user ? (int) $user->ID : 0,
                'user_login'  => $user && isset($user->user_login) ? substr($user->user_login, 0, 60) : '',
                'actor_type'  => $args['actor_type'],
                'object_type' => substr($args['object_type'], 0, 30),
                'object_id'   => (int) $args['object_id'],
                'action'      => substr($action, 0, 50),
                'severity'    => $args['severity'],
                'message'     => $args['message'],
                'context'     => $context,
                'ip_address'  => self::client_ip(),
                'user_agent'  => self::user_agent(),
                'created_at'  => current_time('mysql', true),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (false === $result) {
            if ($wpdb->last_error && defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostics.
                error_log('[obydullah-restaurant-loyalty] activity log failed: ' . $wpdb->last_error);
            }
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    private static function client_ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private static function user_agent()
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }
        return substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255);
    }
}
