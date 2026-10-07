<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Cron
{
    public static function init()
    {
        add_action(OBRL_CRON_DAILY, array(__CLASS__, 'run_daily'));
    }

    public static function run_daily()
    {
        $birthdays = OBRL_Birthday::grant_due();
        $expired   = OBRL_Redemptions::expire_overdue();

        OBRL_Logger::log(
            'cron_daily',
            array(
                'object_type' => 'system',
                'context'     => array(
                    'birthday_rewards' => (int) $birthdays,
                    'expired_rewards'  => (int) $expired,
                ),
            )
        );

        do_action('obrl_daily_maintenance_complete', $birthdays, $expired);
    }
}
