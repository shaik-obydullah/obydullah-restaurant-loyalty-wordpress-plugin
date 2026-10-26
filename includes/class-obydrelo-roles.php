<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Roles
{
    public static function manager_capabilities()
    {
        return array(
            'obydrelo_view_dashboard',
            'obydrelo_manage_members',
            'obydrelo_manage_rewards',
            'obydrelo_record_transactions',
            'obydrelo_redeem_rewards',
            'obydrelo_apply_adjustments',
            'obydrelo_manage_referrals',
            'obydrelo_view_reports',
            'obydrelo_view_activity',
            'obydrelo_export_csv',
            'obydrelo_manage_settings',
        );
    }

    public static function staff_capabilities()
    {
        return array(
            'obydrelo_view_dashboard',
            'obydrelo_manage_members',
            'obydrelo_record_transactions',
            'obydrelo_redeem_rewards',
            'obydrelo_manage_referrals',
        );
    }

    public static function member_capabilities()
    {
        return array(
            'read',
            'obydrelo_access_portal',
            'obydrelo_redeem_own_rewards',
            'obydrelo_manage_own_profile',
        );
    }

    public static function role_names()
    {
        return array('obydrelo_manager', 'obydrelo_staff', 'obydrelo_member');
    }

    public static function install()
    {
        $manager = array('read' => true);
        foreach (self::manager_capabilities() as $capability) {
            $manager[$capability] = true;
        }
        self::upsert_role('obydrelo_manager', __('Loyalty Manager', 'obydullah-restaurant-loyalty'), $manager);

        $staff = array('read' => true);
        foreach (self::staff_capabilities() as $capability) {
            $staff[$capability] = true;
        }
        self::upsert_role('obydrelo_staff', __('Loyalty Staff', 'obydullah-restaurant-loyalty'), $staff);

        $member = array();
        foreach (self::member_capabilities() as $capability) {
            $member[$capability] = true;
        }
        self::upsert_role('obydrelo_member', __('Loyalty Member', 'obydullah-restaurant-loyalty'), $member);

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (self::manager_capabilities() as $capability) {
                $administrator->add_cap($capability);
            }
            foreach (self::member_capabilities() as $capability) {
                if ('read' === $capability) {
                    continue;
                }
                $administrator->add_cap($capability);
            }
        }
    }

    public static function uninstall()
    {
        foreach (self::role_names() as $role_name) {
            if (get_role($role_name)) {
                remove_role($role_name);
            }
        }
        $administrator = get_role('administrator');
        if (!$administrator) {
            return;
        }
        foreach (array_keys($administrator->capabilities) as $capability) {
            if (0 === strpos($capability, 'obydrelo_')) {
                $administrator->remove_cap($capability);
            }
        }
    }

    private static function upsert_role($role_name, $display_name, $capabilities)
    {
        $role = get_role($role_name);
        if (!$role) {
            add_role($role_name, $display_name, $capabilities);
            return;
        }
        foreach ($capabilities as $capability => $enabled) {
            if ($enabled) {
                $role->add_cap($capability);
            }
        }
    }
}
