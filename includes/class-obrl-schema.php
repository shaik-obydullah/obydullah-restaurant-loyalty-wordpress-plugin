<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Table prefix for the seven plugin tables, appended to $wpdb->prefix.
 *
 * It is defined here, beside the only code that reads it, rather than in the
 * main plugin file, because this class is also loaded on its own. WordPress
 * includes uninstall.php without the plugin's main file, and an undefined
 * constant is a fatal error, so a definition that only the main file performed
 * would turn every uninstall into a crash. Defining it in both places would
 * instead be two places to keep in step.
 *
 * OBRL_Schema::table(), table_names(), statements() and drop() all derive every
 * table name from it, so a query can never be built against a name the
 * installer did not create. Nothing else in the plugin may hardcode the string.
 */
if (!defined('OBRL')) {
    define('OBRL', 'obrl_');
}

final class OBRL_Schema
{
    const TABLES = 'members,member_transactions,rewards,redemptions,referrals,program_settings,activity_logs';

    /**
     * Per-request cache of the table check behind is_installed(). Null means
     * not yet looked up.
     *
     * @var bool|null
     */
    private static $installed = null;

    /*
     * Why this plugin queries $wpdb directly
     * ---------------------------------------
     * The WordPress plugin checker reports every one of those calls, and the
     * report is expected here rather than a defect to be refactored away. It
     * flags the absence of an abstraction that does not exist for this data.
     *
     * These seven tables hold the program itself: a member balance, an
     * append-only ledger of every movement behind that balance, and the
     * rewards and redemptions drawn against it. None of it can be modelled as
     * posts or as user meta, so there is no query layer to move it behind. The
     * alternative, denormalising a running balance into user meta to satisfy a
     * linter, would trade a reportable warning for a genuine integrity risk.
     *
     * The no-caching half of the same report matters more. A member's points,
     * stamps, and visits are money, and a redemption debits the ledger while
     * two staff can be acting on the same screen at once. A cached balance row
     * would hand one of them a figure that is already wrong. Every read here
     * is deliberately uncached and authoritative, and any balance can be
     * rebuilt from the ledger, which is what OBRL_Ledger::recalculate_member()
     * exists for.
     *
     * Every identifier that reaches a query is escaped with prepare()'s %i
     * placeholder, and every value is bound. The rules that must not be bent
     * are those two, not the choice of table.
     */

    public static function prefix()
    {
        global $wpdb;
        return $wpdb->prefix . OBRL;
    }

    /**
     * Returns the fully prefixed table name for a known base table.
     * Unknown names return an empty string so callers never interpolate
     * an unchecked value into a query.
     */
    public static function table($name)
    {
        $allowed = explode(',', self::TABLES);
        if (!in_array($name, $allowed, true)) {
            return '';
        }
        return self::prefix() . $name;
    }

    public static function table_names()
    {
        global $wpdb;
        $prefix = self::prefix();
        $names = array();
        foreach (explode(',', self::TABLES) as $name) {
            $names[] = $prefix . $name;
        }
        return $names;
    }

    public static function statements()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = self::prefix();

        $statements = array();

        $statements['members'] = "CREATE TABLE {$prefix}members (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_code varchar(50) NOT NULL DEFAULT '',
            qr_token varchar(64) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NULL DEFAULT NULL,
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(100) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            birthday date NULL DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            notes text NULL,
            points_balance decimal(15,2) NOT NULL DEFAULT 0.00,
            stamps_count int(11) NOT NULL DEFAULT 0,
            visits_count int(11) NOT NULL DEFAULT 0,
            lifetime_points decimal(15,2) NOT NULL DEFAULT 0.00,
            lifetime_redemptions int(11) NOT NULL DEFAULT 0,
            total_spend decimal(15,2) NOT NULL DEFAULT 0.00,
            tier varchar(50) NOT NULL DEFAULT '',
            referral_code varchar(50) NOT NULL DEFAULT '',
            referred_by_id bigint(20) unsigned NULL DEFAULT NULL,
            birthday_reward_year int(11) NOT NULL DEFAULT 0,
            welcome_reward_claimed tinyint(1) NOT NULL DEFAULT 0,
            enrolled_by bigint(20) unsigned NOT NULL DEFAULT 0,
            last_visit_at datetime NULL DEFAULT NULL,
            last_transaction_at datetime NULL DEFAULT NULL,
            joined_at datetime NULL DEFAULT NULL,
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            deleted_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY member_code (member_code),
            UNIQUE KEY qr_token (qr_token),
            UNIQUE KEY referral_code (referral_code),
            UNIQUE KEY user_id (user_id),
            KEY email (email),
            KEY phone (phone),
            KEY last_name (last_name),
            KEY status (status),
            KEY birthday (birthday),
            KEY referred_by_id (referred_by_id),
            KEY deleted_at (deleted_at)
        ) ENGINE=InnoDB {$charset};";

        $statements['member_transactions'] = "CREATE TABLE {$prefix}member_transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL DEFAULT 0,
            type varchar(30) NOT NULL DEFAULT 'adjustment',
            source varchar(30) NOT NULL DEFAULT 'staff',
            points decimal(15,2) NOT NULL DEFAULT 0.00,
            stamps int(11) NOT NULL DEFAULT 0,
            visits int(11) NOT NULL DEFAULT 0,
            amount decimal(15,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT '',
            points_after decimal(15,2) NULL DEFAULT NULL,
            stamps_after int(11) NULL DEFAULT NULL,
            visits_after int(11) NULL DEFAULT NULL,
            reward_id bigint(20) unsigned NULL DEFAULT NULL,
            redemption_id bigint(20) unsigned NULL DEFAULT NULL,
            reference_type varchar(30) NOT NULL DEFAULT '',
            reference_id bigint(20) unsigned NULL DEFAULT NULL,
            reversal_of_id bigint(20) unsigned NULL DEFAULT NULL,
            external_id varchar(100) NOT NULL DEFAULT '',
            id_hash char(64) NULL DEFAULT NULL,
            staff_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            staff_login varchar(60) NOT NULL DEFAULT '',
            note text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY id_hash (id_hash),
            KEY member_created (member_id, created_at),
            KEY member_type (member_id, type),
            KEY type (type),
            KEY external_id (external_id),
            KEY staff_user_id (staff_user_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        $statements['rewards'] = "CREATE TABLE {$prefix}rewards (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(191) NOT NULL DEFAULT '',
            slug varchar(191) NOT NULL DEFAULT '',
            description text NULL,
            type varchar(30) NOT NULL DEFAULT 'fixed_value',
            points_cost decimal(15,2) NOT NULL DEFAULT 0.00,
            stamps_cost int(11) NOT NULL DEFAULT 0,
            visits_cost int(11) NOT NULL DEFAULT 0,
            value decimal(15,2) NOT NULL DEFAULT 0.00,
            percent_off decimal(6,2) NOT NULL DEFAULT 0.00,
            max_discount decimal(15,2) NOT NULL DEFAULT 0.00,
            min_purchase decimal(15,2) NOT NULL DEFAULT 0.00,
            item_name varchar(191) NOT NULL DEFAULT '',
            item_id bigint(20) unsigned NOT NULL DEFAULT 0,
            usage_limit int(11) NULL DEFAULT NULL,
            used_count int(11) NOT NULL DEFAULT 0,
            per_member_limit int(11) NOT NULL DEFAULT 0,
            validity_days int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            image_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            deleted_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY type (type),
            KEY is_active (is_active),
            KEY sort_order (sort_order),
            KEY deleted_at (deleted_at)
        ) ENGINE=InnoDB {$charset};";

        $statements['redemptions'] = "CREATE TABLE {$prefix}redemptions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            redemption_code varchar(50) NOT NULL DEFAULT '',
            member_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reward_id bigint(20) unsigned NOT NULL DEFAULT 0,
            staff_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source varchar(30) NOT NULL DEFAULT 'staff',
            points_spent decimal(15,2) NOT NULL DEFAULT 0.00,
            stamps_spent int(11) NOT NULL DEFAULT 0,
            visits_spent int(11) NOT NULL DEFAULT 0,
            reward_value decimal(15,2) NOT NULL DEFAULT 0.00,
            percent_off decimal(6,2) NOT NULL DEFAULT 0.00,
            item_name varchar(191) NOT NULL DEFAULT '',
            quantity int(11) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'issued',
            bill_reference varchar(100) NOT NULL DEFAULT '',
            external_id varchar(100) NOT NULL DEFAULT '',
            id_hash char(64) NULL DEFAULT NULL,
            note text NULL,
            issued_at datetime NOT NULL,
            used_at datetime NULL DEFAULT NULL,
            expires_at datetime NULL DEFAULT NULL,
            cancelled_at datetime NULL DEFAULT NULL,
            cancelled_by bigint(20) unsigned NOT NULL DEFAULT 0,
            cancelled_reason varchar(255) NOT NULL DEFAULT '',
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY redemption_code (redemption_code),
            UNIQUE KEY id_hash (id_hash),
            KEY member_status (member_id, status),
            KEY reward_id (reward_id),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY external_id (external_id),
            KEY bill_reference (bill_reference),
            KEY issued_at (issued_at)
        ) ENGINE=InnoDB {$charset};";

        $statements['referrals'] = "CREATE TABLE {$prefix}referrals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_member_id bigint(20) unsigned NOT NULL DEFAULT 0,
            referred_member_id bigint(20) unsigned NOT NULL DEFAULT 0,
            referral_code varchar(50) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            qualification varchar(30) NOT NULL DEFAULT 'first_purchase',
            reward_referrer_points decimal(15,2) NOT NULL DEFAULT 0.00,
            reward_referrer_stamps int(11) NOT NULL DEFAULT 0,
            reward_referred_points decimal(15,2) NOT NULL DEFAULT 0.00,
            reward_referred_stamps int(11) NOT NULL DEFAULT 0,
            transaction_id bigint(20) unsigned NULL DEFAULT NULL,
            note text NULL,
            qualified_at datetime NULL DEFAULT NULL,
            rewarded_at datetime NULL DEFAULT NULL,
            rejected_at datetime NULL DEFAULT NULL,
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY referred_member_id (referred_member_id),
            KEY referrer_member_id (referrer_member_id),
            KEY referral_code (referral_code),
            KEY status (status),
            KEY created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        $statements['program_settings'] = "CREATE TABLE {$prefix}program_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL DEFAULT '',
            setting_value longtext NULL,
            setting_group varchar(50) NOT NULL DEFAULT 'general',
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NULL DEFAULT NULL,
            updated_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key),
            KEY setting_group (setting_group)
        ) ENGINE=InnoDB {$charset};";

        $statements['activity_logs'] = "CREATE TABLE {$prefix}activity_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NULL DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_login varchar(60) NOT NULL DEFAULT '',
            actor_type varchar(20) NOT NULL DEFAULT 'system',
            object_type varchar(30) NOT NULL DEFAULT '',
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(50) NOT NULL DEFAULT '',
            severity varchar(10) NOT NULL DEFAULT 'info',
            message text NULL,
            context longtext NULL,
            ip_address varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY object (object_type, object_id),
            KEY member_created (member_id, created_at),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        return $statements;
    }

    public static function install()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $prefix = self::prefix();
        $statements = self::statements();
        $errors = array();

        foreach ($statements as $table => $statement) {
            $wpdb->last_error = '';
            $result = dbDelta($statement);
            if (false === $result || $wpdb->last_error) {
                $errors[] = $prefix . $table . ': ' . $wpdb->last_error;
            }
        }

        foreach (array_keys($statements) as $table) {
            $wpdb->last_error = '';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- dbDelta() in install() is the supported way to create and upgrade custom tables and drop() has no dbDelta equivalent. Nothing here reads a balance.
            if ($prefix . $table !== $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . $table))) {
                $errors[] = $prefix . $table . ': table was not created.';
            }
        }

        if ($errors) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                foreach ($errors as $error) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostics.
                    error_log('[obydullah-restaurant-loyalty] ' . $error);
                }
            }
            return false;
        }

        self::$installed = true;
        return true;
    }

    /**
     * Whether the plugin's tables are present.
     *
     * This used to compare a stored version string against a constant, which
     * answered the question without ever looking at the database: an install
     * whose tables had been dropped still reported itself as installed, and
     * every caller below then queried a table that was not there. Asking the
     * database costs one SHOW TABLES per request, held in a static because the
     * four callers all run on the same request.
     */
    public static function is_installed()
    {
        global $wpdb;
        if (null !== self::$installed) {
            return self::$installed;
        }

        $table = self::table('members');
        if (!$table) {
            self::$installed = false;
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One SHOW TABLES per request so the callers do not query tables that do not exist. No balance is read.
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        self::$installed = ($table === $found);
        return self::$installed;
    }

    public static function drop()
    {
        global $wpdb;
        $failed = false;
        foreach (self::table_names() as $table) {
            // %i binds the name as a quoted identifier. Every name here comes
            // from table_names(), which is $wpdb->prefix plus a hard-coded list.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- dbDelta() in install() is the supported way to create and upgrade custom tables and drop() has no dbDelta equivalent. Nothing here reads a balance.
            if (false === $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table))) {
                $failed = true;
            }
        }
        self::$installed = false;
        return !$failed;
    }
}
