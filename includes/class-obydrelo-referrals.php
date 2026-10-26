<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Referrals
{
    const STATUSES = 'pending,qualified,rewarded,rejected';

    public static function table()
    {
        return OBYDRELO_Schema::table('referrals');
    }

    public static function statuses()
    {
        return array(
            'pending'   => __('Pending', 'obydullah-restaurant-loyalty'),
            'qualified' => __('Qualified', 'obydullah-restaurant-loyalty'),
            'rewarded'  => __('Rewarded', 'obydullah-restaurant-loyalty'),
            'rejected'  => __('Rejected', 'obydullah-restaurant-loyalty'),
        );
    }

    /**
     * Attaches a pending referral row when a new member supplies a valid
     * referrer code. Self-referral and cycles are rejected.
     */
    public static function link($member_id, $referral_code)
    {
        global $wpdb;
        $table = self::table();
        $referral_code = strtoupper(trim((string) $referral_code));
        if (!$table || '' === $referral_code) {
            return false;
        }

        $member = OBYDRELO_Members::get($member_id);
        if (!$member || !OBYDRELO_Settings::get('referral_enabled')) {
            return false;
        }

        $referrer = OBYDRELO_Members::get_by('referral_code', $referral_code);
        if (!$referrer) {
            return false;
        }
        if ((int) $referrer['id'] === (int) $member_id) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        $exists = $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM %i WHERE referred_member_id = %d', $table, $member_id)
        );
        if (null !== $exists) {
            return false;
        }

        $now = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        $result = $wpdb->insert(
            $table,
            array(
                'referrer_member_id'     => (int) $referrer['id'],
                'referred_member_id'     => (int) $member_id,
                'referral_code'          => $referral_code,
                'status'                 => 'pending',
                'qualification'          => (string) OBYDRELO_Settings::get('referral_qualification', 'first_purchase'),
                'reward_referrer_points' => (float) OBYDRELO_Settings::get('referral_points_referrer', 0),
                'reward_referred_points' => (float) OBYDRELO_Settings::get('referral_points_referred', 0),
                'created_at'             => $now,
                'updated_at'             => $now,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s')
        );

        if (false === $result) {
            return false;
        }

        // Capture the id before any further query resets $wpdb->insert_id.
        $referral_id = (int) $wpdb->insert_id;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        $wpdb->update(
            OBYDRELO_Members::table(),
            array('referred_by_id' => (int) $referrer['id'], 'updated_at' => $now),
            array('id' => (int) $member_id),
            array('%d', '%s'),
            array('%d')
        );

        OBYDRELO_Logger::log(
            'referral_linked',
            array(
                'member_id'   => (int) $member_id,
                'object_type' => 'referral',
                'object_id'   => $referral_id,
                'context'     => array('referrer_member_id' => (int) $referrer['id'], 'code' => $referral_code),
            )
        );

        if ('signup' === OBYDRELO_Settings::get('referral_qualification', 'first_purchase')) {
            self::maybe_qualify($member_id);
        }

        return true;
    }

    /**
     * Qualifies and pays a pending referral once the referred member's first
     * qualifying transaction is recorded. Called after every ledger append.
     */
    public static function maybe_qualify($member_id, $transaction_id = 0)
    {
        global $wpdb;
        $table = self::table();
        if (!$table || !OBYDRELO_Settings::get('referral_enabled')) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE referred_member_id = %d AND status = \'pending\'',
                $table,
                (int) $member_id
            ),
            ARRAY_A
        );
        if (!$row) {
            return false;
        }

        if ('first_purchase' === $row['qualification']) {
            if (!$transaction_id) {
                return false;
            }
            $tx_table = OBYDRELO_Ledger::table();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
            $qualifying = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE member_id = %d AND amount > 0',
                    $tx_table,
                    (int) $member_id
                )
            );
            if ((int) $qualifying < 1) {
                return false;
            }
        }

        $now = current_time('mysql', true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        $wpdb->update(
            $table,
            array(
                'status'         => 'rewarded',
                'transaction_id' => $transaction_id ? (int) $transaction_id : null,
                'qualified_at'   => $now,
                'rewarded_at'    => $now,
                'updated_at'     => $now,
            ),
            array('id' => (int) $row['id'], 'status' => 'pending'),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d', '%s')
        );
        if (0 === $wpdb->rows_affected) {
            return false;
        }

        $referrer_points = (float) $row['reward_referrer_points'];
        $referred_points = (float) $row['reward_referred_points'];

        if ($referrer_points > 0) {
            OBYDRELO_Ledger::record(
                array(
                    'member_id' => (int) $row['referrer_member_id'],
                    'type'      => 'referral',
                    'source'    => 'system',
                    'points'    => $referrer_points,
                    'note'      => __('Referral reward', 'obydullah-restaurant-loyalty'),
                    'reference_type' => 'referral',
                    'reference_id'   => (int) $row['id'],
                )
            );
        }

        if ($referred_points > 0) {
            OBYDRELO_Ledger::record(
                array(
                    'member_id' => (int) $row['referred_member_id'],
                    'type'      => 'referral',
                    'source'    => 'system',
                    'points'    => $referred_points,
                    'note'      => __('Referral bonus', 'obydullah-restaurant-loyalty'),
                    'reference_type' => 'referral',
                    'reference_id'   => (int) $row['id'],
                )
            );
        }

        OBYDRELO_Logger::log(
            'referral_rewarded',
            array(
                'member_id'   => (int) $member_id,
                'object_type' => 'referral',
                'object_id'   => (int) $row['id'],
                'context'     => array(
                    'referrer_member_id' => (int) $row['referrer_member_id'],
                    'points'             => array($referrer_points, $referred_points),
                ),
            )
        );

        return true;
    }

    public static function summary($member_id)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return array('invited' => 0, 'rewarded' => 0, 'earned_points' => 0.0);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS invited,
                    SUM(CASE WHEN status = 'rewarded' THEN 1 ELSE 0 END) AS rewarded,
                    COALESCE(SUM(CASE WHEN status = 'rewarded' THEN reward_referrer_points ELSE 0 END), 0) AS earned_points
                 FROM %i WHERE referrer_member_id = %d",
                $table,
                (int) $member_id
            ),
            ARRAY_A
        );
        return array(
            'invited'       => (int) ($row['invited'] ?? 0),
            'rewarded'      => (int) ($row['rewarded'] ?? 0),
            'earned_points' => (float) ($row['earned_points'] ?? 0),
        );
    }

    public static function list_referrals($args = array())
    {
        global $wpdb;
        $table = self::table();
        $members = OBYDRELO_Members::table();
        if (!$table || !$members) {
            return array();
        }
        $args = wp_parse_args($args, array('status' => '', 'limit' => 50));

        // The status filter is optional, so it is an always-present predicate
        // that goes neutral when unused: "'' = ''" holds for every row. One
        // literal query shape keeps every value bound.
        $status = array_key_exists( $args['status'], self::statuses() ) ? $args['status'] : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Qualification counts and the link uniqueness checks. A cached count would qualify a referral that never reached the threshold, and a cached link check would accept a duplicate.
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT r.*, rm.member_code AS referrer_code, rm.first_name AS referrer_first, rm.last_name AS referrer_last,
                        dm.member_code AS referred_code, dm.first_name AS referred_first, dm.last_name AS referred_last
                   FROM %i r
                   LEFT JOIN %i rm ON rm.id = r.referrer_member_id
                   LEFT JOIN %i dm ON dm.id = r.referred_member_id
                  WHERE (%s = \'\' OR r.status = %s)
                  ORDER BY r.created_at DESC LIMIT %d',
                $table,
                $members,
                $members,
                $status,
                $status,
                max(1, min(200, (int) $args['limit']))
            )
        );
    }
}
