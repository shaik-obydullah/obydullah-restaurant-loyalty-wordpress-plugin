<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Rewards
{
    const TYPES = 'fixed_value,percentage,free_item';

    public static function types()
    {
        return array(
            'fixed_value' => __('Fixed value', 'obydullah-restaurant-loyalty'),
            'percentage'  => __('Percentage discount', 'obydullah-restaurant-loyalty'),
            'free_item'   => __('Free item', 'obydullah-restaurant-loyalty'),
        );
    }

    public static function table()
    {
        return OBYDRELO_Schema::table('rewards');
    }

    public static function get($reward_id)
    {
        global $wpdb;
        // Reject rather than abs(): a negative id must never resolve to a real row.
        $reward_id = is_numeric($reward_id) ? (int) $reward_id : 0;
        $table = self::table();
        if (!$table || $reward_id < 1) {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d AND deleted_at IS NULL',
                $table,
                $reward_id
            ),
            ARRAY_A
        );
        return $row ? self::hydrate($row) : null;
    }

    public static function get_by_slug($slug)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE slug = %s AND deleted_at IS NULL',
                $table,
                $slug
            ),
            ARRAY_A
        );
        return $row ? self::hydrate($row) : null;
    }

    public static function validate($data, $reward_id = 0)
    {
        $errors = array();
        $data = wp_parse_args(
            $data,
            array(
                'title'            => '',
                'description'      => '',
                'type'             => 'fixed_value',
                'points_cost'      => 0,
                'stamps_cost'      => 0,
                'visits_cost'      => 0,
                'value'            => 0,
                'percent_off'      => 0,
                'max_discount'     => 0,
                'min_purchase'     => 0,
                'item_name'        => '',
                'usage_limit'      => null,
                'per_member_limit' => 0,
                'validity_days'    => 0,
                'sort_order'       => 0,
                'is_active'        => 1,
            )
        );

        $data['title'] = sanitize_text_field($data['title']);
        $data['description'] = sanitize_textarea_field($data['description']);
        $data['item_name'] = sanitize_text_field($data['item_name']);

        if ('' === $data['title']) {
            $errors['title'] = __('A reward title is required.', 'obydullah-restaurant-loyalty');
        }
        if (!array_key_exists($data['type'], self::types())) {
            $errors['type'] = __('Choose a reward type.', 'obydullah-restaurant-loyalty');
        }

        $data['points_cost'] = max(0, round((float) $data['points_cost'], 2));
        $data['stamps_cost'] = max(0, (int) $data['stamps_cost']);
        $data['visits_cost'] = max(0, (int) $data['visits_cost']);
        $data['value'] = max(0, round((float) $data['value'], 2));
        $data['percent_off'] = min(100, max(0, round((float) $data['percent_off'], 2)));
        $data['max_discount'] = max(0, round((float) $data['max_discount'], 2));
        $data['min_purchase'] = max(0, round((float) $data['min_purchase'], 2));
        $data['per_member_limit'] = max(0, (int) $data['per_member_limit']);
        $data['validity_days'] = max(0, (int) $data['validity_days']);
        $data['sort_order'] = (int) $data['sort_order'];
        $data['is_active'] = $data['is_active'] ? 1 : 0;

        if (null !== $data['usage_limit'] && '' !== $data['usage_limit']) {
            $data['usage_limit'] = (int) $data['usage_limit'] > 0 ? (int) $data['usage_limit'] : null;
        } else {
            $data['usage_limit'] = null;
        }

        if ('percentage' === $data['type'] && $data['percent_off'] <= 0) {
            $errors['percent_off'] = __('Enter a discount percentage greater than zero.', 'obydullah-restaurant-loyalty');
        }
        if ('fixed_value' === $data['type'] && $data['value'] <= 0) {
            $errors['value'] = __('Enter the reward value.', 'obydullah-restaurant-loyalty');
        }
        if ('free_item' === $data['type'] && '' === $data['item_name']) {
            $errors['item_name'] = __('Name the free item.', 'obydullah-restaurant-loyalty');
        }

        // max() returns an int when the value is zero, so compare numerically
        // rather than with a strict float identity check.
        if ((float) $data['points_cost'] <= 0 && (int) $data['stamps_cost'] <= 0 && (int) $data['visits_cost'] <= 0) {
            $errors['points_cost'] = __('Set a cost in points, stamps, or visits.', 'obydullah-restaurant-loyalty');
        }

        if ($errors) {
            return new WP_Error('obydrelo_reward_invalid', __('Please correct the highlighted fields.', 'obydullah-restaurant-loyalty'), $errors);
        }

        return $data;
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return new WP_Error('obydrelo_no_schema', __('The loyalty database is not installed.', 'obydullah-restaurant-loyalty'));
        }

        $clean = self::validate($data);
        if (is_wp_error($clean)) {
            return $clean;
        }

        $now = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        $result = $wpdb->insert(
            $table,
            array(
                'title'            => $clean['title'],
                'slug'             => self::unique_slug($clean['title']),
                'description'      => $clean['description'],
                'type'             => $clean['type'],
                'points_cost'      => $clean['points_cost'],
                'stamps_cost'      => $clean['stamps_cost'],
                'visits_cost'      => $clean['visits_cost'],
                'value'            => $clean['value'],
                'percent_off'      => $clean['percent_off'],
                'max_discount'     => $clean['max_discount'],
                'min_purchase'     => $clean['min_purchase'],
                'item_name'        => $clean['item_name'],
                'usage_limit'      => $clean['usage_limit'],
                'per_member_limit' => $clean['per_member_limit'],
                'validity_days'    => $clean['validity_days'],
                'sort_order'       => $clean['sort_order'],
                'is_active'        => $clean['is_active'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array('%s', '%s', '%s', '%s', '%f', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if (false === $result) {
            return new WP_Error('obydrelo_reward_create_failed', __('The reward could not be saved.', 'obydullah-restaurant-loyalty'));
        }

        $reward_id = (int) $wpdb->insert_id;
        OBYDRELO_Logger::log(
            'reward_created',
            array('object_type' => 'reward', 'object_id' => $reward_id, 'message' => $clean['title'])
        );
        return $reward_id;
    }

    public static function update($reward_id, $data)
    {
        global $wpdb;
        $table = self::table();
        $reward = self::get($reward_id);
        if (!$table || !$reward) {
            return new WP_Error('obydrelo_reward_missing', __('That reward no longer exists.', 'obydullah-restaurant-loyalty'));
        }

        $clean = self::validate($data, $reward_id);
        if (is_wp_error($clean)) {
            return $clean;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        $result = $wpdb->update(
            $table,
            array(
                'title'            => $clean['title'],
                'description'      => $clean['description'],
                'type'             => $clean['type'],
                'points_cost'      => $clean['points_cost'],
                'stamps_cost'      => $clean['stamps_cost'],
                'visits_cost'      => $clean['visits_cost'],
                'value'            => $clean['value'],
                'percent_off'      => $clean['percent_off'],
                'max_discount'     => $clean['max_discount'],
                'min_purchase'     => $clean['min_purchase'],
                'item_name'        => $clean['item_name'],
                'usage_limit'      => $clean['usage_limit'],
                'per_member_limit' => $clean['per_member_limit'],
                'validity_days'    => $clean['validity_days'],
                'sort_order'       => $clean['sort_order'],
                'is_active'        => $clean['is_active'],
                'updated_at'       => current_time('mysql', true),
            ),
            array('id' => (int) $reward_id),
            array('%s', '%s', '%s', '%f', '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%d', '%s'),
            array('%d')
        );

        if (false === $result) {
            return new WP_Error('obydrelo_reward_update_failed', __('The reward could not be updated.', 'obydullah-restaurant-loyalty'));
        }

        OBYDRELO_Logger::log(
            'reward_updated',
            array('object_type' => 'reward', 'object_id' => (int) $reward_id, 'message' => $clean['title'])
        );
        return true;
    }

    public static function delete($reward_id)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        $result = $wpdb->update(
            $table,
            array('is_active' => 0, 'deleted_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)),
            array('id' => (int) $reward_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        if (false === $result) {
            return false;
        }
        OBYDRELO_Logger::log(
            'reward_deleted',
            array('object_type' => 'reward', 'object_id' => (int) $reward_id, 'severity' => 'warning')
        );
        return true;
    }

    public static function list_rewards($args = array())
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return array();
        }
        $args = wp_parse_args($args, array('active_only' => false, 'type' => ''));

        // Both filters are optional, so each is an always-present predicate
        // that goes neutral when unused: '0 = 0' and "'' = ''" hold for every
        // row. One literal query shape keeps every value bound.
        $type       = array_key_exists( $args['type'], self::types() ) ? $args['type'] : '';
        $active     = $args['active_only'] ? 1 : 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i
                  WHERE deleted_at IS NULL
                    AND (%d = 0 OR is_active = %d)
                    AND (%s = \'\' OR type = %s)
                  ORDER BY sort_order ASC, id ASC',
                $table,
                $active,
                $active,
                $type,
                $type
            )
        );
        return array_map(array(__CLASS__, 'hydrate'), (array) $rows);
    }

    public static function stock_remaining($reward)
    {
        $limit = null === $reward['usage_limit'] ? null : (int) $reward['usage_limit'];
        if (null === $limit) {
            return null;
        }
        return max(0, $limit - (int) $reward['used_count']);
    }

    /**
     * Checks whether a member may redeem a reward right now. Returns
     * true or a WP_Error explaining the refusal.
     */
    public static function can_redeem($member, $reward, $bill_amount = 0.0)
    {
        if (!$member || !$reward) {
            return new WP_Error('obydrelo_missing', __('Member or reward not found.', 'obydullah-restaurant-loyalty'));
        }
        if ('active' !== $member['status']) {
            return new WP_Error('obydrelo_inactive_member', __('This member is not active.', 'obydullah-restaurant-loyalty'));
        }
        if (!$reward['is_active']) {
            return new WP_Error('obydrelo_inactive_reward', __('This reward is not currently available.', 'obydullah-restaurant-loyalty'));
        }

        $remaining = self::stock_remaining($reward);
        if (null !== $remaining && $remaining < 1) {
            return new WP_Error('obydrelo_out_of_stock', __('This reward is out of stock.', 'obydullah-restaurant-loyalty'));
        }

        if ($reward['per_member_limit'] > 0) {
            $used = self::member_redemption_count($member['id'], $reward['id']);
            if ($used >= (int) $reward['per_member_limit']) {
                return new WP_Error(
                    'obydrelo_per_member_limit',
                    sprintf(
                        /* translators: %d: number of redemptions already used. */
                        __('This member has already used this reward %d time(s).', 'obydullah-restaurant-loyalty'),
                        $used
                    )
                );
            }
        }

        $bill_amount = (float) $bill_amount;
        if ($reward['min_purchase'] > 0 && $bill_amount < (float) $reward['min_purchase']) {
            return new WP_Error(
                'obydrelo_min_purchase',
                sprintf(
                    /* translators: %s: formatted minimum purchase amount. */
                    __('This reward requires a minimum purchase of %s.', 'obydullah-restaurant-loyalty'),
                    self::format_amount($reward['min_purchase'])
                )
            );
        }

        $shortfall = array();
        if ((float) $reward['points_cost'] > (float) $member['points_balance']) {
            $shortfall[] = sprintf(
                /* translators: %s: formatted points. */
                __('%s points', 'obydullah-restaurant-loyalty'),
                number_format_i18n((float) $reward['points_cost'] - (float) $member['points_balance'], 0)
            );
        }
        if ((int) $reward['stamps_cost'] > (int) $member['stamps_count']) {
            $shortfall[] = sprintf(
                /* translators: %d: stamp count. */
                __('%d stamps', 'obydullah-restaurant-loyalty'),
                (int) $reward['stamps_cost'] - (int) $member['stamps_count']
            );
        }
        if ((int) $reward['visits_cost'] > (int) $member['visits_count']) {
            $shortfall[] = sprintf(
                /* translators: %d: visit count. */
                __('%d visits', 'obydullah-restaurant-loyalty'),
                (int) $reward['visits_cost'] - (int) $member['visits_count']
            );
        }

        if ($shortfall) {
            return new WP_Error(
                'obydrelo_insufficient_balance',
                sprintf(
                    /* translators: %s: list of shortfalls. */
                    __('Not enough balance. Still needed: %s.', 'obydullah-restaurant-loyalty'),
                    implode(', ', $shortfall)
                )
            );
        }

        $minimum = (int) OBYDRELO_Settings::get('redemption_min_points', 0);
        if ($minimum > 0 && (float) $reward['points_cost'] < $minimum && (float) $member['points_balance'] < $minimum) {
            return new WP_Error(
                'obydrelo_min_points',
                sprintf(
                    /* translators: %d: minimum points. */
                    __('A redemption needs at least %d points.', 'obydullah-restaurant-loyalty'),
                    $minimum
                )
            );
        }

        return true;
    }

    public static function member_redemption_count($member_id, $reward_id)
    {
        global $wpdb;
        $table = OBYDRELO_Schema::table('redemptions');
        if (!$table) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE member_id = %d AND reward_id = %d AND status IN ('issued','used')",
                $table,
                (int) $member_id,
                (int) $reward_id
            )
        );
    }

    /**
     * Resolves the monetary value of a reward against a bill, honouring the
     * percentage cap.
     */
    public static function redeemable_value($reward, $bill_amount)
    {
        $bill_amount = max(0.0, (float) $bill_amount);
        if ('percentage' === $reward['type']) {
            $value = $bill_amount * ((float) $reward['percent_off'] / 100);
            if ((float) $reward['max_discount'] > 0) {
                $value = min($value, (float) $reward['max_discount']);
            }
            return round(min($value, $bill_amount), 2);
        }
        if ('fixed_value' === $reward['type']) {
            return round(min((float) $reward['value'], $bill_amount), 2);
        }
        return round(min((float) $reward['value'], $bill_amount), 2);
    }

    public static function format_amount($amount)
    {
        return OBYDRELO_Format::money($amount);
    }

    private static function unique_slug($title)
    {
        global $wpdb;
        $table = self::table();
        $base = sanitize_title($title);
        $base = '' !== $base ? $base : 'reward';
        $slug = $base;
        $suffix = 1;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reward availability, plus the slug uniqueness loop, which has to be current so that two rewards cannot share a slug.
        while ($table && null !== $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE slug = %s', $table, $slug))) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }
        return $slug;
    }

    private static function hydrate($row)
    {
        if (is_object($row)) {
            $row = (array) $row;
        }
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['points_cost'] = (float) $row['points_cost'];
        $row['stamps_cost'] = (int) $row['stamps_cost'];
        $row['visits_cost'] = (int) $row['visits_cost'];
        $row['value'] = (float) $row['value'];
        $row['percent_off'] = (float) $row['percent_off'];
        $row['max_discount'] = (float) $row['max_discount'];
        $row['min_purchase'] = (float) $row['min_purchase'];
        $row['usage_limit'] = null === $row['usage_limit'] ? null : (int) $row['usage_limit'];
        $row['used_count'] = (int) $row['used_count'];
        $row['per_member_limit'] = (int) $row['per_member_limit'];
        $row['validity_days'] = (int) $row['validity_days'];
        $row['is_active'] = (int) $row['is_active'];
        $row['sort_order'] = (int) $row['sort_order'];
        return $row;
    }
}
