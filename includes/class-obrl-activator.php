<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Activator
{
    public static function activate()
    {
        if (!OBRL_Schema::install()) {
            wp_die(esc_html__('Obydullah Restaurant Loyalty could not create its database tables. Check the database logs and try again.', 'obydullah-restaurant-loyalty'));
        }

        OBRL_Roles::install();
        self::seed_settings();
        self::ensure_cron();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(OBRL_CRON_DAILY);
        flush_rewrite_rules();
    }

    public static function maybe_upgrade()
    {
        if (!OBRL_Schema::is_installed()) {
            if (!OBRL_Schema::install()) {
                return;
            }
            OBRL_Roles::install();
            self::seed_settings();
        }

        self::ensure_cron();
    }

    public static function ensure_cron()
    {
        if (!wp_next_scheduled(OBRL_CRON_DAILY)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', OBRL_CRON_DAILY);
        }
    }

    /**
     * Inserts any program setting that is not yet present. Existing values are
     * never overwritten so administrator choices survive upgrades.
     */
    public static function seed_settings()
    {
        global $wpdb;
        $table = OBRL_Schema::table('program_settings');
        if (!$table) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs on activation and on the version check, before any cache is warm, and only writes.
        $existing = $wpdb->get_col($wpdb->prepare('SELECT setting_key FROM %i', $table));
        $existing = is_array($existing) ? $existing : array();

        $now = current_time('mysql', true);
        foreach (OBRL_Settings::definitions() as $key => $definition) {
            if (in_array($key, $existing, true)) {
                continue;
            }
            $value = $definition['value'];
            $value = is_bool($value) ? (int) $value : (string) $value;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Runs on activation and on the version check, before any cache is warm, and only writes.
            $wpdb->insert(
                $table,
                array(
                    'setting_key'   => $key,
                    'setting_value' => (string) $value,
                    'setting_group' => $definition['group'],
                    'updated_by'    => 0,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s')
            );
        }

        OBRL_Settings::flush_cache();
    }
}
