<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Reports
{
    public static function since($days)
    {
        $days = max(1, min(3650, (int) $days));
        return gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
    }

    public static function build($days = 30)
    {
        global $wpdb;
        $days      = in_array((string) $days, array('7', '30', '90', '365'), true) ? (string) $days : '30';
        $since     = self::since($days);
        $tx        = OBYDRELO_Schema::table('member_transactions');
        $members   = OBYDRELO_Members::table();
        $rewards   = OBYDRELO_Schema::table('rewards');
        $redeem    = OBYDRELO_Schema::table('redemptions');

        $empty = array(
            'range'           => $days,
            'revenue'         => 0.0,
            'transactions'    => 0,
            'new_members'     => 0,
            'points_earned'   => 0.0,
            'points_redeemed' => 0.0,
            'redemptions'     => 0,
            'top_members'     => array(),
            'reward_usage'    => array(),
            'daily'           => array(),
            'max_daily'       => 0,
        );

        if (!$tx || !$members) {
            return $empty;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Time-windowed aggregates over the ledger. Every transaction moves them, so an entry would go stale the moment staff recorded one, and these are read on demand from a report screen.
        $summary = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS transactions,
                    COALESCE(SUM(amount), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS points_earned,
                    COALESCE(SUM(CASE WHEN points < 0 THEN points ELSE 0 END), 0) AS points_redeemed
                 FROM %i WHERE created_at >= %s",
                $tx,
                $since
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Time-windowed aggregates over the ledger. Every transaction moves them, so an entry would go stale the moment staff recorded one, and these are read on demand from a report screen.
        $new_members = (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at >= %s', $members, $since)
        );

        $redemptions = 0;
        if ($redeem) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Time-windowed aggregates over the ledger. Every transaction moves them, so an entry would go stale the moment staff recorded one, and these are read on demand from a report screen.
            $redemptions = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE issued_at >= %s AND status IN (\'issued\',\'used\')',
                    $redeem,
                    $since
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Time-windowed aggregates over the ledger. Every transaction moves them, so an entry would go stale the moment staff recorded one, and these are read on demand from a report screen.
        $top_members = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    m.id, m.member_code, m.first_name, m.last_name, m.visits_count,
                    COALESCE(SUM(t.amount), 0) AS total_spend
                 FROM %i m
                 INNER JOIN %i t ON t.member_id = m.id AND t.created_at >= %s
                 WHERE m.deleted_at IS NULL
                 GROUP BY m.id, m.member_code, m.first_name, m.last_name, m.visits_count
                 HAVING total_spend > 0
                 ORDER BY total_spend DESC
                 LIMIT 10",
                $members,
                $tx,
                $since
            )
        );
        foreach ((array) $top_members as $row) {
            $row->name = trim($row->first_name . ' ' . $row->last_name);
        }

        $reward_usage = array();
        if ($redeem && $rewards) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Time-windowed aggregates over the ledger. Every transaction moves them, so an entry would go stale the moment staff recorded one, and these are read on demand from a report screen.
            $reward_usage = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT rw.title, COUNT(*) AS count, COALESCE(SUM(rd.reward_value), 0) AS value
                     FROM %i rd
                     INNER JOIN %i rw ON rw.id = rd.reward_id
                     WHERE rd.issued_at >= %s AND rd.status IN ('issued','used')
                     GROUP BY rd.reward_id, rw.title
                     ORDER BY count DESC",
                    $redeem,
                    $rewards,
                    $since
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Time-windowed aggregates over the ledger. Every transaction moves them, so an entry would go stale the moment staff recorded one, and these are read on demand from a report screen.
        $daily = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS day, COUNT(*) AS count
                 FROM %i WHERE created_at >= %s
                 GROUP BY DATE(created_at) ORDER BY day ASC",
                $tx,
                $since
            )
        );

        $max_daily = 0;
        foreach ((array) $daily as $row) {
            $max_daily = max($max_daily, (int) $row->count);
        }

        return array(
            'range'           => $days,
            'since'           => $since,
            'revenue'         => (float) ($summary['revenue'] ?? 0),
            'transactions'    => (int) ($summary['transactions'] ?? 0),
            'new_members'     => $new_members,
            'points_earned'   => (float) ($summary['points_earned'] ?? 0),
            'points_redeemed' => (float) ($summary['points_redeemed'] ?? 0),
            'redemptions'     => $redemptions,
            'top_members'     => (array) $top_members,
            'reward_usage'    => (array) $reward_usage,
            'daily'           => (array) $daily,
            'max_daily'       => $max_daily,
        );
    }
}
