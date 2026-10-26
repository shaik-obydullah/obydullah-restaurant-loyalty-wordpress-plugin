<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Redemptions
{
    const STATUSES = 'issued,used,cancelled,expired';

    public static function table()
    {
        return OBYDRELO_Schema::table('redemptions');
    }

    public static function get($redemption_id)
    {
        global $wpdb;
        // Reject rather than abs(): a negative id must never resolve to a real row.
        $redemption_id = is_numeric($redemption_id) ? (int) $redemption_id : 0;
        $table = self::table();
        if (!$table || $redemption_id < 1) {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $redemption_id),
            ARRAY_A
        );
        return $row ? self::hydrate($row) : null;
    }

    /**
     * Issues a redemption: reserves reward stock, debits the ledger, and
     * writes a redemption row. Rolls the ledger entry back if stock runs out
     * mid-flight.
     */
    public static function issue($args)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return new WP_Error('obydrelo_no_schema', __('The loyalty database is not installed.', 'obydullah-restaurant-loyalty'));
        }

        $args = wp_parse_args(
            $args,
            array(
                'member_id'     => 0,
                'reward_id'     => 0,
                'bill_amount'   => 0,
                'bill_reference' => '',
                'source'        => 'staff',
                'external_id'   => '',
                'note'          => '',
            )
        );

        $member = OBYDRELO_Members::get($args['member_id']);
        $reward = OBYDRELO_Rewards::get($args['reward_id']);
        if (!$member || !$reward) {
            return new WP_Error('obydrelo_missing', __('Member or reward not found.', 'obydullah-restaurant-loyalty'));
        }

        $bill_amount = max(0.0, (float) $args['bill_amount']);
        $check = OBYDRELO_Rewards::can_redeem($member, $reward, $bill_amount);
        if (is_wp_error($check)) {
            return $check;
        }

        $args['external_id'] = substr(sanitize_text_field($args['external_id']), 0, 100);
        $id_hash = OBYDRELO_Ledger::id_hash($member['id'], 'redeem', $args['external_id']);
        if ($id_hash) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
            $dupe = $wpdb->get_var(
                $wpdb->prepare('SELECT id FROM %i WHERE id_hash = %s', $table, $id_hash)
            );
            if (null !== $dupe) {
                return new WP_Error('obydrelo_duplicate', __('This redemption was already recorded.', 'obydullah-restaurant-loyalty'));
            }
        }

        $validity_days = (int) $reward['validity_days'];
        if ($validity_days < 1) {
            $validity_days = (int) OBYDRELO_Settings::get('reward_validity_days', 0);
        }
        $expires_at = $validity_days > 0
            ? gmdate('Y-m-d H:i:s', time() + ($validity_days * DAY_IN_SECONDS))
            : null;

        $user = wp_get_current_user();
        $now = current_time('mysql', true);

        // Reserve stock atomically so two staff cannot oversell the last unit.
        $reserved = false;
        if (null !== $reward['usage_limit']) {
            $reserved = self::reserve_stock($reward['id']);
            if (!$reserved) {
                return new WP_Error('obydrelo_out_of_stock', __('This reward just went out of stock.', 'obydullah-restaurant-loyalty'));
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $inserted = $wpdb->insert(
            $table,
            array(
                'redemption_code' => self::generate_code(),
                'member_id'       => $member['id'],
                'reward_id'       => $reward['id'],
                'staff_user_id'   => $user ? (int) $user->ID : 0,
                'source'          => $args['source'],
                'points_spent'    => (float) $reward['points_cost'],
                'stamps_spent'    => (int) $reward['stamps_cost'],
                'visits_spent'    => (int) $reward['visits_cost'],
                'reward_value'    => OBYDRELO_Rewards::redeemable_value($reward, $bill_amount),
                'percent_off'     => (float) $reward['percent_off'],
                'item_name'       => $reward['item_name'],
                'quantity'        => 1,
                'status'          => 'issued',
                'bill_reference'  => substr(sanitize_text_field($args['bill_reference']), 0, 100),
                'external_id'     => $args['external_id'],
                'id_hash'         => $id_hash,
                'note'            => sanitize_textarea_field($args['note']),
                'issued_at'       => $now,
                'expires_at'      => $expires_at,
                'created_at'      => $now,
                'updated_at'      => $now,
            ),
            array('%s', '%d', '%d', '%d', '%s', '%f', '%d', '%d', '%f', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (false === $inserted) {
            if ($reserved) {
                self::release_stock($reward['id']);
            }
            if (false !== stripos((string) $wpdb->last_error, 'duplicate entry')) {
                return new WP_Error('obydrelo_duplicate', __('This redemption was already recorded.', 'obydullah-restaurant-loyalty'));
            }
            return new WP_Error('obydrelo_redemption_failed', __('The redemption could not be saved.', 'obydullah-restaurant-loyalty'));
        }

        $redemption_id = (int) $wpdb->insert_id;

        $ledger = OBYDRELO_Ledger::record(
            array(
                'member_id'      => $member['id'],
                'type'           => 'redeem',
                'source'         => $args['source'],
                'points'         => -1 * (float) $reward['points_cost'],
                'stamps'         => -1 * (int) $reward['stamps_cost'],
                'visits'         => -1 * (int) $reward['visits_cost'],
                'reward_id'      => $reward['id'],
                'redemption_id'  => $redemption_id,
                'reference_type' => 'redemption',
                'reference_id'   => $redemption_id,
                'external_id'    => $args['external_id'],
                'note'           => sprintf(
                    /* translators: %s: reward title. */
                    __('Redeemed: %s', 'obydullah-restaurant-loyalty'),
                    $reward['title']
                ),
            )
        );

        if (is_wp_error($ledger)) {
            // Undo the redemption row and the stock reservation so the ledger
            // stays the single source of truth.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
            $wpdb->delete($table, array('id' => $redemption_id), array('%d'));
            if ($reserved) {
                self::release_stock($reward['id']);
            }
            return $ledger;
        }

        OBYDRELO_Logger::log(
            'reward_redeemed',
            array(
                'member_id'   => $member['id'],
                'object_type' => 'redemption',
                'object_id'   => $redemption_id,
                'message'     => $reward['title'],
                'context'     => array(
                    'reward_id'     => $reward['id'],
                    'points_spent'  => (float) $reward['points_cost'],
                    'bill_reference' => $args['bill_reference'],
                ),
            )
        );

        return $redemption_id;
    }

    /**
     * Marks a redemption as used against a bill.
     */
    public static function mark_used($redemption_id, $bill_reference = '')
    {
        global $wpdb;
        $table = self::table();
        $redemption = self::get($redemption_id);
        if (!$table || !$redemption) {
            return new WP_Error('obydrelo_missing', __('That redemption no longer exists.', 'obydullah-restaurant-loyalty'));
        }
        if ('issued' !== $redemption['status']) {
            return new WP_Error('obydrelo_bad_status', __('Only an issued redemption can be used.', 'obydullah-restaurant-loyalty'));
        }

        $update = array(
            'status'     => 'used',
            'used_at'    => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        );
        $format = array('%s', '%s', '%s');

        if ('' !== $bill_reference) {
            $update['bill_reference'] = substr(sanitize_text_field($bill_reference), 0, 100);
            $format[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $result = $wpdb->update(
            $table,
            $update,
            array('id' => (int) $redemption_id, 'status' => 'issued'),
            $format,
            array('%d', '%s')
        );

        if (false === $result) {
            return new WP_Error('obydrelo_update_failed', __('The redemption could not be updated.', 'obydullah-restaurant-loyalty'));
        }
        if (0 === $wpdb->rows_affected) {
            return new WP_Error('obydrelo_bad_status', __('That redemption was already used or cancelled.', 'obydullah-restaurant-loyalty'));
        }

        OBYDRELO_Logger::log(
            'redemption_used',
            array('member_id' => $redemption['member_id'], 'object_type' => 'redemption', 'object_id' => (int) $redemption_id)
        );
        return true;
    }

    /**
     * Cancels a redemption and returns the cost to the member through a new
     * ledger row. The original rows are never deleted.
     */
    public static function cancel($redemption_id, $reason = '')
    {
        global $wpdb;
        $table = self::table();
        $redemption = self::get($redemption_id);
        if (!$table || !$redemption) {
            return new WP_Error('obydrelo_missing', __('That redemption no longer exists.', 'obydullah-restaurant-loyalty'));
        }
        if (in_array($redemption['status'], array('cancelled', 'expired'), true)) {
            return new WP_Error('obydrelo_bad_status', __('That redemption is already closed.', 'obydullah-restaurant-loyalty'));
        }

        $user = wp_get_current_user();
        $now = current_time('mysql', true);
        // The status guard lives in the WHERE clause as well as in PHP, so two
        // concurrent requests cannot both pass the check above and refund the
        // same redemption twice.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i
                    SET status = %s, cancelled_at = %s, cancelled_by = %d, cancelled_reason = %s, updated_at = %s
                  WHERE id = %d AND status NOT IN ('cancelled', 'expired')",
                $table,
                'cancelled',
                $now,
                $user ? (int) $user->ID : 0,
                substr(sanitize_text_field($reason), 0, 255),
                $now,
                (int) $redemption_id
            )
        );

        if (false === $result || 0 === (int) $wpdb->rows_affected) {
            return new WP_Error('obydrelo_update_failed', __('The redemption could not be cancelled.', 'obydullah-restaurant-loyalty'));
        }

        $reward = OBYDRELO_Rewards::get($redemption['reward_id']);
        if ($reward && null !== $reward['usage_limit']) {
            self::release_stock($reward['id']);
        }

        $refund = OBYDRELO_Ledger::record(
            array(
                'member_id'      => $redemption['member_id'],
                'type'           => 'void',
                'source'         => 'staff',
                'points'         => (float) $redemption['points_spent'],
                'stamps'         => (int) $redemption['stamps_spent'],
                'visits'         => (int) $redemption['visits_spent'],
                'reward_id'      => $redemption['reward_id'],
                'redemption_id'  => (int) $redemption_id,
                'reference_type' => 'redemption',
                'reference_id'   => (int) $redemption_id,
                'note'           => '' !== $reason
                    ? sprintf(
                        /* translators: %s: cancellation reason. */
                        __('Redemption cancelled: %s', 'obydullah-restaurant-loyalty'),
                        $reason
                    )
                    : __('Redemption cancelled and balance returned.', 'obydullah-restaurant-loyalty'),
            )
        );

        OBYDRELO_Logger::log(
            'redemption_cancelled',
            array(
                'member_id'   => $redemption['member_id'],
                'object_type' => 'redemption',
                'object_id'   => (int) $redemption_id,
                'severity'    => 'warning',
                'context'     => array('reason' => $reason, 'refunded' => !is_wp_error($refund)),
            )
        );

        return true;
    }

    /**
     * Cron: closes issued redemptions that were never used before their expiry
     * and returns the cost, because issue() already debited it. Expiring is a
     * reversal of the debit, not a second charge.
     */
    public static function expire_overdue()
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE status = \'issued\' AND expires_at IS NOT NULL AND expires_at < %s',
                $table,
                current_time('mysql', true)
            ),
            ARRAY_A
        );
        if (!$rows) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
            $updated = $wpdb->update(
                $table,
                array('status' => 'expired', 'updated_at' => current_time('mysql', true)),
                array('id' => (int) $row['id'], 'status' => 'issued'),
                array('%s', '%s'),
                array('%d', '%s')
            );
            if (false === $updated || 0 === $wpdb->rows_affected) {
                continue;
            }

            $reward = OBYDRELO_Rewards::get($row['reward_id']);
            if ($reward && null !== $reward['usage_limit']) {
                self::release_stock($reward['id']);
            }

            OBYDRELO_Ledger::record(
                array(
                    'member_id'      => (int) $row['member_id'],
                    'type'           => 'expire',
                    'source'         => 'system',
                    'points'         => (float) $row['points_spent'],
                    'stamps'         => (int) $row['stamps_spent'],
                    'visits'         => (int) $row['visits_spent'],
                    'reward_id'      => $row['reward_id'],
                    'redemption_id'  => (int) $row['id'],
                    'reference_type' => 'redemption',
                    'reference_id'   => (int) $row['id'],
                    'note'           => __('Reward expired unused, balance returned.', 'obydullah-restaurant-loyalty'),
                )
            );

            OBYDRELO_Logger::log(
                'redemption_expired',
                array(
                    'member_id'   => (int) $row['member_id'],
                    'object_type' => 'redemption',
                    'object_id'   => (int) $row['id'],
                    'severity'    => 'warning',
                )
            );
            $count++;
        }

        return $count;
    }

    public static function list_redemptions($args = array())
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return array();
        }
        $args = wp_parse_args($args, array('member_id' => 0, 'status' => '', 'limit' => 50));

        // Both filters are optional, so each is an always-present predicate
        // that goes neutral when unused: '0 = 0' and "'' = ''" hold for every
        // row. One literal query shape keeps every value bound.
        $member_id = (int) $args['member_id'];
        $status    = array_key_exists( $args['status'], self::statuses() ) ? $args['status'] : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i
                  WHERE (%d = 0 OR member_id = %d) AND (%s = \'\' OR status = %s)
                  ORDER BY issued_at DESC LIMIT %d',
                $table,
                $member_id,
                $member_id,
                $status,
                $status,
                max(1, min(200, (int) $args['limit']))
            )
        );
    }

    public static function statuses()
    {
        return array(
            'issued'    => __('Issued', 'obydullah-restaurant-loyalty'),
            'used'      => __('Used', 'obydullah-restaurant-loyalty'),
            'cancelled' => __('Cancelled', 'obydullah-restaurant-loyalty'),
            'expired'   => __('Expired', 'obydullah-restaurant-loyalty'),
        );
    }

    /**
     * Atomically increments used_count only while below usage_limit.
     */
    private static function reserve_stock($reward_id)
    {
        global $wpdb;
        $table = OBYDRELO_Rewards::table();
        if (!$table) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET used_count = used_count + 1, updated_at = %s
                 WHERE id = %d AND usage_limit IS NOT NULL AND used_count < usage_limit",
                $table,
                current_time('mysql', true),
                (int) $reward_id
            )
        );
        return $updated > 0;
    }

    private static function release_stock($reward_id)
    {
        global $wpdb;
        $table = OBYDRELO_Rewards::table();
        if (!$table) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The used_count comparison is the oversell guard and the expiry sweep compares the current time against uncached state. A cached count would let a limited reward be redeemed past its limit.
        $updated = $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET used_count = GREATEST(used_count - 1, 0), updated_at = %s WHERE id = %d',
                $table,
                current_time('mysql', true),
                (int) $reward_id
            )
        );
        return false !== $updated;
    }

    private static function generate_code()
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        return 'RDM-' . strtoupper(substr(str_shuffle($alphabet), 0, 4) . substr(str_shuffle($alphabet), 0, 4));
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
        $row['member_id'] = (int) $row['member_id'];
        $row['reward_id'] = (int) $row['reward_id'];
        $row['points_spent'] = (float) $row['points_spent'];
        $row['stamps_spent'] = (int) $row['stamps_spent'];
        $row['visits_spent'] = (int) $row['visits_spent'];
        $row['reward_value'] = (float) $row['reward_value'];
        $row['percent_off'] = (float) $row['percent_off'];
        return $row;
    }
}
