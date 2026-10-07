<?php
if (!defined('ABSPATH')) {
    exit;
}

$obrl_definitions = OBRL_Settings::definitions();
$obrl_values      = OBRL_Settings::all();
$obrl_groups      = array(
    OBRL_Settings::GROUP_GENERAL  => __('General', 'obydullah-restaurant-loyalty'),
    OBRL_Settings::GROUP_PROGRAM  => __('Program', 'obydullah-restaurant-loyalty'),
    OBRL_Settings::GROUP_REWARDS  => __('Rewards', 'obydullah-restaurant-loyalty'),
    OBRL_Settings::GROUP_BIRTHDAY => __('Birthday & welcome', 'obydullah-restaurant-loyalty'),
    OBRL_Settings::GROUP_REFERRAL => __('Referrals', 'obydullah-restaurant-loyalty'),
    OBRL_Settings::GROUP_PRIVACY  => __('Privacy', 'obydullah-restaurant-loyalty'),
);

$obrl_labels = array(
    'program_mode'               => __('Program mode', 'obydullah-restaurant-loyalty'),
    'points_per_currency'        => __('Points per 1 unit of spend', 'obydullah-restaurant-loyalty'),
    'points_value'               => __('Currency value of one point', 'obydullah-restaurant-loyalty'),
    'currency_code'              => __('Currency code', 'obydullah-restaurant-loyalty'),
    'points_label'               => __('Points label', 'obydullah-restaurant-loyalty'),
    'stamps_label'               => __('Stamps label', 'obydullah-restaurant-loyalty'),
    'visits_label'               => __('Visits label', 'obydullah-restaurant-loyalty'),
    'reward_validity_days'       => __('Rewards valid for (days)', 'obydullah-restaurant-loyalty'),
    'expiry_grace_days'          => __('Grace days after expiry', 'obydullah-restaurant-loyalty'),
    'allow_negative_points'      => __('Allow negative balances', 'obydullah-restaurant-loyalty'),
    'redemption_min_points'      => __('Minimum points to redeem', 'obydullah-restaurant-loyalty'),
    'birthday_reward_enabled'    => __('Enable birthday reward', 'obydullah-restaurant-loyalty'),
    'birthday_reward_points'     => __('Birthday reward points', 'obydullah-restaurant-loyalty'),
    'birthday_reward_stamps'     => __('Birthday reward stamps', 'obydullah-restaurant-loyalty'),
    'birthday_reward_visits'     => __('Birthday reward visits', 'obydullah-restaurant-loyalty'),
    'birthday_reward_lead_days'  => __('Grant days before birthday', 'obydullah-restaurant-loyalty'),
    'welcome_reward_enabled'     => __('Enable welcome reward', 'obydullah-restaurant-loyalty'),
    'welcome_reward_points'      => __('Welcome reward points', 'obydullah-restaurant-loyalty'),
    'welcome_reward_stamps'      => __('Welcome reward stamps', 'obydullah-restaurant-loyalty'),
    'welcome_reward_visits'      => __('Welcome reward visits', 'obydullah-restaurant-loyalty'),
    'referral_enabled'           => __('Enable referrals', 'obydullah-restaurant-loyalty'),
    'referral_qualification'     => __('Referral qualifies on', 'obydullah-restaurant-loyalty'),
    'referral_points_referrer'   => __('Referrer receives points', 'obydullah-restaurant-loyalty'),
    'referral_points_referred'   => __('New member receives points', 'obydullah-restaurant-loyalty'),
    'member_code_prefix'         => __('Member code prefix', 'obydullah-restaurant-loyalty'),
    'allow_public_signup'        => __('Allow public portal signup', 'obydullah-restaurant-loyalty'),
    'require_email'              => __('Require email to enrol', 'obydullah-restaurant-loyalty'),
    'require_phone'              => __('Require phone to enrol', 'obydullah-restaurant-loyalty'),
    'delete_data_on_uninstall'   => __('Delete all data on uninstall', 'obydullah-restaurant-loyalty'),
);
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Loyalty Settings', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-form">
		<input type="hidden" name="action" value="orl_save_settings">
		<?php wp_nonce_field(OBRL_NONCE_ADMIN); ?>

		<?php foreach ($obrl_groups as $obrl_group_key => $obrl_group_label) : ?>
			<?php
			$obrl_group_fields = array();
			foreach ($obrl_definitions as $obrl_key => $obrl_def) {
				if ($obrl_def['group'] === $obrl_group_key) {
					$obrl_group_fields[$obrl_key] = $obrl_def;
				}
			}
			if (!$obrl_group_fields) {
				continue;
			}
			?>
			<div class="orl-panel">
				<h2><?php echo esc_html($obrl_group_label); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ($obrl_group_fields as $obrl_key => $obrl_def) : ?>
						<?php $obrl_value = $obrl_values[$obrl_key] ?? $obrl_def['value']; ?>
						<tr>
							<th scope="row">
								<label for="orl-set-<?php echo esc_attr($obrl_key); ?>">
									<?php echo esc_html($obrl_labels[$obrl_key] ?? $obrl_key); ?>
								</label>
							</th>
							<td>
								<?php if ('bool' === $obrl_def['type']) : ?>
									<label>
										<input type="checkbox" id="orl-set-<?php echo esc_attr($obrl_key); ?>"
											name="orl_settings[<?php echo esc_attr($obrl_key); ?>]" value="1"
											<?php checked((bool) $obrl_value); ?>>
										<?php esc_html_e('Enabled', 'obydullah-restaurant-loyalty'); ?>
									</label>
								<?php elseif ('select' === $obrl_def['type']) : ?>
									<select id="orl-set-<?php echo esc_attr($obrl_key); ?>"
										name="orl_settings[<?php echo esc_attr($obrl_key); ?>]">
										<?php foreach ($obrl_def['choices'] as $obrl_choice => $obrl_choice_label) : ?>
											<option value="<?php echo esc_attr($obrl_choice); ?>" <?php selected((string) $obrl_value, (string) $obrl_choice); ?>>
												<?php echo esc_html($obrl_choice_label); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php elseif ('decimal' === $obrl_def['type']) : ?>
									<input type="number" id="orl-set-<?php echo esc_attr($obrl_key); ?>" step="0.0001" min="0"
										class="regular-text"
										name="orl_settings[<?php echo esc_attr($obrl_key); ?>]"
										value="<?php echo esc_attr((string) $obrl_value); ?>">
								<?php elseif ('int' === $obrl_def['type']) : ?>
									<input type="number" id="orl-set-<?php echo esc_attr($obrl_key); ?>" step="1" min="0"
										class="regular-text"
										name="orl_settings[<?php echo esc_attr($obrl_key); ?>]"
										value="<?php echo esc_attr((string) $obrl_value); ?>">
								<?php else : ?>
									<input type="text" id="orl-set-<?php echo esc_attr($obrl_key); ?>" class="regular-text"
										name="orl_settings[<?php echo esc_attr($obrl_key); ?>]"
										value="<?php echo esc_attr((string) $obrl_value); ?>" maxlength="100">
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<p class="orl-submit">
			<button type="submit" class="button button-primary"><?php esc_html_e('Save settings', 'obydullah-restaurant-loyalty'); ?></button>
		</p>
	</form>
</div>
