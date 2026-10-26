<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Append-only transaction ledger.
 *
 * Every balance change in the program must produce exactly one row in
 * member_transactions. Balances are never edited directly: the cached columns
 * on the members row are refreshed from SUM() over the ledger by recalculate_member(),
 * which can rebuild any member at any time.
 */
final class OBYDRELO_Ledger
{
    const TYPES = 'purchase,visit,earn,redeem,adjustment,birthday,welcome,referral,expire,void';

    public static function types()
    {
        return array(
            'purchase'   => __('Purchase', 'obydullah-restaurant-loyalty'),
            'visit'      => __('Visit', 'obydullah-restaurant-loyalty'),
            'earn'       => __('Points / stamps awarded', 'obydullah-restaurant-loyalty'),
            'redeem'     => __('Reward redeemed', 'obydullah-restaurant-loyalty'),
            'adjustment' => __('Manual adjustment', 'obydullah-restaurant-loyalty'),
            'birthday'   => __('Birthday reward', 'obydullah-restaurant-loyalty'),
            'welcome'    => __('Welcome reward', 'obydullah-restaurant-loyalty'),
            'referral'   => __('Referral reward', 'obydullah-restaurant-loyalty'),
            'expire'     => __('Expired', 'obydullah-restaurant-loyalty'),
            'void'       => __('Void', 'obydullah-restaurant-loyalty'),
        );
    }

    public static function table()
    {
        return OBYDRELO_Schema::table('member_transactions');
    }

    public static function mode()
    {
        return (string) OBYDRELO_Settings::get('program_mode', 'points');
    }

    /**
     * Appends one ledger row and refreshes the member's cached balance.
     *
     * @param array $args {
     *     @type int    $member_id   Required.
     *     @type string $type        One of self::types().
     *     @type string $source      staff|portal|system.
     *     @type float  $points      Signed. Positive credits, negative debits.
     *     @type int    $stamps      Signed.
     *     @type int    $visits      Signed.
     *     @type float  $amount      Purchase amount in the program currency.
     *     @type string $external_id Caller supplied de-duplication key.
     *     @type string $note        Reason shown in history.
     * }
     * @return int|WP_Error Inserted transaction id.
     */
    public static function record($args)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return new WP_Error('obydrelo_no_schema', __('The loyalty database is not installed.', 'obydullah-restaurant-loyalty'));
        }

        $args = wp_parse_args(
            $args,
            array(
                'member_id'      => 0,
                'type'           => 'adjustment',
                'source'         => 'staff',
                'points'         => 0,
                'stamps'         => 0,
                'visits'         => 0,
                'amount'         => 0,
                'currency'       => (string) OBYDRELO_Settings::get('currency_code', ''),
                'reward_id'      => null,
                'redemption_id'  => null,
                'reference_type' => '',
                'reference_id'   => null,
                'reversal_of_id' => null,
                'external_id'    => '',
                'note'           => '',
            )
        );

        $member = OBYDRELO_Members::get($args['member_id']);
        if (!$member) {
            return new WP_Error('obydrelo_member_missing', __('That member no longer exists.', 'obydullah-restaurant-loyalty'));
        }
        if (!array_key_exists($args['type'], self::types())) {
            return new WP_Error('obydrelo_bad_type', __('Unknown transaction type.', 'obydullah-restaurant-loyalty'));
        }

        $args['points'] = round((float) $args['points'], 2);
        $args['stamps'] = (int) $args['stamps'];
        $args['visits'] = (int) $args['visits'];
        $args['amount'] = round((float) $args['amount'], 2);

        // A purchase or a visit carries its own reward values, so a caller that
        // only knows the bill total still gets the right earn for the mode.
        $is_visit_event = in_array($args['type'], array('purchase', 'visit'), true);
        if ($is_visit_event && 0.0 === $args['points'] && 0 === $args['stamps'] && 0 === $args['visits']) {
            $derived = self::values_for_purchase($args['amount']);
            $args['points'] = $derived['points'];
            $args['stamps'] = $derived['stamps'];
            $args['visits'] = $derived['visits'];
        }

        if (0.0 === $args['points'] && 0 === $args['stamps'] && 0 === $args['visits'] && 0.0 === $args['amount']) {
            return new WP_Error('obydrelo_empty_transaction', __('Enter a points, stamp, visit, or amount value.', 'obydullah-restaurant-loyalty'));
        }

        // Refuse to overdraw a member unless the program allows a negative
        // balance, so a debit can never silently create debt.
        if ($args['points'] < 0 && !OBYDRELO_Settings::get('allow_negative_points')) {
            $projected = round((float) $member['points_balance'] + $args['points'], 2);
            if ($projected < 0) {
                return new WP_Error(
                    'obydrelo_insufficient_balance',
                    sprintf(
                        /* translators: %s: formatted points shortfall. */
                        __('Not enough points. Still needed: %s.', 'obydullah-restaurant-loyalty'),
                        OBYDRELO_Format::points(abs($projected))
                    )
                );
            }
        }

        $args['external_id'] = substr(sanitize_text_field($args['external_id']), 0, 100);
        $id_hash = self::id_hash($member['id'], $args['type'], $args['external_id']);

        // Check the de-duplication key up front so a repeat import reports a
        // clean error instead of tripping the unique index.
        if ($id_hash) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
            $existing = $wpdb->get_var(
                $wpdb->prepare('SELECT id FROM %i WHERE id_hash = %s', $table, $id_hash)
            );
            if (null !== $existing) {
                return new WP_Error('obydrelo_duplicate', __('This transaction was already recorded.', 'obydullah-restaurant-loyalty'));
            }
        }

        $user = wp_get_current_user();
        $now = current_time('mysql', true);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $result = $wpdb->insert(
            $table,
            array(
                'member_id'      => $member['id'],
                'type'           => $args['type'],
                'source'         => $args['source'],
                'points'         => $args['points'],
                'stamps'         => $args['stamps'],
                'visits'         => $args['visits'],
                'amount'         => $args['amount'],
                'currency'       => $args['currency'],
                'reward_id'      => $args['reward_id'] ? (int) $args['reward_id'] : null,
                'redemption_id'  => $args['redemption_id'] ? (int) $args['redemption_id'] : null,
                'reference_type' => substr($args['reference_type'], 0, 30),
                'reference_id'   => $args['reference_id'] ? (int) $args['reference_id'] : null,
                'reversal_of_id' => $args['reversal_of_id'] ? (int) $args['reversal_of_id'] : null,
                'external_id'    => $args['external_id'],
                'id_hash'        => $id_hash,
                'staff_user_id'  => $user ? (int) $user->ID : 0,
                'staff_login'    => $user && isset($user->user_login) ? substr($user->user_login, 0, 60) : '',
                'note'           => sanitize_textarea_field($args['note']),
                'created_at'     => $now,
            ),
            array('%d', '%s', '%s', '%f', '%d', '%d', '%f', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if (false === $result) {
            if (self::is_duplicate($wpdb->last_error)) {
                return new WP_Error('obydrelo_duplicate', __('This transaction was already recorded.', 'obydullah-restaurant-loyalty'));
            }
            return new WP_Error('obydrelo_transaction_failed', __('The transaction could not be saved.', 'obydullah-restaurant-loyalty'));
        }

        $transaction_id = (int) $wpdb->insert_id;
        self::refresh_member($member['id'], $transaction_id, $args['type']);

        OBYDRELO_Logger::log(
            'transaction_' . $args['type'],
            array(
                'member_id'   => $member['id'],
                'object_type' => 'transaction',
                'object_id'   => $transaction_id,
                'message'     => sprintf('%s: %s pts, %s stamps, %s visits', $args['type'], $args['points'], $args['stamps'], $args['visits']),
                'context'     => array(
                    'points'      => $args['points'],
                    'stamps'      => $args['stamps'],
                    'visits'      => $args['visits'],
                    'amount'      => $args['amount'],
                    'external_id' => $args['external_id'],
                ),
            )
        );

        OBYDRELO_Referrals::maybe_qualify($member['id'], $transaction_id);

        return $transaction_id;
    }

    /**
     * Deterministic de-duplication key. A blank external_id yields NULL so the
     * unique index ignores the row and manual entries are never blocked.
     */
    public static function id_hash($member_id, $type, $external_id)
    {
        $external_id = trim((string) $external_id);
        if ('' === $external_id) {
            return null;
        }
        return hash('sha256', $member_id . '|' . $type . '|' . $external_id);
    }

    private static function is_duplicate($error)
    {
        return is_string($error) && false !== stripos($error, 'duplicate entry');
    }

    /**
     * Rebuilds the cached balance columns on the member row from the ledger and
     * stamps the post-transaction snapshot onto the newest row.
     */
    public static function recalculate_member($member_id)
    {
        global $wpdb;
        $members = OBYDRELO_Members::table();
        $table = self::table();
        $member_id = (int) $member_id;
        if (!$members || !$table || $member_id < 1) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(points), 0) AS points,
                    COALESCE(SUM(stamps), 0) AS stamps,
                    COALESCE(SUM(visits), 0) AS visits,
                    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS earned_points,
                    COALESCE(SUM(amount), 0) AS total_spend
                 FROM %i WHERE member_id = %d",
                $table,
                $member_id
            ),
            ARRAY_A
        );
        if (!$totals) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $wpdb->update(
            $members,
            array(
                'points_balance'  => round((float) $totals['points'], 2),
                'stamps_count'    => (int) $totals['stamps'],
                'visits_count'    => (int) $totals['visits'],
                'lifetime_points' => round((float) $totals['earned_points'], 2),
                'total_spend'     => round((float) $totals['total_spend'], 2),
                'updated_at'      => current_time('mysql', true),
            ),
            array('id' => $member_id),
            array('%f', '%d', '%d', '%f', '%f', '%s'),
            array('%d')
        );

        return true;
    }

    /**
     * Applies the append and then refreshes balances. Used by record().
     */
    private static function refresh_member($member_id, $transaction_id, $type = '')
    {
        global $wpdb;
        $table = self::table();
        $members = OBYDRELO_Members::table();

        $before = OBYDRELO_Members::get($member_id);
        if (!$before) {
            return;
        }

        self::recalculate_member($member_id);
        $after = OBYDRELO_Members::get($member_id);
        if (!$after) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $wpdb->update(
            $table,
            array(
                'points_after' => round((float) $after['points_balance'], 2),
                'stamps_after' => (int) $after['stamps_count'],
                'visits_after' => (int) $after['visits_count'],
            ),
            array('id' => (int) $transaction_id),
            array('%f', '%d', '%d'),
            array('%d')
        );

        $now = current_time('mysql', true);
        $member_update = array('last_transaction_at' => $now);
        $member_formats = array('%s');

        // A purchase is a visit whatever the earn mode, so stamp the last visit
        // on any real spend rather than only when the visit counter moves.
        if ($after['visits_count'] > $before['visits_count']) {
            $member_update['last_visit_at'] = $now;
        } elseif ('purchase' === $type) {
            $member_update['last_visit_at'] = $now;
        }
        if (count($member_update) > 1) {
            $member_formats[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $wpdb->update(
            $members,
            $member_update,
            array('id' => (int) $member_id),
            $member_formats,
            array('%d')
        );
    }

    public static function history($args = array())
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return array('items' => array(), 'total' => 0, 'pages' => 0, 'page' => 1);
        }

        $args = wp_parse_args(
            $args,
            array(
                'member_id' => 0,
                'type'      => '',
                'page'      => 1,
                'per_page'  => 20,
            )
        );

        // Both filters are optional, so each is an always-present predicate that
        // goes neutral when unused: '0 = 0' and "'' = ''" hold for every row,
        // while a real filter makes that branch false and the filter itself
        // decides. That keeps one literal query shape, so every value arrives
        // as a bound placeholder.
        $member_id = (int) $args['member_id'];
        $type      = array_key_exists( $args['type'], self::types() ) ? $args['type'] : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE (%d = 0 OR member_id = %d) AND (%s = \'\' OR type = %s)',
                $table,
                $member_id,
                $member_id,
                $type,
                $type
            )
        );

        $per_page = max(1, min(100, (int) $args['per_page']));
        $pages = (int) ceil($total / $per_page);
        $page = max(1, min($pages ? $pages : 1, (int) $args['page']));

        $offset = ($page - 1) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Points, stamps, and visits are money. This is the write path of the ledger: the duplicate check that stops a replayed transaction, the aggregate it updates, and the history it returns. A cached row would let a second redemption spend the same balance twice.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i
                  WHERE (%d = 0 OR member_id = %d) AND (%s = \'\' OR type = %s)
                  ORDER BY created_at DESC, id DESC
                  LIMIT %d OFFSET %d',
                $table,
                $member_id,
                $member_id,
                $type,
                $type,
                $per_page,
                $offset
            )
        );

        return array(
            'items' => (array) $rows,
            'total' => $total,
            'pages' => $pages,
            'page'  => $page,
        );
    }

    /**
     * Rewards an amount using the configured earn rate for the active mode.
     * Returns the signed ledger values for the current program mode.
     */
    public static function values_for_purchase($amount)
    {
        $amount = max(0.0, (float) $amount);
        $mode = self::mode();
        $rate = (float) OBYDRELO_Settings::get('points_per_currency', 1);

        $values = array('points' => 0.0, 'stamps' => 0, 'visits' => 1, 'amount' => $amount);
        if ('points' === $mode) {
            $values['points'] = round($amount * $rate, 2);
            $values['visits'] = 0;
        } elseif ('stamps' === $mode) {
            $values['stamps'] = 1;
            $values['visits'] = 0;
        }
        return $values;
    }
}
