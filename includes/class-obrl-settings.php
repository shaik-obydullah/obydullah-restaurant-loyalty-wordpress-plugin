<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Settings
{
    const GROUP_GENERAL = 'general';
    const GROUP_PROGRAM = 'program';
    const GROUP_REWARDS = 'rewards';
    const GROUP_BIRTHDAY = 'birthday';
    const GROUP_REFERRAL = 'referral';
    const GROUP_PRIVACY = 'privacy';

    private static $cache = null;

    /**
     * Object cache group for the whole settings set.
     *
     * Every entry is stored as a single value rather than one key per setting,
     * so a change is one delete instead of a scatter of them, and there is no
     * window in which half the settings are the new values and half the old.
     */
    const CACHE_GROUP = 'obrl_settings';

    public static function modes()
    {
        return array(
            'points' => __('Points', 'obydullah-restaurant-loyalty'),
            'stamps' => __('Stamps', 'obydullah-restaurant-loyalty'),
            'visits' => __('Visits', 'obydullah-restaurant-loyalty'),
        );
    }

    /**
     * Setting definitions used to seed the program_settings table and to
     * render the settings screen. Each entry declares its default value,
     * control type, group, and the allowed choices for select controls.
     */
    public static function definitions()
    {
        return array(
            'program_mode' => array(
                'value'  => 'points',
                'type'   => 'select',
                'group'  => self::GROUP_PROGRAM,
                'choices' => self::modes(),
            ),
            'points_per_currency' => array(
                'value' => 1.0000,
                'type'  => 'decimal',
                'group' => self::GROUP_PROGRAM,
            ),
            'points_value' => array(
                'value' => 0.01,
                'type'  => 'decimal',
                'group' => self::GROUP_PROGRAM,
            ),
            'currency_code' => array(
                'value' => 'USD',
                'type'  => 'text',
                'group' => self::GROUP_PROGRAM,
            ),
            'points_label' => array(
                'value' => 'Points',
                'type'  => 'text',
                'group' => self::GROUP_GENERAL,
            ),
            'stamps_label' => array(
                'value' => 'Stamps',
                'type'  => 'text',
                'group' => self::GROUP_GENERAL,
            ),
            'visits_label' => array(
                'value' => 'Visits',
                'type'  => 'text',
                'group' => self::GROUP_GENERAL,
            ),
            'reward_validity_days' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_REWARDS,
            ),
            'expiry_grace_days' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_REWARDS,
            ),
            'allow_negative_points' => array(
                'value' => 0,
                'type'  => 'bool',
                'group' => self::GROUP_REWARDS,
            ),
            'redemption_min_points' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_REWARDS,
            ),
            'birthday_reward_enabled' => array(
                'value' => 1,
                'type'  => 'bool',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'birthday_reward_points' => array(
                'value' => 50,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'birthday_reward_stamps' => array(
                'value' => 1,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'birthday_reward_visits' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'birthday_reward_lead_days' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'welcome_reward_enabled' => array(
                'value' => 1,
                'type'  => 'bool',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'welcome_reward_points' => array(
                'value' => 20,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'welcome_reward_stamps' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'welcome_reward_visits' => array(
                'value' => 0,
                'type'  => 'int',
                'group' => self::GROUP_BIRTHDAY,
            ),
            'referral_enabled' => array(
                'value' => 1,
                'type'  => 'bool',
                'group' => self::GROUP_REFERRAL,
            ),
            'referral_qualification' => array(
                'value'  => 'first_purchase',
                'type'   => 'select',
                'group'  => self::GROUP_REFERRAL,
                'choices' => array(
                    'signup'        => __('On signup', 'obydullah-restaurant-loyalty'),
                    'first_purchase' => __('On first purchase', 'obydullah-restaurant-loyalty'),
                ),
            ),
            'referral_points_referrer' => array(
                'value' => 100,
                'type'  => 'int',
                'group' => self::GROUP_REFERRAL,
            ),
            'referral_points_referred' => array(
                'value' => 50,
                'type'  => 'int',
                'group' => self::GROUP_REFERRAL,
            ),
            'member_code_prefix' => array(
                'value' => 'MBR',
                'type'  => 'text',
                'group' => self::GROUP_GENERAL,
            ),
            'allow_public_signup' => array(
                'value' => 0,
                'type'  => 'bool',
                'group' => self::GROUP_GENERAL,
            ),
            'require_email' => array(
                'value' => 0,
                'type'  => 'bool',
                'group' => self::GROUP_GENERAL,
            ),
            'require_phone' => array(
                'value' => 0,
                'type'  => 'bool',
                'group' => self::GROUP_GENERAL,
            ),
            'allow_portal_redeem' => array(
                'value' => 1,
                'type'  => 'bool',
                'group' => self::GROUP_GENERAL,
            ),
            'allow_portal_profile_edit' => array(
                'value' => 1,
                'type'  => 'bool',
                'group' => self::GROUP_GENERAL,
            ),
            'delete_data_on_uninstall' => array(
                'value' => 0,
                'type'  => 'bool',
                'group' => self::GROUP_PRIVACY,
            ),
        );
    }

    public static function defaults()
    {
        $defaults = array();
        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['value'];
        }
        return $defaults;
    }

    public static function all()
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        $settings = self::defaults();
        if (!OBRL_Schema::is_installed()) {
            self::$cache = $settings;
            return $settings;
        }

        /*
         * The set is read on nearly every request, by the birthday cron, the
         * welcome grant, referral qualification, and the privacy exporter, but
         * it only changes when an administrator edits it. The per-request
         * static above stops the second and later reads within one request;
         * this stops the first read from reaching the table at all on the next
         * one. set() drops the key, so an edit takes effect on the request
         * after it rather than whenever a cache entry happened to expire.
         */
        $cache_key = self::cache_key();
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (is_array($cached)) {
            self::$cache = $cached;
            return $cached;
        }

        global $wpdb;
        $table = OBRL_Schema::table('program_settings');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads go through all(), which caches the whole set in the object cache and drops it in set(); the write and the existence check that chooses between insert and update are current to the row being written.
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT setting_key, setting_value FROM %i', $table),
            ARRAY_A
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (array_key_exists($row['setting_key'], $settings)) {
                    $settings[$row['setting_key']] = self::cast(
                        $row['setting_key'],
                        $row['setting_value']
                    );
                }
            }
        }

        // A day is far longer than any realistic gap between an edit and the
        // delete that follows it, and flush_cache() covers the normal path.
        wp_cache_set($cache_key, $settings, self::CACHE_GROUP, DAY_IN_SECONDS);

        self::$cache = $settings;
        return $settings;
    }

    public static function get($key, $fallback = null)
    {
        $settings = self::all();
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        return $fallback;
    }

    /**
     * Drops both the per-request copy and the object cache entry.
     *
     * Called after a successful write in set() and after seeding on install,
     * so neither can leave a stale set behind. Leaving DAY_IN_SECONDS to expire
     * an entry that a write should have removed is how a setting silently
     * reverts, so the delete is not left to the expiry.
     */
    public static function flush_cache()
    {
        self::$cache = null;
        wp_cache_delete(self::cache_key(), self::CACHE_GROUP);
    }

    /**
     * Cache key for the whole settings set on this site.
     *
     * Derived from the resolved table name so that it follows the prefix if
     * that ever changes, and so that two sites sharing a persistent object
     * cache, which is what happens on multisite, cannot read each other's
     * settings. It uses no plugin constant, because this file is
     * also loaded by uninstall.php, where the plugin's constants are absent.
     *
     * @return string
     */
    private static function cache_key()
    {
        return 'all_' . md5((string) OBRL_Schema::table('program_settings'));
    }

    public static function set($key, $value)
    {
        $definitions = self::definitions();
        if (!isset($definitions[$key]) || !OBRL_Schema::is_installed()) {
            return false;
        }

        global $wpdb;
        $table = OBRL_Schema::table('program_settings');
        $value = self::sanitize($key, $value, $definitions[$key]);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads go through all(), which caches the whole set in the object cache and drops it in set(); the write and the existence check that chooses between insert and update are current to the row being written.
        $existing = $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM %i WHERE setting_key = %s', $table, $key)
        );

        if (null === $existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads go through all(), which caches the whole set in the object cache and drops it in set(); the write and the existence check that chooses between insert and update are current to the row being written.
            $result = $wpdb->insert(
                $table,
                array(
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'setting_group' => $definitions[$key]['group'],
                    'updated_by'    => get_current_user_id(),
                    'updated_at'    => current_time('mysql', true),
                ),
                array('%s', '%s', '%s', '%d', '%s')
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads go through all(), which caches the whole set in the object cache and drops it in set(); the write and the existence check that chooses between insert and update are current to the row being written.
            $result = $wpdb->update(
                $table,
                array(
                    'setting_value' => $value,
                    'updated_by'    => get_current_user_id(),
                    'updated_at'    => current_time('mysql', true),
                ),
                array('id' => (int) $existing),
                array('%s', '%d', '%s'),
                array('%d')
            );
        }

        if (false === $result) {
            return false;
        }

        self::flush_cache();
        return true;
    }

    private static function cast($key, $raw)
    {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            return $raw;
        }
        return self::cast_type($raw, $definitions[$key]['type']);
    }

    private static function cast_type($raw, $type)
    {
        switch ($type) {
            case 'bool':
                return (int) $raw ? 1 : 0;
            case 'int':
                return (int) $raw;
            case 'decimal':
                return (float) $raw;
            default:
                return (string) $raw;
        }
    }

    private static function sanitize($key, $value, $definition)
    {
        $type = $definition['type'];
        if ('select' === $type && isset($definition['choices'])) {
            $value = (string) $value;
            return array_key_exists($value, $definition['choices']) ? $value : $definition['value'];
        }
        if ('bool' === $type) {
            return $value ? 1 : 0;
        }
        if ('int' === $type) {
            return (int) $value;
        }
        if ('decimal' === $type) {
            return (float) $value;
        }
        return sanitize_text_field((string) $value);
    }
}
