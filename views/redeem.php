<?php
if (!defined('ABSPATH')) {
    exit;
}

$obrl_rewards    = OBRL_Rewards::list_rewards();
$obrl_redemptions = OBRL_Redemptions::list_redemptions(array('limit' => 25));
$obrl_statuses   = OBRL_Redemptions::statuses();
// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obrl_preset     = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
// phpcs:enable WordPress.Security.NonceVerification
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Redeem Reward', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-columns">
		<div class="orl-column orl-column--narrow">
			<div class="orl-panel">
				<h2><?php esc_html_e('Issue a reward', 'obydullah-restaurant-loyalty'); ?></h2>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-form" id="orl-redeem-form">
					<input type="hidden" name="action" value="orl_redeem">
					<?php wp_nonce_field(OBRL_NONCE_STAFF); ?>
					<input type="hidden" name="member_id" id="orl-member-id" value="0">

					<p>
						<label for="orl-member-search"><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-member-search" class="regular-text" autocomplete="off"
							data-preset-member="<?php echo esc_attr((string) $obrl_preset); ?>"
							placeholder="<?php esc_attr_e('Start typing a name or code', 'obydullah-restaurant-loyalty'); ?>">
						<div id="orl-member-results" class="orl-autocomplete" hidden></div>
						<div id="orl-member-selected" class="orl-selected" hidden></div>
					</p>

					<p>
						<label for="orl-reward-id"><?php esc_html_e('Reward', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="orl-reward-id" name="reward_id">
							<option value="0"><?php esc_html_e('Select a reward', 'obydullah-restaurant-loyalty'); ?></option>
							<?php foreach ($obrl_rewards as $obrl_reward) : ?>
								<option value="<?php echo esc_attr((string) $obrl_reward['id']); ?>"
									data-type="<?php echo esc_attr($obrl_reward['type']); ?>"
									<?php disabled(!$obrl_reward['is_active']); ?>>
									<?php
									echo esc_html(
										sprintf(
											'%s — %s',
											$obrl_reward['title'],
											OBRL_Rewards::stock_remaining($obrl_reward) === 0
												? __('out of stock', 'obydullah-restaurant-loyalty')
												: OBRL_Format::points($obrl_reward['points_cost']) . ' pts'
										)
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="orl-bill-amount"><?php esc_html_e('Bill amount', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="number" id="orl-bill-amount" name="bill_amount" step="0.01" min="0" class="regular-text" value="0">
						<span class="orl-help"><?php esc_html_e('Needed to value percentage rewards and check minimum spend.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>

					<p>
						<label for="orl-bill-reference"><?php esc_html_e('Bill / table reference', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-bill-reference" name="bill_reference" class="regular-text" maxlength="100">
					</p>

					<p>
						<label for="orl-external-id"><?php esc_html_e('Receipt reference', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-external-id" name="external_id" class="regular-text" maxlength="100">
						<span class="orl-help"><?php esc_html_e('Re-using a reference prevents the same reward being issued twice.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Redeem', 'obydullah-restaurant-loyalty'); ?></button>
					</p>
				</form>
			</div>
		</div>

		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('Recent redemptions', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_redemptions) : ?>
					<p class="orl-empty"><?php esc_html_e('No redemptions yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Code', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Cost', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Status', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Expires', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('When', 'obydullah-restaurant-loyalty'); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obrl_redemptions as $obrl_row) : ?>
							<tr>
								<td><code><?php echo esc_html($obrl_row->redemption_code); ?></code></td>
								<td>
									<?php echo esc_html(OBRL_Format::points($obrl_row->points_spent)); ?> pts
									<?php if ((int) $obrl_row->stamps_spent ) : ?>
										<br><small class="orl-muted"><?php echo esc_html((string) (int) $obrl_row->stamps_spent); ?> <?php esc_html_e('stamps', 'obydullah-restaurant-loyalty'); ?></small>
									<?php endif; ?>
								</td>
								<td><span class="orl-status orl-status--<?php echo esc_attr($obrl_row->status); ?>"><?php echo esc_html($obrl_statuses[$obrl_row->status] ?? $obrl_row->status); ?></span></td>
								<td><?php echo esc_html(OBRL_Format::date($obrl_row->expires_at)); ?></td>
								<td><?php echo esc_html(OBRL_Format::datetime($obrl_row->issued_at)); ?></td>
								<td>
									<?php if ('issued' === $obrl_row->status) : ?>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-inline-form"
											onsubmit="return confirm('<?php echo esc_js(__('Cancel this redemption and return the balance?', 'obydullah-restaurant-loyalty')); ?>');">
											<input type="hidden" name="action" value="orl_cancel_redemption">
											<?php wp_nonce_field(OBRL_NONCE_STAFF); ?>
											<input type="hidden" name="redemption_id" value="<?php echo esc_attr((string) $obrl_row->id); ?>">
											<input type="text" name="reason" placeholder="<?php esc_attr_e('Reason', 'obydullah-restaurant-loyalty'); ?>" class="orl-reason">
											<button type="submit" class="button button-small"><?php esc_html_e('Cancel', 'obydullah-restaurant-loyalty'); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
