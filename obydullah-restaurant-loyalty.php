<?php
/**
 * Plugin Name: Obydullah Restaurant Loyalty
 * Description: Restaurant loyalty program with points, stamps, or visit tracking, staff-recorded transactions, rewards, and a member portal.
 * Version: 1.0.0
 * Author: Shaik Obydullah
 * Author URI: https://obydullah.com
 * Text Domain: obydullah-restaurant-loyalty
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OBYDRELO_VERSION', '1.0.0');

define('OBYDRELO_FILE', __FILE__);
define('OBYDRELO_PATH', plugin_dir_path(__FILE__));
define('OBYDRELO_URL', plugin_dir_url(__FILE__));
define('OBYDRELO_NONCE_ADMIN', 'obydrelo_admin_action');
define('OBYDRELO_NONCE_STAFF', 'obydrelo_staff_action');
define('OBYDRELO_NONCE_PORTAL', 'obydrelo_portal_action');
define('OBYDRELO_CRON_DAILY', 'obydrelo_daily_maintenance');

require_once OBYDRELO_PATH . 'includes/class-obydrelo-plugin.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-activator.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-roles.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-settings.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-schema.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-logger.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-format.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-members.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-ledger.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-rewards.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-redemptions.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-referrals.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-welcome.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-birthday.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-card.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-reports.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-csv.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-cron.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-privacy.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-admin.php';
require_once OBYDRELO_PATH . 'includes/class-obydrelo-portal.php';

register_activation_hook(OBYDRELO_FILE, array('OBYDRELO_Activator', 'activate'));
register_deactivation_hook(OBYDRELO_FILE, array('OBYDRELO_Activator', 'deactivate'));

add_action('plugins_loaded', array('OBYDRELO_Plugin', 'boot'));
add_action('init', array('OBYDRELO_Activator', 'maybe_upgrade'));