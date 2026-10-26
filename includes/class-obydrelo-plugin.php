<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Plugin
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

        OBYDRELO_Cron::init();
        OBYDRELO_Privacy::init();

        if (is_admin()) {
            new OBYDRELO_Admin();
        }

        new OBYDRELO_Portal();
    }
}
