<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Cron
{
    public static function init()
    {
        add_action(OBYDRELO_CRON_DAILY, array(__CLASS__, 'run_daily'));
    }

    public static function run_daily()
    {
        $birthdays = OBYDRELO_Birthday::grant_due();
        $expired   = OBYDRELO_Redemptions::expire_overdue();

        OBYDRELO_Logger::log(
            'cron_daily',
            array(
                'object_type' => 'system',
                'context'     => array(
                    'birthday_rewards' => (int) $birthdays,
                    'expired_rewards'  => (int) $expired,
                ),
            )
        );

        do_action('obydrelo_daily_maintenance_complete', $birthdays, $expired);
    }
}
