<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Plugin
{
    private static $booted = false;

    public static function boot()
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // Translations are not loaded by hand. WordPress resolves the
        // languages/ directory from the plugin slug declared in the header and
        // loads the .mo file itself when the site locale calls for one.

        OBRL_Cron::init();
        OBRL_Privacy::init();

        if (is_admin()) {
            new OBRL_Admin();
        }

        new OBRL_Portal();
    }
}
