<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Welcome
{
    /**
     * Grants the configured welcome reward once per member. Runs after
     * enrolment; failures never block member creation.
     */
    public static function maybe_grant($member)
    {
        if (!$member || empty($member['id'])) {
            return false;
        }
        if (!OBRL_Settings::get('welcome_reward_enabled')) {
            return false;
        }
        if (!empty($member['welcome_reward_claimed'])) {
            return false;
        }

        global $wpdb;
        $table = OBRL_Members::table();
        if (!$table) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The welcome grant is guarded by a ledger lookup that has to be current, or a second visit would grant it twice.
        $claimed = $wpdb->update(
            $table,
            array('welcome_reward_claimed' => 1, 'updated_at' => current_time('mysql', true)),
            array('id' => (int) $member['id'], 'welcome_reward_claimed' => 0),
            array('%d', '%s'),
            array('%d')
        );
        if (false === $claimed || 0 === $claimed) {
            return false;
        }

        $points = (float) OBRL_Settings::get('welcome_reward_points', 0);
        $stamps = (int) OBRL_Settings::get('welcome_reward_stamps', 0);
        $visits = (int) OBRL_Settings::get('welcome_reward_visits', 0);

        if (0.0 === $points && 0 === $stamps && 0 === $visits) {
            return false;
        }

        $result = OBRL_Ledger::record(
            array(
                'member_id' => $member['id'],
                'type'      => 'welcome',
                'source'    => 'system',
                'points'    => $points,
                'stamps'    => $stamps,
                'visits'    => $visits,
                'note'      => __('Welcome reward', 'obydullah-restaurant-loyalty'),
            )
        );

        return !is_wp_error($result);
    }
}
