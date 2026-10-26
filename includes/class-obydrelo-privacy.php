<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress privacy integration. Member records hold personal data, so the
 * plugin registers both the exporter and the eraser.
 */
final class OBYDRELO_Privacy
{
    public static function init()
    {
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
    }

    public static function register_exporter($exporters)
    {
        $exporters['obydullah-restaurant-loyalty'] = array(
            'exporter_friendly_name' => __('Restaurant Loyalty', 'obydullah-restaurant-loyalty'),
            'callback'               => array(__CLASS__, 'export'),
        );
        return $exporters;
    }

    public static function register_eraser($erasers)
    {
        $erasers['obydullah-restaurant-loyalty'] = array(
            'eraser_friendly_name' => __('Restaurant Loyalty', 'obydullah-restaurant-loyalty'),
            'callback'             => array(__CLASS__, 'erase'),
        );
        return $erasers;
    }

    public static function member_for_email($email)
    {
        return OBYDRELO_Members::get_by('email', $email);
    }

    public static function export($email, $page = 1)
    {
        $member = self::member_for_email($email);
        if (!$member) {
            return array('data' => array(), 'done' => true);
        }

        $items = array(
            array(
                'group_id'    => 'obydullah-restaurant-loyalty',
                'group_label' => __('Loyalty membership', 'obydullah-restaurant-loyalty'),
                'item_id'     => 'obydrelo-member-' . $member['id'],
                'data'        => array(
                    array('name' => __('Member ID', 'obydullah-restaurant-loyalty'), 'value' => $member['id']),
                    array('name' => __('Member code', 'obydullah-restaurant-loyalty'), 'value' => $member['member_code']),
                    array('name' => __('First name', 'obydullah-restaurant-loyalty'), 'value' => $member['first_name']),
                    array('name' => __('Last name', 'obydullah-restaurant-loyalty'), 'value' => $member['last_name']),
                    array('name' => __('Email', 'obydullah-restaurant-loyalty'), 'value' => $member['email']),
                    array('name' => __('Phone', 'obydullah-restaurant-loyalty'), 'value' => $member['phone']),
                    array('name' => __('Birthday', 'obydullah-restaurant-loyalty'), 'value' => (string) $member['birthday']),
                    array('name' => __('Status', 'obydullah-restaurant-loyalty'), 'value' => $member['status']),
                    array('name' => __('Joined', 'obydullah-restaurant-loyalty'), 'value' => (string) $member['joined_at']),
                ),
            ),
            array(
                'group_id'    => 'obydullah-restaurant-loyalty',
                'group_label' => __('Loyalty balance', 'obydullah-restaurant-loyalty'),
                'item_id'     => 'obydrelo-balance-' . $member['id'],
                'data'        => array(
                    array('name' => __('Points balance', 'obydullah-restaurant-loyalty'), 'value' => $member['points_balance']),
                    array('name' => __('Stamps', 'obydullah-restaurant-loyalty'), 'value' => $member['stamps_count']),
                    array('name' => __('Visits', 'obydullah-restaurant-loyalty'), 'value' => $member['visits_count']),
                    array('name' => __('Lifetime points', 'obydullah-restaurant-loyalty'), 'value' => $member['lifetime_points']),
                    array('name' => __('Total spend', 'obydullah-restaurant-loyalty'), 'value' => $member['total_spend']),
                ),
            ),
        );

        $tx_table    = OBYDRELO_Ledger::table();
        $per_page    = 100;
        $offset      = ($page - 1) * $per_page;
        $type_labels = OBYDRELO_Ledger::types();

        global $wpdb;
        if ($tx_table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The export and the erase both have to see the current rows. An erase that ran against a cached list would leave records behind that the person asked to have removed.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE member_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d',
                    $tx_table,
                    $member['id'],
                    $per_page,
                    $offset
                )
            );
            foreach ((array) $rows as $row) {
                $items[] = array(
                    'group_id'    => 'obydullah-restaurant-loyalty',
                    'group_label' => __('Loyalty transactions', 'obydullah-restaurant-loyalty'),
                    'item_id'     => 'obydrelo-tx-' . $row->id,
                    'data'        => array(
                        array('name' => __('Date', 'obydullah-restaurant-loyalty'), 'value' => $row->created_at),
                        array('name' => __('Type', 'obydullah-restaurant-loyalty'), 'value' => $type_labels[$row->type] ?? $row->type),
                        array('name' => __('Points', 'obydullah-restaurant-loyalty'), 'value' => $row->points),
                        array('name' => __('Stamps', 'obydullah-restaurant-loyalty'), 'value' => $row->stamps),
                        array('name' => __('Visits', 'obydullah-restaurant-loyalty'), 'value' => $row->visits),
                        array('name' => __('Amount', 'obydullah-restaurant-loyalty'), 'value' => $row->amount),
                        array('name' => __('Note', 'obydullah-restaurant-loyalty'), 'value' => (string) $row->note),
                    ),
                );
            }
        }

        $redemptions = OBYDRELO_Redemptions::list_redemptions(array('member_id' => $member['id'], 'limit' => 1000));
        foreach ($redemptions as $row) {
            $items[] = array(
                'group_id'    => 'obydullah-restaurant-loyalty',
                'group_label' => __('Loyalty redemptions', 'obydullah-restaurant-loyalty'),
                'item_id'     => 'obydrelo-redeem-' . $row->id,
                'data'        => array(
                    array('name' => __('Date', 'obydullah-restaurant-loyalty'), 'value' => $row->issued_at),
                    array('name' => __('Code', 'obydullah-restaurant-loyalty'), 'value' => $row->redemption_code),
                    array('name' => __('Status', 'obydullah-restaurant-loyalty'), 'value' => $row->status),
                    array('name' => __('Points spent', 'obydullah-restaurant-loyalty'), 'value' => $row->points_spent),
                ),
            );
        }

        return array(
            'data' => $items,
            'done' => count($items) < ($per_page + 1),
        );
    }

    public static function erase($email, $page = 1)
    {
        $member = self::member_for_email($email);
        if (!$member) {
            return array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
        }

        $removed = false;
        $retained = false;
        $messages = array();

        // Free item and free value rewards may be mid-redemption; a member with
        // an open redemption is retained so financial history stays intact.
        $open = OBYDRELO_Redemptions::list_redemptions(
            array('member_id' => $member['id'], 'status' => 'issued', 'limit' => 1)
        );
        if ($open) {
            $retained = true;
            $messages[] = __('Loyalty member has an uncollected reward and was not erased.', 'obydullah-restaurant-loyalty');
            return array(
                'items_removed'  => false,
                'items_retained' => true,
                'messages'       => $messages,
                'done'           => true,
            );
        }

        global $wpdb;

        $members = OBYDRELO_Members::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The export and the erase both have to see the current rows. An erase that ran against a cached list would leave records behind that the person asked to have removed.
        $wpdb->update(
            $members,
            array(
                'first_name' => '',
                'last_name'  => '',
                'email'      => '',
                'phone'      => '',
                'birthday'   => null,
                'notes'      => '',
                'status'     => 'inactive',
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => (int) $member['id']),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        $removed = true;

        $tx = OBYDRELO_Ledger::table();
        if ($tx) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The export and the erase both have to see the current rows. An erase that ran against a cached list would leave records behind that the person asked to have removed.
            $wpdb->update(
                $tx,
                array('note' => __('Member data erased', 'obydullah-restaurant-loyalty')),
                array('member_id' => (int) $member['id']),
                array('%s'),
                array('%d')
            );
        }

        $logs = OBYDRELO_Schema::table('activity_logs');
        if ($logs) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The export and the erase both have to see the current rows. An erase that ran against a cached list would leave records behind that the person asked to have removed.
            $wpdb->update(
                $logs,
                array(
                    'user_login' => '',
                    'ip_address' => '',
                    'user_agent' => '',
                ),
                array('member_id' => (int) $member['id']),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }

        $messages[] = __('Loyalty member personal data was anonymised. Transaction and audit records were retained for accounting purposes.', 'obydullah-restaurant-loyalty');

        return array(
            'items_removed'  => $removed,
            'items_retained' => $retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }
}
