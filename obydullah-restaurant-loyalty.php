<?php
/**
 * Plugin Name: Obydullah Restaurant Loyalty
 * Description: Restaurant loyalty program with points, stamps, or visit tracking, staff-recorded transactions, rewards, and a member portal.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Tested up to: 7.1
 * Author: Shaik Obydullah
 * Author URI: https://obydullah.com
 * Text Domain: obydullah-restaurant-loyalty
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    exit;
}

define('OBRL_VERSION', '1.0.0');

define('OBRL_FILE', __FILE__);
define('OBRL_PATH', plugin_dir_path(__FILE__));
define('OBRL_URL', plugin_dir_url(__FILE__));
define('OBRL_NONCE_ADMIN', 'orl_admin_action');
define('OBRL_NONCE_STAFF', 'orl_staff_action');
define('OBRL_NONCE_PORTAL', 'orl_portal_action');
define('OBRL_CRON_DAILY', 'obrl_daily_maintenance');

require_once OBRL_PATH . 'includes/class-obrl-plugin.php';
require_once OBRL_PATH . 'includes/class-obrl-activator.php';
require_once OBRL_PATH . 'includes/class-obrl-roles.php';
require_once OBRL_PATH . 'includes/class-obrl-settings.php';
require_once OBRL_PATH . 'includes/class-obrl-schema.php';
require_once OBRL_PATH . 'includes/class-obrl-logger.php';
require_once OBRL_PATH . 'includes/class-obrl-format.php';
require_once OBRL_PATH . 'includes/class-obrl-members.php';
require_once OBRL_PATH . 'includes/class-obrl-ledger.php';
require_once OBRL_PATH . 'includes/class-obrl-rewards.php';
require_once OBRL_PATH . 'includes/class-obrl-redemptions.php';
require_once OBRL_PATH . 'includes/class-obrl-referrals.php';
require_once OBRL_PATH . 'includes/class-obrl-welcome.php';
require_once OBRL_PATH . 'includes/class-obrl-birthday.php';
require_once OBRL_PATH . 'includes/class-obrl-card.php';
require_once OBRL_PATH . 'includes/class-obrl-reports.php';
require_once OBRL_PATH . 'includes/class-obrl-csv.php';
require_once OBRL_PATH . 'includes/class-obrl-cron.php';
require_once OBRL_PATH . 'includes/class-obrl-privacy.php';
require_once OBRL_PATH . 'includes/class-obrl-admin.php';
require_once OBRL_PATH . 'includes/class-obrl-portal.php';

register_activation_hook(OBRL_FILE, array('OBRL_Activator', 'activate'));
register_deactivation_hook(OBRL_FILE, array('OBRL_Activator', 'deactivate'));

add_action('plugins_loaded', array('OBRL_Plugin', 'boot'));
add_action('init', array('OBRL_Activator', 'maybe_upgrade'));