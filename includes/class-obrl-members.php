<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Members
{
    const STATUSES = 'active,inactive,suspended';
    const PER_PAGE = 20;

    public static function statuses()
    {
        return array(
            'active'    => __('Active', 'obydullah-restaurant-loyalty'),
            'inactive'  => __('Inactive', 'obydullah-restaurant-loyalty'),
            'suspended' => __('Suspended', 'obydullah-restaurant-loyalty'),
        );
    }

    public static function table()
    {
        return OBRL_Schema::table('members');
    }

    public static function get($member_id)
    {
        global $wpdb;
        // Reject rather than abs(): a negative id must never resolve to a real row.
        $member_id = is_numeric($member_id) ? (int) $member_id : 0;
        $table = self::table();
        if (!$table || $member_id < 1) {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $member = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d AND deleted_at IS NULL',
                $table,
                $member_id
            )
        );
        return $member ? self::hydrate($member) : null;
    }

    public static function get_by($column, $value)
    {
        // Checked against an allowlist, so it is safe to bind as an identifier.
        $allowed = array('member_code', 'qr_token', 'referral_code', 'user_id', 'email', 'phone');
        if (!in_array($column, $allowed, true)) {
            return null;
        }
        $table = self::table();
        if (!$table || '' === $value || null === $value) {
            return null;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $member = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE %i = %s AND deleted_at IS NULL',
                $table,
                $column,
                $value
            )
        );
        return $member ? self::hydrate($member) : null;
    }

    public static function get_by_user_id($user_id)
    {
        return self::get_by('user_id', absint($user_id));
    }

    public static function get_by_qr_token($token)
    {
        return self::get_by('qr_token', sanitize_text_field($token));
    }

    public static function full_name($member)
    {
        $member = (array) $member;
        $name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        return '' !== $name ? $name : __('(no name)', 'obydullah-restaurant-loyalty');
    }

    public static function initials($member)
    {
        $member = (array) $member;
        $first = isset($member['first_name']) ? substr(trim($member['first_name']), 0, 1) : '';
        $last = isset($member['last_name']) ? substr(trim($member['last_name']), 0, 1) : '';
        $initials = strtoupper($first . $last);
        return '' !== $initials ? $initials : '?';
    }

    /**
     * Validates and normalises member input. Returns WP_Error on failure.
     *
     * Every value must be a scalar. Both request boundaries -- the admin
     * handlers and the member portal -- reject non-scalar fields before
     * calling this, because the allowlist lookups below are not safe to run
     * against an array.
     *
     * @param array $data      Submitted fields.
     * @param int   $member_id Member being updated, or 0 when enrolling.
     * @return array|WP_Error
     */
    public static function validate($data, $member_id = 0)
    {
        $errors = array();
        $data = wp_parse_args(
            $data,
            array(
                'first_name' => '',
                'last_name'  => '',
                'email'      => '',
                'phone'      => '',
                'birthday'   => '',
                'status'     => 'active',
                'notes'      => '',
            )
        );

        $data['first_name'] = sanitize_text_field($data['first_name']);
        $data['last_name'] = sanitize_text_field($data['last_name']);
        $data['notes'] = sanitize_textarea_field($data['notes']);

        if ('' === trim($data['first_name'] . $data['last_name'])) {
            $errors['name'] = __('A first or last name is required.', 'obydullah-restaurant-loyalty');
        }

        // sanitize_email() discards anything unparseable, so compare against
        // the raw input: a non-empty value that sanitises away is a typo, not
        // an absent address.
        $raw_email = trim((string) $data['email']);
        $data['email'] = sanitize_email($raw_email);
        if ('' !== $raw_email && (!is_email($data['email']) || '' === $data['email'])) {
            $errors['email'] = __('That email address is not valid.', 'obydullah-restaurant-loyalty');
        }
        if (OBRL_Settings::get('require_email') && '' === $data['email']) {
            $errors['email'] = __('An email address is required.', 'obydullah-restaurant-loyalty');
        }

        $data['phone'] = self::sanitize_phone($data['phone']);
        if (OBRL_Settings::get('require_phone') && '' === $data['phone']) {
            $errors['phone'] = __('A phone number is required.', 'obydullah-restaurant-loyalty');
        }

        $data['birthday'] = self::sanitize_date($data['birthday']);
        if (!empty($data['birthday']) && !self::is_valid_birthday($data['birthday'])) {
            $errors['birthday'] = __('Enter a birthday in YYYY-MM-DD format.', 'obydullah-restaurant-loyalty');
        }

        if (!array_key_exists($data['status'], self::statuses())) {
            $errors['status'] = __('Unknown member status.', 'obydullah-restaurant-loyalty');
        }

        $data['status'] = array_key_exists($data['status'], self::statuses()) ? $data['status'] : 'active';

        if ($errors) {
            return new WP_Error('orl_member_invalid', __('Please correct the highlighted fields.', 'obydullah-restaurant-loyalty'), $errors);
        }

        return $data;
    }

    public static function create($data)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return new WP_Error('orl_no_schema', __('The loyalty database is not installed.', 'obydullah-restaurant-loyalty'));
        }

        $clean = self::validate($data);
        if (is_wp_error($clean)) {
            return $clean;
        }

        $now = current_time('mysql', true);
        $row = array(
            'member_code'          => self::generate_code('member_code'),
            'qr_token'             => wp_generate_password(48, false, false),
            'first_name'           => $clean['first_name'],
            'last_name'            => $clean['last_name'],
            'email'                => $clean['email'],
            'phone'                => $clean['phone'],
            'birthday'             => $clean['birthday'] ? $clean['birthday'] : null,
            'status'               => $clean['status'],
            'notes'                => $clean['notes'],
            'referral_code'        => self::generate_code('referral_code'),
            'birthday_reward_year' => 0,
            'enrolled_by'          => get_current_user_id(),
            'joined_at'            => $now,
            'created_at'           => $now,
            'updated_at'           => $now,
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $result = $wpdb->insert(
            $table,
            $row,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );

        if (false === $result) {
            return new WP_Error('orl_member_create_failed', __('The member could not be saved.', 'obydullah-restaurant-loyalty'));
        }

        $member_id = (int) $wpdb->insert_id;
        OBRL_Logger::log(
            'member_created',
            array(
                'member_id'   => $member_id,
                'object_type' => 'member',
                'object_id'   => $member_id,
                'message'     => sprintf('Member %s enrolled.', $row['member_code']),
                'context'     => array('member_code' => $row['member_code']),
            )
        );

        OBRL_Welcome::maybe_grant(self::get($member_id));

        return $member_id;
    }

    public static function update($member_id, $data)
    {
        global $wpdb;
        $table = self::table();
        $member = self::get($member_id);
        if (!$table || !$member) {
            return new WP_Error('orl_member_missing', __('That member no longer exists.', 'obydullah-restaurant-loyalty'));
        }

        $clean = self::validate($data, $member_id);
        if (is_wp_error($clean)) {
            return $clean;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $result = $wpdb->update(
            $table,
            array(
                'first_name' => $clean['first_name'],
                'last_name'  => $clean['last_name'],
                'email'      => $clean['email'],
                'phone'      => $clean['phone'],
                'birthday'   => $clean['birthday'] ? $clean['birthday'] : null,
                'status'     => $clean['status'],
                'notes'      => $clean['notes'],
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => (int) $member_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if (false === $result) {
            return new WP_Error('orl_member_update_failed', __('The member could not be updated.', 'obydullah-restaurant-loyalty'));
        }

        OBRL_Logger::log(
            'member_updated',
            array(
                'member_id'   => (int) $member_id,
                'object_type' => 'member',
                'object_id'   => (int) $member_id,
            )
        );

        return true;
    }

    public static function delete($member_id)
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $result = $wpdb->update(
            $table,
            array(
                'status'     => 'inactive',
                'deleted_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => (int) $member_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        if (false === $result) {
            return false;
        }
        OBRL_Logger::log(
            'member_deleted',
            array(
                'member_id'   => (int) $member_id,
                'object_type' => 'member',
                'object_id'   => (int) $member_id,
                'severity'    => 'warning',
            )
        );
        return true;
    }

    public static function search($args = array())
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return array('items' => array(), 'total' => 0, 'pages' => 0);
        }

        $args = wp_parse_args(
            $args,
            array(
                'search'   => '',
                'status'   => '',
                'page'     => 1,
                'per_page' => self::PER_PAGE,
                'orderby'  => 'created_at',
                'order'    => 'DESC',
            )
        );

        // Both filters are optional, so each is written as an always-present
        // predicate that goes neutral when its filter is not in use. An unset
        // status is compared against the empty string, and that first branch
        // then holds for every row; an unset search term becomes '%', and
        // LIKE '%' matches every one of these NOT NULL columns. Holding the
        // query at one literal shape means every value reaches MySQL as a
        // bound placeholder and no part of the statement is ever assembled by
        // concatenating a variable into it.
        $status = ( '' !== $args['status'] && array_key_exists( $args['status'], self::statuses() ) )
            ? $args['status']
            : '';
        $term = sanitize_text_field( $args['search'] );
        $like = '' !== $term ? '%' . $wpdb->esc_like( $term ) . '%' : '%';

        $orderby = in_array($args['orderby'], array('id', 'first_name', 'last_name', 'member_code', 'points_balance', 'created_at', 'last_visit_at'), true)
            ? $args['orderby']
            : 'created_at';
        $order = 'ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                   FROM %i
                  WHERE deleted_at IS NULL
                    AND (%s = \'\' OR status = %s)
                    AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR member_code LIKE %s)',
                $table,
                $status,
                $status,
                $like,
                $like,
                $like,
                $like,
                $like
            )
        );

        $per_page = max(1, min(100, (int) $args['per_page']));
        $pages = (int) ceil($total / $per_page);
        $page = max(1, min($pages ? $pages : 1, (int) $args['page']));
        $offset = ($page - 1) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT *
                   FROM %i
                  WHERE deleted_at IS NULL
                    AND (%s = \'\' OR status = %s)
                    AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR member_code LIKE %s)
                  ORDER BY %i %s
                  LIMIT %d OFFSET %d',
                $table,
                $status,
                $status,
                $like,
                $like,
                $like,
                $like,
                $like,
                $orderby,
                $order,
                $per_page,
                $offset
            )
        );

        return array(
            'items' => array_map(array(__CLASS__, 'hydrate'), (array) $rows),
            'total' => $total,
            'pages' => $pages,
            'page'  => $page,
        );
    }

    /**
     * Rewrites cached balance columns from the append-only ledger.
     */
    public static function recalculate($member_id)
    {
        return OBRL_Ledger::recalculate_member($member_id);
    }

    public static function stats()
    {
        global $wpdb;
        $table = self::table();
        if (!$table) {
            return array('total' => 0, 'active' => 0, 'new_this_month' => 0, 'points_outstanding' => 0.0);
        }
        $month_start = gmdate('Y-m-01 00:00:00');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS new_this_month,
                    COALESCE(SUM(points_balance), 0) AS points_outstanding
                 FROM %i WHERE deleted_at IS NULL",
                $table,
                $month_start
            ),
            ARRAY_A
        );
        return array(
            'total'              => (int) ($row['total'] ?? 0),
            'active'             => (int) ($row['active'] ?? 0),
            'new_this_month'     => (int) ($row['new_this_month'] ?? 0),
            'points_outstanding' => (float) ($row['points_outstanding'] ?? 0),
        );
    }

    public static function generate_code($column = 'member_code')
    {
        global $wpdb;
        $table = self::table();

        // Interpolated into the query, so it must never come from user input.
        $allowed = array('member_code', 'referral_code');
        if (!in_array($column, $allowed, true)) {
            $column = 'member_code';
        }

        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) OBRL_Settings::get('member_code_prefix', 'MBR')));
        $prefix = '' !== $prefix ? $prefix : 'MBR';

        if ('referral_code' === $column) {
            $prefix = 'REF';
        }

        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $value = $prefix . '-' . substr(str_shuffle($alphabet), 0, 4) . substr(str_shuffle($alphabet), 0, 4);
            $exists = $table
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Balances, plus the loop that mints member codes. Both have to be current: a cached code check would hand out a duplicate, and a cached balance would disagree with the ledger that produced it.
                ? $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE %i = %s', $table, $column, $value))
                : null;
            if (null === $exists) {
                return $value;
            }
        }

        return $prefix . '-' . strtoupper(wp_generate_password(12, false, false));
    }

    public static function sanitize_phone($phone)
    {
        $phone = preg_replace('/[^0-9+\-\s()]/', '', (string) $phone);
        return substr(trim($phone), 0, 50);
    }

    public static function sanitize_date($date)
    {
        $date = trim((string) $date);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    public static function is_valid_birthday($date)
    {
        $parts = explode('-', $date);
        if (3 !== count($parts)) {
            return false;
        }
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return false;
        }
        // A real date, in a plausible range, and not in the future. Newborns
        // are legitimate members even though they cannot earn a birthday
        // reward until next year.
        if ((int) $parts[0] < 1900) {
            return false;
        }
        return strtotime($date) <= strtotime(gmdate('Y-m-d'));
    }

    public static function birthday_this_month($member_id)
    {
        $member = self::get($member_id);
        if (!$member || empty($member['birthday'])) {
            return false;
        }
        return (int) substr($member['birthday'], 5, 2) === (int) gmdate('m');
    }

    private static function hydrate($row)
    {
        if (is_object($row)) {
            $row = (array) $row;
        }
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['points_balance'] = (float) $row['points_balance'];
        $row['stamps_count'] = (int) $row['stamps_count'];
        $row['visits_count'] = (int) $row['visits_count'];
        $row['lifetime_points'] = (float) $row['lifetime_points'];
        $row['total_spend'] = (float) $row['total_spend'];
        $row['birthday_reward_year'] = (int) $row['birthday_reward_year'];
        return $row;
    }
}
