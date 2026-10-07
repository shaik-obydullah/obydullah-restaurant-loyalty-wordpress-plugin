<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Admin
{
    const MENU = 'orl-loyalty';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));

        add_action('admin_post_orl_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_orl_save_member', array($this, 'handle_save_member'));
        add_action('admin_post_orl_delete_member', array($this, 'handle_delete_member'));
        add_action('admin_post_orl_save_reward', array($this, 'handle_save_reward'));
        add_action('admin_post_orl_delete_reward', array($this, 'handle_delete_reward'));
        add_action('admin_post_orl_record_transaction', array($this, 'handle_record_transaction'));
        add_action('admin_post_orl_redeem', array($this, 'handle_redeem'));
        add_action('admin_post_orl_cancel_redemption', array($this, 'handle_cancel_redemption'));
        add_action('admin_post_orl_export', array($this, 'handle_export'));

        add_action('wp_ajax_orl_search_members', array($this, 'ajax_search_members'));
        add_action('wp_ajax_orl_get_member', array($this, 'ajax_get_member'));
        add_action('wp_ajax_orl_member_history', array($this, 'ajax_member_history'));
        add_action('wp_ajax_orl_recalculate', array($this, 'ajax_recalculate'));
        add_action('wp_ajax_orl_reward_lookup', array($this, 'ajax_reward_lookup'));
    }

    public function register_menus()
    {
        add_menu_page(
            __('Restaurant Loyalty', 'obydullah-restaurant-loyalty'),
            __('Loyalty', 'obydullah-restaurant-loyalty'),
            'orl_view_dashboard',
            self::MENU,
            array($this, 'render_dashboard'),
            'dashicons-star-filled',
            75
        );

        $pages = array(
            array('orl-members', __('Members', 'obydullah-restaurant-loyalty'), 'orl_manage_members', 'render_members'),
            array('orl-record', __('Record Transaction', 'obydullah-restaurant-loyalty'), 'orl_record_transactions', 'render_record'),
            array('orl-redeem', __('Redeem Reward', 'obydullah-restaurant-loyalty'), 'orl_redeem_rewards', 'render_redeem'),
            array('orl-rewards', __('Rewards', 'obydullah-restaurant-loyalty'), 'orl_manage_rewards', 'render_rewards'),
            array('orl-reports', __('Reports', 'obydullah-restaurant-loyalty'), 'orl_view_reports', 'render_reports'),
            array('orl-referrals', __('Referrals', 'obydullah-restaurant-loyalty'), 'orl_manage_referrals', 'render_referrals'),
            array('orl-activity', __('Activity Log', 'obydullah-restaurant-loyalty'), 'orl_view_activity', 'render_activity'),
            array('orl-settings', __('Settings', 'obydullah-restaurant-loyalty'), 'orl_manage_settings', 'render_settings'),
        );

        foreach ($pages as $page) {
            add_submenu_page(
                self::MENU,
                $page[1],
                $page[1],
                $page[2],
                $page[0],
                array($this, $page[3])
            );
        }
    }

    /**
     * The top level menu registers the hook suffix "toplevel_page_orl-loyalty",
     * but every submenu gets "toplevel_page_orl-<slug>", so matching the parent
     * slug alone would skip all submenu screens.
     */
    public static function is_plugin_screen($hook = '')
    {
        $pages = array(
            'orl-loyalty',
            'orl-members',
            'orl-record',
            'orl-redeem',
            'orl-rewards',
            'orl-reports',
            'orl-referrals',
            'orl-activity',
            'orl-settings',
        );

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ('' !== $page && in_array($page, $pages, true)) {
            return true;
        }

        $hook = (string) $hook;
        foreach ($pages as $slug) {
            if ('_page_' . $slug === substr($hook, -1 * strlen('_page_' . $slug))) {
                return true;
            }
        }
        return false;
    }

    public function enqueue($hook)
    {
        if (!self::is_plugin_screen($hook)) {
            return;
        }

        wp_enqueue_style('orl-admin', OBRL_URL . 'assets/css/admin.css', array(), OBRL_VERSION);
        wp_enqueue_script('orl-qrcode', OBRL_URL . 'assets/js/vendor/qrcode-generator.js', array(), '1.4.4', true);
        wp_enqueue_script('orl-admin', OBRL_URL . 'assets/js/admin.js', array('jquery', 'orl-qrcode'), OBRL_VERSION, true);

        wp_localize_script(
            'orl-admin',
            'orlAdmin',
            array(
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce(OBRL_NONCE_ADMIN),
                'searchNonce' => wp_create_nonce(OBRL_NONCE_STAFF),
                'strings'    => array(
                    'noResults'  => __('No members found.', 'obydullah-restaurant-loyalty'),
                    'select'     => __('Select a member', 'obydullah-restaurant-loyalty'),
                    'confirm'    => __('Are you sure?', 'obydullah-restaurant-loyalty'),
                ),
            )
        );
    }

    private function verify($capability, $nonce_action = OBRL_NONCE_ADMIN)
    {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('You do not have permission to do that.', 'obydullah-restaurant-loyalty'), 403);
        }
        check_admin_referer($nonce_action);
    }

    /**
     * Reads one single-line POST field as a sanitized string.
     *
     * Reading a field is the trust boundary: it decides the type and the shape
     * of everything a validator later sees. A submitted array such as
     * "status[]=x" is a malformed field rather than a value, so it is rejected
     * here instead of being passed along. That is not pedantry. PHP 7.4 only
     * warned when an array reached array_key_exists() or a numeric cast, while
     * PHP 8 raises a TypeError and the request dies with a 500.
     *
     * Sanitizing here covers shape only. Meaning -- is this a known status, is
     * this a real email address, is this a well-formed date -- stays with the
     * validators, which have to see the submitted text intact to be able to
     * report anything back to the user.
     *
     * @param string $key     POST field name.
     * @param string $default Returned when the field is absent or the wrong type.
     * @return string
     */
    private static function post_text($key, $default = '')
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Accessor, not a handler. Every caller reaches this through a handler that has already called check_admin_referer(), and a helper cannot verify a nonce of its own.
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return $default;
        }
        return sanitize_text_field(wp_unslash($_POST[$key]));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Reads one multi-line POST field, keeping the line breaks a textarea
     * carries. Same trust boundary and same wrong-type rejection as
     * post_text().
     *
     * @param string $key     POST field name.
     * @param string $default Returned when the field is absent or the wrong type.
     * @return string
     */
    private static function post_textarea($key, $default = '')
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Accessor, not a handler. See post_text().
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return $default;
        }
        return sanitize_textarea_field(wp_unslash($_POST[$key]));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Redirects back to a plugin screen, carrying any message as a one-time
     * flash token.
     *
     * The text is kept in a transient rather than the URL, so nothing a visitor
     * types can ever be reflected back into the admin chrome as a notice.
     */
    private function redirect($args = array())
    {
        $args = wp_parse_args(
            $args,
            array(
                'page'       => self::MENU,
                'orl_notice' => '',
                'orl_error'  => '',
                'member_id'  => 0,
            )
        );

        $url_args = array_filter(
            array(
                'page'       => $args['page'],
                'member_id'  => $args['member_id'] ? (int) $args['member_id'] : 0,
            ),
            function ($value) {
                return '' !== $value && null !== $value && 0 !== $value;
            }
        );

        if ('' !== $args['orl_notice']) {
            $url_args['orl_msg'] = self::flash('success', $args['orl_notice']);
        }
        if ('' !== $args['orl_error']) {
            $url_args['orl_msg'] = self::flash('error', $args['orl_error']);
        }

        wp_safe_redirect(add_query_arg($url_args, admin_url('admin.php')));
        exit;
    }

    /**
     * Stores a message and returns the token that reads it back exactly once.
     */
    private static function flash($type, $text)
    {
        $token = sanitize_key(wp_generate_password(12, false, false));
        set_transient(
            'orl_flash_' . $token,
            array(
                'type' => 'error' === $type ? 'error' : 'success',
                'text' => (string) $text,
            ),
            5 * MINUTE_IN_SECONDS
        );
        return $token;
    }

    /**
     * Renders and consumes the pending flash message, if there is one.
     */
    public static function notice_from_query()
    {
        // Read-only display parameter: the token is a transient lookup key, not
        // a value, and nothing is echoed unless a message was stored for it.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['orl_msg'])) {
            return;
        }

        $token = sanitize_key(wp_unslash($_GET['orl_msg']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ('' === $token) {
            return;
        }

        $flash = get_transient('orl_flash_' . $token);
        if (!is_array($flash) || empty($flash['text'])) {
            return;
        }
        delete_transient('orl_flash_' . $token);

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($flash['type']),
            esc_html($flash['text'])
        );
    }

    /* ---------------------------------------------------------------------
     * Form handlers
     * ------------------------------------------------------------------ */

    public function handle_save_settings()
    {
        $this->verify('orl_manage_settings');
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing


        $definitions = OBRL_Settings::definitions();
        $saved = 0;
        foreach ($definitions as $key => $definition) {
            if ('bool' === $definition['type']) {
                OBRL_Settings::set($key, isset($_POST['orl_settings'][$key]) ? 1 : 0);
                $saved++;
                continue;
            }
            if (!isset($_POST['orl_settings'][$key]) || !is_scalar($_POST['orl_settings'][$key])) {
                // A checkbox left alone, or a field submitted as an array.
                continue;
            }
            // Sanitized for shape here; OBRL_Settings::set() owns the per-type
            // coercion and allowlisting, so the rules live in one place.
            OBRL_Settings::set($key, sanitize_text_field(wp_unslash($_POST['orl_settings'][$key])));
            $saved++;
        }

        $this->redirect(
            array(
                'page'       => 'orl-settings',
                'orl_notice' => sprintf(
                    /* translators: %d: number of settings saved. */
                    _n('%d setting saved.', '%d settings saved.', $saved, 'obydullah-restaurant-loyalty'),
                    $saved
                ),
            )
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_save_member()
    {
        $this->verify('orl_manage_members');
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing


        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        // Sanitized for shape only; OBRL_Members::validate() decides whether the
        // address is deliverable, the date is real, and the status is known.
        $data = array(
            'first_name' => self::post_text('first_name'),
            'last_name'  => self::post_text('last_name'),
            'email'      => self::post_text('email'),
            'phone'      => self::post_text('phone'),
            'birthday'   => self::post_text('birthday'),
            'status'     => self::post_text('status', 'active'),
            'notes'      => self::post_textarea('notes'),
        );

        $referral_code = self::post_text('referral_code');

        $result = $member_id
            ? OBRL_Members::update($member_id, $data)
            : OBRL_Members::create($data);

        if (is_wp_error($result)) {
            $this->redirect(
                array(
                    'page'      => 'orl-members',
                    'orl_error' => $this->first_error($result),
                    'member_id' => $member_id,
                )
            );
        }

        if ('' !== $referral_code) {
            OBRL_Referrals::link((int) $result, $referral_code);
        }

        $this->redirect(
            array(
                'page'       => 'orl-members',
                'member_id'  => (int) $result,
                'orl_notice' => $member_id
                    ? __('Member updated.', 'obydullah-restaurant-loyalty')
                    : __('Member enrolled.', 'obydullah-restaurant-loyalty'),
            )
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_delete_member()
    {
        $this->verify('orl_manage_members');
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        if ($member_id && OBRL_Members::delete($member_id)) {
            $this->redirect(array('page' => 'orl-members', 'orl_notice' => __('Member removed.', 'obydullah-restaurant-loyalty')));
        }
        $this->redirect(array('page' => 'orl-members', 'orl_error' => __('The member could not be removed.', 'obydullah-restaurant-loyalty')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_save_reward()
    {
        $this->verify('orl_manage_rewards');
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing


        $reward_id = isset($_POST['reward_id']) ? absint($_POST['reward_id']) : 0;
        // Numeric fields stay strings so OBRL_Rewards::validate() can apply its
        // own ranges; an empty usage_limit stays empty so it means "unlimited".
        $data = array(
            'title'            => self::post_text('title'),
            'description'      => self::post_textarea('description'),
            'type'             => self::post_text('type', 'fixed_value'),
            'points_cost'      => self::post_text('points_cost', '0'),
            'stamps_cost'      => self::post_text('stamps_cost', '0'),
            'visits_cost'      => self::post_text('visits_cost', '0'),
            'value'            => self::post_text('value', '0'),
            'percent_off'      => self::post_text('percent_off', '0'),
            'max_discount'     => self::post_text('max_discount', '0'),
            'min_purchase'     => self::post_text('min_purchase', '0'),
            'item_name'        => self::post_text('item_name'),
            'usage_limit'      => self::post_text('usage_limit'),
            'per_member_limit' => self::post_text('per_member_limit', '0'),
            'validity_days'    => self::post_text('validity_days', '0'),
            'sort_order'       => self::post_text('sort_order', '0'),
            'is_active'        => isset($_POST['is_active']) ? 1 : 0,
        );

        $result = $reward_id ? OBRL_Rewards::update($reward_id, $data) : OBRL_Rewards::create($data);

        if (is_wp_error($result)) {
            $this->redirect(array('page' => 'orl-rewards', 'orl_error' => $this->first_error($result), 'reward_id' => $reward_id));
        }

        $this->redirect(
            array(
                'page'       => 'orl-rewards',
                'orl_notice' => $reward_id ? __('Reward updated.', 'obydullah-restaurant-loyalty') : __('Reward created.', 'obydullah-restaurant-loyalty'),
            )
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_delete_reward()
    {
        $this->verify('orl_manage_rewards');
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $reward_id = isset($_POST['reward_id']) ? absint($_POST['reward_id']) : 0;
        if ($reward_id && OBRL_Rewards::delete($reward_id)) {
            $this->redirect(array('page' => 'orl-rewards', 'orl_notice' => __('Reward removed.', 'obydullah-restaurant-loyalty')));
        }
        $this->redirect(array('page' => 'orl-rewards', 'orl_error' => __('The reward could not be removed.', 'obydullah-restaurant-loyalty')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_record_transaction()
    {
        $this->verify('orl_record_transactions', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing


        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'purchase';

        $args = array(
            'member_id'   => $member_id,
            'type'        => $type,
            'source'      => 'staff',
            'external_id' => isset($_POST['external_id']) ? sanitize_text_field(wp_unslash($_POST['external_id'])) : '',
            'note'        => self::post_textarea('note'),
        );

        if ('purchase' === $type) {
            $amount = (float) self::post_text('amount', '0');
            if ($amount <= 0) {
                $this->redirect(array('page' => 'orl-record', 'orl_error' => __('Enter a purchase amount greater than zero.', 'obydullah-restaurant-loyalty')));
            }
            $args = array_merge($args, OBRL_Ledger::values_for_purchase($amount));
        } else {
            // Negative points are meaningful: that is what an adjustment is.
            $args['points'] = (float) self::post_text('points', '0');
            $args['stamps'] = (int) self::post_text('stamps', '0');
            $args['visits'] = (int) self::post_text('visits', '0');

            if ('adjustment' === $type && !current_user_can('orl_apply_adjustments')) {
                $this->redirect(array('page' => 'orl-record', 'orl_error' => __('You cannot apply manual adjustments.', 'obydullah-restaurant-loyalty')));
            }
        }

        $result = OBRL_Ledger::record($args);
        if (is_wp_error($result)) {
            $this->redirect(array('page' => 'orl-record', 'orl_error' => $this->first_error($result), 'member_id' => $member_id));
        }

        $this->redirect(
            array(
                'page'       => 'orl-record',
                'member_id'  => $member_id,
                'orl_notice' => __('Transaction recorded.', 'obydullah-restaurant-loyalty'),
            )
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_redeem()
    {
        $this->verify('orl_redeem_rewards', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing


        $result = OBRL_Redemptions::issue(
            array(
                'member_id'      => isset($_POST['member_id']) ? absint($_POST['member_id']) : 0,
                'reward_id'      => isset($_POST['reward_id']) ? absint($_POST['reward_id']) : 0,
                'bill_amount'    => (float) self::post_text('bill_amount', '0'),
                'bill_reference' => self::post_text('bill_reference'),
                'external_id'    => self::post_text('external_id'),
                'note'           => self::post_textarea('note'),
            )
        );

        if (is_wp_error($result)) {
            $this->redirect(array('page' => 'orl-redeem', 'orl_error' => $this->first_error($result)));
        }

        $this->redirect(array('page' => 'orl-redeem', 'orl_notice' => __('Reward redeemed.', 'obydullah-restaurant-loyalty')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function handle_cancel_redemption()
    {
        $this->verify('orl_redeem_rewards', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify() call above;
        // the sniff cannot follow the indirection into the $_POST reads below.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $redemption_id = isset($_POST['redemption_id']) ? absint($_POST['redemption_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        $result = OBRL_Redemptions::cancel($redemption_id, $reason);
        if (is_wp_error($result)) {
            $this->redirect(array('page' => 'orl-redeem', 'orl_error' => $this->first_error($result)));
        }
        $this->redirect(array('page' => 'orl-redeem', 'orl_notice' => __('Redemption cancelled and balance returned.', 'obydullah-restaurant-loyalty')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /* ---------------------------------------------------------------------
     * AJAX
     * ------------------------------------------------------------------ */

    private function verify_ajax($capability, $nonce_action = OBRL_NONCE_ADMIN)
    {
        // check_ajax_referer() does not halt when the third argument is false,
        // so the return value has to be checked or a stale nonce would be
        // ignored for anyone who already holds the capability.
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(
                array('message' => __('Your session expired. Please reload the page and try again.', 'obydullah-restaurant-loyalty')),
                403
            );
        }
        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => __('You do not have permission to do that.', 'obydullah-restaurant-loyalty')), 403);
        }
    }

    public function ajax_search_members()
    {
        $this->verify_ajax('orl_record_transactions', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify_ajax() call
        // above; the sniff cannot follow the indirection.
        // phpcs:disable WordPress.Security.NonceVerification.Missing


        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (mb_strlen($term) < 2) {
            wp_send_json_success(array('items' => array()));
        }

        $result = OBRL_Members::search(array('search' => $term, 'per_page' => 10));
        $items = array();
        foreach ($result['items'] as $member) {
            $items[] = array(
                'id'         => $member['id'],
                'name'       => OBRL_Members::full_name($member),
                'code'       => $member['member_code'],
                'email'      => $member['email'],
                'phone'      => $member['phone'],
                'status'     => $member['status'],
                'points'     => $member['points_balance'],
                'stamps'     => $member['stamps_count'],
                'visits'     => $member['visits_count'],
                'balance'    => OBRL_Format::points($member['points_balance']) . ' pts / '
                    . $member['stamps_count'] . ' stamps / ' . $member['visits_count'] . ' visits',
            );
        }
        wp_send_json_success(array('items' => $items));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function ajax_get_member()
    {
        $this->verify_ajax('orl_record_transactions', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify_ajax() call
        // above; the sniff cannot follow the indirection.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $member = OBRL_Members::get(isset($_POST['member_id']) ? absint($_POST['member_id']) : 0);
        if (!$member) {
            wp_send_json_error(array('message' => __('Member not found.', 'obydullah-restaurant-loyalty')), 404);
        }
        wp_send_json_success(
            array(
                'id'     => $member['id'],
                'name'   => OBRL_Members::full_name($member),
                'code'   => $member['member_code'],
                'status' => $member['status'],
                'points' => $member['points_balance'],
                'stamps' => $member['stamps_count'],
                'visits' => $member['visits_count'],
            )
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function ajax_member_history()
    {
        $this->verify_ajax('orl_record_transactions', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify_ajax() call
        // above; the sniff cannot follow the indirection.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $history = OBRL_Ledger::history(array('member_id' => $member_id, 'per_page' => 15));
        wp_send_json_success(array('items' => $history['items'], 'total' => $history['total']));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function ajax_recalculate()
    {
        $this->verify_ajax('orl_manage_members');
        // Capability and nonce are both enforced by the verify_ajax() call
        // above; the sniff cannot follow the indirection.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
        $member = OBRL_Members::get($member_id);
        if (!$member || !OBRL_Members::recalculate($member_id)) {
            wp_send_json_error(array('message' => __('Could not rebuild the balance.', 'obydullah-restaurant-loyalty')), 404);
        }
        OBRL_Logger::log('member_recalculated', array('member_id' => $member_id, 'object_type' => 'member', 'object_id' => $member_id));
        wp_send_json_success(OBRL_Members::get($member_id));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function ajax_reward_lookup()
    {
        $this->verify_ajax('orl_redeem_rewards', OBRL_NONCE_STAFF);
        // Capability and nonce are both enforced by the verify_ajax() call
        // above; the sniff cannot follow the indirection.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $reward = OBRL_Rewards::get(isset($_POST['reward_id']) ? absint($_POST['reward_id']) : 0);
        if (!$reward) {
            wp_send_json_error(array('message' => __('Reward not found.', 'obydullah-restaurant-loyalty')), 404);
        }
        $reward['stock_remaining'] = OBRL_Rewards::stock_remaining($reward);
        wp_send_json_success($reward);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /* ---------------------------------------------------------------------
     * Rendering
     * ------------------------------------------------------------------ */

    public function render_dashboard()
    {
        $view = OBRL_PATH . 'views/dashboard.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_members()
    {
        $view = OBRL_PATH . 'views/members.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_record()
    {
        $view = OBRL_PATH . 'views/record.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_redeem()
    {
        $view = OBRL_PATH . 'views/redeem.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_rewards()
    {
        $view = OBRL_PATH . 'views/rewards.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_reports()
    {
        $view = OBRL_PATH . 'views/reports.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_referrals()
    {
        $view = OBRL_PATH . 'views/referrals.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_activity()
    {
        $view = OBRL_PATH . 'views/activity.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function render_settings()
    {
        $view = OBRL_PATH . 'views/settings.php';
        if (is_readable($view)) {
            include $view;
        }
    }

    public function handle_export()
    {
        $this->verify('orl_export_csv');
        OBRL_CSV::handle_export();
    }

    private function first_error($error)
    {
        $message = $error->get_error_message();
        $errors = $error->get_error_data();
        if (is_array($errors) && $errors) {
            $message .= ' (' . implode('; ', array_map('sanitize_text_field', $errors)) . ')';
        }
        return $message;
    }
}
