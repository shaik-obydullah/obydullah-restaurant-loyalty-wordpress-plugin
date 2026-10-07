<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Birthday
{
    /**
     * Grants the birthday reward once per calendar year to every member whose
     * birthday falls inside the lead window. Safe to run daily.
     */
    public static function grant_due()
    {
        if (!OBRL_Settings::get('birthday_reward_enabled')) {
            return 0;
        }

        global $wpdb;
        $table = OBRL_Members::table();
        if (!$table) {
            return 0;
        }

        $year = (int) gmdate('Y');
        $lead = max(0, (int) OBRL_Settings::get('birthday_reward_lead_days', 0));
        $points = (float) OBRL_Settings::get('birthday_reward_points', 0);
        $stamps = (int) OBRL_Settings::get('birthday_reward_stamps', 0);
        $visits = (int) OBRL_Settings::get('birthday_reward_visits', 0);

        if (0.0 === $points && 0 === $stamps && 0 === $visits) {
            return 0;
        }

        $candidates = self::due_member_ids($year, $lead);
        $granted = 0;

        foreach ($candidates as $member_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- due_member_ids() is the idempotency guard: birthday_reward_year is compared on every read, so a cached result would grant a second birthday reward for the same year.
            $claimed = $wpdb->query(
                $wpdb->prepare(
                    'UPDATE %i SET birthday_reward_year = %d, updated_at = %s
                     WHERE id = %d AND birthday_reward_year != %d',
                    $table,
                    $year,
                    current_time('mysql', true),
                    $member_id,
                    $year
                )
            );
            if (false === $claimed || 0 === $claimed) {
                continue;
            }

            $result = OBRL_Ledger::record(
                array(
                    'member_id' => $member_id,
                    'type'      => 'birthday',
                    'source'    => 'system',
                    'points'    => $points,
                    'stamps'    => $stamps,
                    'visits'    => $visits,
                    'note'      => __('Birthday reward', 'obydullah-restaurant-loyalty'),
                )
            );
            if (!is_wp_error($result)) {
                $granted++;
            }
        }

        return $granted;
    }

    /**
     * Finds member ids whose birthday falls on the target day, or within the
     * lead window when one is configured.
     */
    public static function due_member_ids($year, $lead_days = 0)
    {
        global $wpdb;
        $table = OBRL_Members::table();
        if (!$table) {
            return array();
        }

        // The birthday itself plus the configured lead window. Only the MM-DD
        // part is matched, so a window that crosses new year has to be read as
        // two spans rather than one.
        list($start, $end, $wraps) = self::day_window($lead_days);

        /*
         * The window is expressed as a fixed shape of bound parameters rather
         * than a generated IN () list. Only one of the two branches can ever
         * be true, chosen by $wraps, so the query text never varies and the
         * whole statement stays a literal for prepare() to fill in. birthday
         * is a DATE column, so SUBSTRING() yields a zero padded MM-DD and
         * string comparison orders the same way the calendar does.
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- due_member_ids() is the idempotency guard: birthday_reward_year is compared on every read, so a cached result would grant a second birthday reward for the same year.
        return array_map('intval', (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM %i
                 WHERE deleted_at IS NULL
                   AND status = 'active'
                   AND birthday IS NOT NULL
                   AND birthday_reward_year != %d
                   AND (
                        (%d = 0 AND SUBSTRING(birthday, 6) BETWEEN %s AND %s)
                     OR (%d = 1 AND (SUBSTRING(birthday, 6) >= %s OR SUBSTRING(birthday, 6) <= %s))
                   )",
                $table,
                $year,
                $wraps,
                $start,
                $end,
                $wraps,
                $start,
                $end
            )
        ));
    }

    /**
     * Upcoming birthdays for the next $days days, for the admin widget.
     */
    public static function upcoming($days = 30, $limit = 10)
    {
        global $wpdb;
        $table = OBRL_Members::table();
        if (!$table) {
            return array();
        }
        // Capped to match day_window(): 365 is the one span that would give a
        // full year of notice with the same MM-DD at both ends.
        $days = max(1, min(364, (int) $days));
        $limit = max(1, min(100, (int) $limit));

        list($start, $end, $wraps) = self::day_window($days);

        // See due_member_ids(): same fixed shape, so no placeholder run is
        // generated and the statement text never changes.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- due_member_ids() is the idempotency guard: birthday_reward_year is compared on every read, so a cached result would grant a second birthday reward for the same year.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i
                 WHERE deleted_at IS NULL AND status = 'active' AND birthday IS NOT NULL
                   AND (
                        (%d = 0 AND SUBSTRING(birthday, 6) BETWEEN %s AND %s)
                     OR (%d = 1 AND (SUBSTRING(birthday, 6) >= %s OR SUBSTRING(birthday, 6) <= %s))
                   )
                 ORDER BY SUBSTRING(birthday, 6) ASC LIMIT %d",
                $table,
                $wraps,
                $start,
                $end,
                $wraps,
                $start,
                $end,
                $limit
            )
        );

        $today = gmdate('m-d');
        foreach ((array) $rows as $row) {
            $row->is_today = substr($row->birthday, 5) === $today;
        }
        return (array) $rows;
    }

    /**
     * Describes the inclusive MM-DD window running from today for $lead_days
     * days.
     *
     * Returns the first and last MM-DD of the window plus a flag saying
     * whether the window runs past 31 December. Both callers need the same
     * three values, and deriving them in one place keeps the two queries
     * answering exactly the same set of members.
     *
     * The window is capped at 364 days, and the cap is load bearing rather
     * than cosmetic. The lead time is a free-form integer in the settings
     * screen, and a span of 365 days lands back on the same MM-DD it started
     * from. That would make start equal to end, which reads as "no wrap" and
     * would collapse BETWEEN into a single day instead of a full year, so a
     * staff member asking for a year of notice would silently get only today.
     * Any span shorter than a year has distinct ends, which is what the two
     * branches below rely on.
     *
     * @param int $lead_days Days after today to include.
     * @return array{0:string,1:string,2:int} Start, end, and wrap flag.
     */
    private static function day_window($lead_days)
    {
        $lead_days = max(0, min(364, (int) $lead_days));

        $start = gmdate('m-d');
        $end = gmdate('m-d', strtotime('+' . $lead_days . ' days'));

        // MM-DD is zero padded, so the string order of two such values is the
        // calendar order, and an end that sorts before the start can only mean
        // the window crossed new year.
        return array($start, $end, $end < $start ? 1 : 0);
    }
}
