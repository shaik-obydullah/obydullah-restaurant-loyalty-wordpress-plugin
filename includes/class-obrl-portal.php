<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Portal
{
    public function __construct()
    {
        add_shortcode('orl_member_portal', array($this, 'shortcode'));
        add_action('template_redirect', array($this, 'maybe_handle_card_scan'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue'));
        add_action('init', array($this, 'maybe_create_page'));
        add_action('admin_post_orl_portal_redeem', array($this, 'handle_redeem'));
        add_action('admin_post_orl_portal_profile', array($this, 'handle_profile'));
        add_filter('wp_robots', array($this, 'noindex_private_pages'));
    }

    public function enqueue()
    {
        if (!$this->is_portal_page()) {
            return;
        }
        wp_enqueue_style('orl-portal', OBRL_URL . 'assets/css/portal.css', array(), OBRL_VERSION);
        wp_enqueue_script('orl-qrcode', OBRL_URL . 'assets/js/vendor/qrcode-generator.js', array(), '1.4.4', true);
        wp_enqueue_script('orl-portal', OBRL_URL . 'assets/js/portal.js', array('jquery'), OBRL_VERSION, true);
    }

    /**
     * Keeps member-only surfaces out of search results: the portal page, which
     * renders a member's balance, and the card scan endpoint, whose URL carries
     * a live member token.
     */
    public function noindex_private_pages($robots)
    {
        if (!is_array($robots)) {
            return $robots;
        }

        $is_card_scan = isset($_GET['orl_card']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($this->is_portal_page() || $is_card_scan) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
            unset($robots['index'], $robots['follow']);
        }

        return $robots;
    }

    private function is_portal_page()
    {
        global $post;
        return $post instanceof WP_Post && has_shortcode((string) $post->post_content, 'orl_member_portal');
    }

    /**
     * A member card QR resolves to home_url('/?orl_card=TOKEN'). Only staff
     * who can record transactions get the lookup result; everyone else is
     * redirected home so card tokens never leak to the public.
     */
    public function maybe_handle_card_scan()
    {
        // Read-only trigger: an unauthenticated or unauthorised visitor is
        // redirected before the token is used for anything.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['orl_card'])) {
            return;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (!is_user_logged_in() || !current_user_can('orl_record_transactions')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $member = OBRL_Card::lookup_from_request();
        if (!$member) {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'       => 'orl-record',
                        'orl_error'  => __('That member card could not be found.', 'obydullah-restaurant-loyalty'),
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }

        OBRL_Logger::log(
            'member_card_scanned',
            array(
                'member_id'   => $member['id'],
                'object_type' => 'member',
                'object_id'   => $member['id'],
            )
        );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'       => 'orl-record',
                    'member_id'  => $member['id'],
                    'orl_notice' => sprintf(
                        /* translators: %s: member name. */
                        __('Card scanned: %s', 'obydullah-restaurant-loyalty'),
                        OBRL_Members::full_name($member)
                    ),
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Resolves the member record for the current viewer. Members are matched
     * by linked user id first, then by verified email on their WP account.
     */
    public function current_member()
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user_id = get_current_user_id();
        $member = OBRL_Members::get_by_user_id($user_id);
        if ($member) {
            return $member;
        }

        $user = wp_get_current_user();
        if (!$user || !is_email($user->user_email)) {
            return null;
        }

        $member = OBRL_Members::get_by('email', $user->user_email);
        if (!$member) {
            return null;
        }

        // First portal visit links the account so future logins are direct.
        global $wpdb;
        $table = OBRL_Members::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the current member and their own balance immediately before a redemption is attempted.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET user_id = %d, updated_at = %s WHERE id = %d AND user_id IS NULL',
                $table,
                $user_id,
                current_time('mysql', true),
                $member['id']
            )
        );
        update_user_meta($user_id, '_orl_member_id', $member['id']);
        update_user_meta($user_id, '_orl_created_by_plugin', '1');

        OBRL_Logger::log('member_portal_linked', array('member_id' => $member['id'], 'object_type' => 'member', 'object_id' => $member['id']));

        return OBRL_Members::get($member['id']);
    }

    /**
     * Stores a message for the portal and returns the token that reads it back
     * exactly once. Mirrors the admin flash so neither surface reflects
     * untrusted text through the redirect URL.
     */
    private function flash($type, $text)
    {
        $token = sanitize_key(wp_generate_password(12, false, false));
        set_transient(
            'orl_portal_flash_' . $token,
            array(
                'type' => 'error' === $type ? 'error' : 'success',
                'text' => (string) $text,
            ),
            5 * MINUTE_IN_SECONDS
        );
        return $token;
    }

    /**
     * Consumes any pending portal flash message and returns it as a list of
     * array( type, text ) pairs, already localised but not escaped.
     */
    private function consume_flash()
    {
        // Read-only display parameter: the token is a transient lookup key and
        // nothing is emitted unless a message was stored for it.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['orl_msg'])) {
            return array();
        }
        $token = sanitize_key(wp_unslash($_GET['orl_msg']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ('' === $token) {
            return array();
        }

        $flash = get_transient('orl_portal_flash_' . $token);
        if (!is_array($flash) || empty($flash['text'])) {
            return array();
        }
        delete_transient('orl_portal_flash_' . $token);

        return array(array($flash['type'], $flash['text']));
    }

    private function portal_url($token = '')
    {
        $page_id = (int) get_option('orl_portal_page_id');
        $base    = $page_id ? get_permalink($page_id) : home_url('/');
        if (!$base) {
            $base = home_url('/');
        }

        if ($token) {
            $base = add_query_arg('orl_msg', rawurlencode($token), $base);
        }

        return $base;
    }

    private function redirect_with_flash($type, $text)
    {
        wp_safe_redirect($this->portal_url($this->flash($type, $text)));
        exit;
    }

    /**
     * Member initiated redemption. A claim debits the balance and reserves
     * stock straight away, exactly like a staff redemption, and issues a code
     * the member presents at the counter. All eligibility rules still run
     * inside OBRL_Redemptions::issue() so the portal cannot bypass them.
     */
    public function handle_redeem()
    {
        check_admin_referer(OBRL_NONCE_PORTAL);

        $member = $this->current_member();
        if (!$member) {
            $this->redirect_with_flash(
                'error',
                __('We could not find a loyalty account for this login.', 'obydullah-restaurant-loyalty')
            );
        }

        if (!OBRL_Settings::get('allow_portal_redeem')) {
            $this->redirect_with_flash(
                'error',
                __('Self-service redemption is currently turned off. Please ask a staff member to redeem your reward.', 'obydullah-restaurant-loyalty')
            );
        }

        $reward_id = isset($_POST['reward_id']) ? absint($_POST['reward_id']) : 0;
        $reward    = OBRL_Rewards::get($reward_id);
        if (!$reward) {
            $this->redirect_with_flash('error', __('That reward no longer exists.', 'obydullah-restaurant-loyalty'));
        }

        $result = OBRL_Redemptions::issue(
            array(
                'member_id'     => $member['id'],
                'reward_id'     => $reward['id'],
                // The claim is presented at the counter, so the bill minimum is
                // treated as met. It is re-checked against the real bill if
                // staff re-verify the redemption.
                'bill_amount'   => (float) $reward['min_purchase'],
                'source'        => 'portal',
                'external_id'   => 'portal-' . wp_generate_password(12, false),
                'note'          => __('Claimed by the member in the portal.', 'obydullah-restaurant-loyalty'),
            )
        );

        if (is_wp_error($result)) {
            $this->redirect_with_flash('error', $result->get_error_message());
        }

        $redemption = OBRL_Redemptions::get($result);
        $this->redirect_with_flash(
            'success',
            sprintf(
                /* translators: %s: redemption code. */
                __('Reward claimed. Show code %s at the counter to collect it.', 'obydullah-restaurant-loyalty'),
                $redemption ? $redemption['redemption_code'] : ''
            )
        );
    }

    /**
     * Builds the member update payload for a self-service edit. Only the
     * contact fields the visitor actually sent are taken; status, notes, and
     * the email that links the account are always carried over from the
     * stored record so a crafted POST cannot escalate or re-link it.
     *
     * @param array $member Stored member record.
     * @param array $input  Raw submitted fields.
     */
    private function merge_profile($member, $input)
    {
        $pick = function ($key, $fallback) use ($input) {
            // A submitted array is a malformed field rather than a value.
            // Casting one to a string yields the literal "Array", which is not
            // empty and would therefore be saved as the member's own name.
            if (!isset($input[$key]) || !is_scalar($input[$key])) {
                return $fallback;
            }
            return sanitize_text_field(wp_unslash((string) $input[$key]));
        };

        return array(
            'first_name' => $pick('first_name', $member['first_name']),
            'last_name'  => $pick('last_name', $member['last_name']),
            'phone'      => $pick('phone', $member['phone']),
            'birthday'   => $pick('birthday', $member['birthday']),
            // Never writable from the portal.
            'email'      => $member['email'],
            'status'     => $member['status'],
            'notes'      => $member['notes'],
        );
    }

    /**
     * Member initiated profile edit. Only contact details and birthday are
     * writable from the portal; status, notes, and the email that links the
     * account stay under staff control.
     */
    public function handle_profile()
    {
        check_admin_referer(OBRL_NONCE_PORTAL);

        $member = $this->current_member();
        if (!$member) {
            $this->redirect_with_flash(
                'error',
                __('We could not find a loyalty account for this login.', 'obydullah-restaurant-loyalty')
            );
        }

        if (!OBRL_Settings::get('allow_portal_profile_edit')) {
            $this->redirect_with_flash(
                'error',
                __('Profile editing is currently turned off. Please ask a staff member to update your details.', 'obydullah-restaurant-loyalty')
            );
        }

        $updated = OBRL_Members::update(
            $member['id'],
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() above.
            $this->merge_profile($member, $_POST)
        );

        if (is_wp_error($updated)) {
            $this->redirect_with_flash('error', $updated->get_error_message());
        }

        $this->redirect_with_flash('success', __('Your details have been updated.', 'obydullah-restaurant-loyalty'));
    }

    public function shortcode($atts = array())
    {
        $atts = shortcode_atts(array('show' => 'all'), $atts, 'orl_member_portal');

        if (!is_user_logged_in()) {
            return $this->login_prompt();
        }

        $member = $this->current_member();
        if (!$member) {
            return $this->no_record_notice();
        }

        if ('card' === $atts['show']) {
            return $this->render_card($member);
        }

        return $this->render_dashboard($member);
    }

    private function login_prompt()
    {
        ob_start();
        ?>
		<div class="orl-portal orl-portal--guest">
			<h2><?php esc_html_e('Your loyalty account', 'obydullah-restaurant-loyalty'); ?></h2>
			<p><?php esc_html_e('Sign in to see your balance, history, and rewards.', 'obydullah-restaurant-loyalty'); ?></p>
			<a class="orl-btn orl-btn--primary" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
				<?php esc_html_e('Sign in', 'obydullah-restaurant-loyalty'); ?>
			</a>
		</div>
		<?php
        return (string) ob_get_clean();
    }

    private function no_record_notice()
    {
        ob_start();
        ?>
		<div class="orl-portal orl-portal--empty">
			<h2><?php esc_html_e('No loyalty account found', 'obydullah-restaurant-loyalty'); ?></h2>
			<p>
				<?php esc_html_e('This login is not linked to a member record yet. Ask a staff member to enrol you or to link this email address.', 'obydullah-restaurant-loyalty'); ?>
			</p>
		</div>
		<?php
        return (string) ob_get_clean();
    }

    private function render_card($member)
    {
        $url = OBRL_Card::member_url($member);
        ob_start();
        ?>
		<div class="orl-portal orl-portal--card" data-qr-text="<?php echo esc_attr($url ? $url : $member['member_code']); ?>">
			<div class="orl-portal__qr" aria-hidden="true"></div>
			<div class="orl-portal__details">
				<h2><?php echo esc_html(OBRL_Members::full_name($member)); ?></h2>
				<p class="orl-portal__code"><?php echo esc_html($member['member_code']); ?></p>
				<ul class="orl-portal__stats">
					<li><span><?php echo esc_html(OBRL_Format::points($member['points_balance'])); ?></span><small><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></small></li>
					<li><span><?php echo esc_html((string) $member['stamps_count']); ?></span><small><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></small></li>
					<li><span><?php echo esc_html((string) $member['visits_count']); ?></span><small><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></small></li>
				</ul>
				<p class="orl-portal__hint"><?php esc_html_e('Show this code at the counter to collect your reward.', 'obydullah-restaurant-loyalty'); ?></p>
			</div>
		</div>
		<?php
        return (string) ob_get_clean();
    }

    private function render_dashboard($member)
    {
        $history    = OBRL_Ledger::history(array('member_id' => $member['id'], 'per_page' => 15));
        $rewards    = OBRL_Rewards::list_rewards(array('active_only' => true));
        $referrals  = OBRL_Referrals::summary($member['id']);
        $types      = OBRL_Ledger::types();
        $card_url   = OBRL_Card::member_url($member);
        $flashes    = $this->consume_flash();
        $can_redeem = (bool) OBRL_Settings::get('allow_portal_redeem');
        $can_edit   = (bool) OBRL_Settings::get('allow_portal_profile_edit');

        ob_start();
        ?>
		<div class="orl-portal">
			<?php foreach ($flashes as $flash) : ?>
				<div class="orl-portal__flash orl-portal__flash--<?php echo esc_attr($flash[0]); ?>">
					<?php echo esc_html($flash[1]); ?>
				</div>
			<?php endforeach; ?>
			<div class="orl-portal__head">
				<div class="orl-portal__qr orl-portal__qr--small" data-qr-text="<?php echo esc_attr($card_url ? $card_url : $member['member_code']); ?>" aria-hidden="true"></div>
				<div>
					<h2><?php echo esc_html(OBRL_Members::full_name($member)); ?></h2>
					<p class="orl-portal__code"><?php echo esc_html($member['member_code']); ?></p>
					<p class="orl-portal__member"><?php esc_html_e('Member since', 'obydullah-restaurant-loyalty'); ?> <?php echo esc_html(OBRL_Format::date($member['joined_at'])); ?></p>
				</div>
			</div>

			<ul class="orl-portal__stats orl-portal__stats--wide">
				<li><span><?php echo esc_html(OBRL_Format::points($member['points_balance'])); ?></span><small><?php esc_html_e('Points balance', 'obydullah-restaurant-loyalty'); ?></small></li>
				<li><span><?php echo esc_html((string) $member['stamps_count']); ?></span><small><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></small></li>
				<li><span><?php echo esc_html((string) $member['visits_count']); ?></span><small><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></small></li>
				<li><span><?php echo esc_html((string) $referrals['invited']); ?></span><small><?php esc_html_e('Friends invited', 'obydullah-restaurant-loyalty'); ?></small></li>
			</ul>

			<?php if ($referrals['invited'] > 0) : ?>
				<p class="orl-portal__referral">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: referral code, 2: invited friend count. */
							__('Share your referral code %1$s — %2$d friend(s) joined so far.', 'obydullah-restaurant-loyalty'),
							$member['referral_code'],
							$referrals['invited']
						)
					);
					?>
				</p>
			<?php endif; ?>

			<h3><?php esc_html_e('Available rewards', 'obydullah-restaurant-loyalty'); ?></h3>
			<?php if (!$rewards) : ?>
				<p class="orl-empty"><?php esc_html_e('No rewards are available right now.', 'obydullah-restaurant-loyalty'); ?></p>
			<?php else : ?>
				<ul class="orl-portal__rewards">
					<?php foreach ($rewards as $reward) : ?>
						<?php $check = OBRL_Rewards::can_redeem($member, $reward, (float) $reward['min_purchase']); ?>
						<li class="orl-portal__reward<?php echo is_wp_error($check) ? ' orl-portal__reward--locked' : ''; ?>">
							<div>
								<strong><?php echo esc_html($reward['title']); ?></strong>
								<?php if ($reward['description']) : ?>
									<small><?php echo esc_html($reward['description']); ?></small>
								<?php endif; ?>
							</div>
							<div class="orl-portal__reward-meta">
								<span class="orl-portal__cost">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: point cost. */
											'%s pts',
											OBRL_Format::points($reward['points_cost'])
										)
									);
									?>
								</span>
						<?php if (is_wp_error($check)) : ?>
							<small class="orl-portal__why"><?php echo esc_html($check->get_error_message()); ?></small>
						<?php elseif ($can_redeem) : ?>
							<small class="orl-portal__ok"><?php esc_html_e('Available', 'obydullah-restaurant-loyalty'); ?></small>
							<form class="orl-portal__claim" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<input type="hidden" name="action" value="orl_portal_redeem">
								<input type="hidden" name="reward_id" value="<?php echo esc_attr((string) $reward['id']); ?>">
								<?php wp_nonce_field(OBRL_NONCE_PORTAL); ?>
								<button type="submit" class="orl-btn orl-btn--small">
									<?php esc_html_e('Claim', 'obydullah-restaurant-loyalty'); ?>
								</button>
							</form>
						<?php else : ?>
							<small class="orl-portal__ok"><?php esc_html_e('Ask at the counter', 'obydullah-restaurant-loyalty'); ?></small>
						<?php endif; ?>
					</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ($can_redeem) : ?>
				<p class="orl-portal__hint"><?php esc_html_e('Claiming reserves the reward and debits your balance straight away. Show the code at the counter to collect it.', 'obydullah-restaurant-loyalty'); ?></p>
			<?php else : ?>
				<p class="orl-portal__hint"><?php esc_html_e('Ask a staff member at the counter to redeem a reward.', 'obydullah-restaurant-loyalty'); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ($can_edit) : ?>
			<h3><?php esc_html_e('Your details', 'obydullah-restaurant-loyalty'); ?></h3>
			<form class="orl-portal__profile" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="orl_portal_profile">
				<?php wp_nonce_field(OBRL_NONCE_PORTAL); ?>
				<p>
					<label for="orl-p-first"><?php esc_html_e('First name', 'obydullah-restaurant-loyalty'); ?></label>
					<input type="text" id="orl-p-first" name="first_name" value="<?php echo esc_attr($member['first_name']); ?>" required>
				</p>
				<p>
					<label for="orl-p-last"><?php esc_html_e('Last name', 'obydullah-restaurant-loyalty'); ?></label>
					<input type="text" id="orl-p-last" name="last_name" value="<?php echo esc_attr($member['last_name']); ?>">
				</p>
				<p>
					<label for="orl-p-phone"><?php esc_html_e('Phone', 'obydullah-restaurant-loyalty'); ?></label>
					<input type="text" id="orl-p-phone" name="phone" value="<?php echo esc_attr($member['phone']); ?>">
				</p>
				<p>
					<label for="orl-p-bday"><?php esc_html_e('Birthday', 'obydullah-restaurant-loyalty'); ?></label>
					<input type="date" id="orl-p-bday" name="birthday" value="<?php echo esc_attr($member['birthday']); ?>">
				</p>
				<p class="orl-portal__locked">
					<?php esc_html_e('Email and account status are managed by the restaurant.', 'obydullah-restaurant-loyalty'); ?>
				</p>
				<p><button type="submit" class="orl-btn orl-btn--primary"><?php esc_html_e('Save details', 'obydullah-restaurant-loyalty'); ?></button></p>
			</form>
		<?php endif; ?>

			<h3><?php esc_html_e('Recent activity', 'obydullah-restaurant-loyalty'); ?></h3>
			<?php if (!$history['items']) : ?>
				<p class="orl-empty"><?php esc_html_e('No activity yet.', 'obydullah-restaurant-loyalty'); ?></p>
			<?php else : ?>
				<table class="orl-portal__history">
					<thead>
						<tr>
							<th><?php esc_html_e('Date', 'obydullah-restaurant-loyalty'); ?></th>
							<th><?php esc_html_e('Activity', 'obydullah-restaurant-loyalty'); ?></th>
							<th><?php esc_html_e('Change', 'obydullah-restaurant-loyalty'); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($history['items'] as $row) : ?>
						<tr>
							<td><?php echo esc_html(OBRL_Format::date($row->created_at)); ?></td>
							<td><?php echo esc_html($types[$row->type] ?? $row->type); ?></td>
							<td>
								<?php if ((float) $row->points) : ?>
									<?php echo esc_html(OBRL_Format::signed_points($row->points)); ?>
								<?php elseif ((int) $row->stamps ) : ?>
									<?php echo esc_html(sprintf('%+d stamps', (int) $row->stamps)); ?>
								<?php elseif ((int) $row->visits ) : ?>
									<?php echo esc_html(sprintf('%+d visits', (int) $row->visits)); ?>
								<?php else : ?>
									<?php echo esc_html(OBRL_Format::money($row->amount)); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
        return (string) ob_get_clean();
    }

    /**
     * Creates a dedicated portal page on activation so the feature is usable
     * without manual setup.
     */
    public function maybe_create_page()
    {
        if (get_option('orl_portal_page_id')) {
            return;
        }
        if (!OBRL_Schema::is_installed()) {
            return;
        }

        $existing = get_page_by_path('loyalty');
        if ($existing) {
            update_option('orl_portal_page_id', $existing->ID);
            return;
        }

        $page_id = wp_insert_post(
            array(
                'post_title'     => __('My Loyalty Account', 'obydullah-restaurant-loyalty'),
                'post_name'      => 'loyalty',
                'post_content'   => '[orl_member_portal]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            )
        );

        if ($page_id && !is_wp_error($page_id)) {
            update_option('orl_portal_page_id', $page_id);
        }
    }
}
