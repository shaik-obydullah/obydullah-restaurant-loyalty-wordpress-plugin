<?php
if (!defined('ABSPATH')) {
    exit;
}

$obydrelo_rewards    = OBYDRELO_Rewards::list_rewards();
$obydrelo_redemptions = OBYDRELO_Redemptions::list_redemptions(array('limit' => 25));
$obydrelo_statuses   = OBYDRELO_Redemptions::statuses();
// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obydrelo_preset     = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
// phpcs:enable WordPress.Security.NonceVerification
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Redeem Reward', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-columns">
		<div class="obydrelo-column obydrelo-column--narrow">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Issue a reward', 'obydullah-restaurant-loyalty'); ?></h2>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="obydrelo-form" id="obydrelo-redeem-form">
					<input type="hidden" name="action" value="obydrelo_redeem">
					<?php wp_nonce_field(OBYDRELO_NONCE_STAFF); ?>
					<input type="hidden" name="member_id" id="obydrelo-member-id" value="0">

					<p>
						<label for="obydrelo-member-search"><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-member-search" class="regular-text" autocomplete="off"
							data-preset-member="<?php echo esc_attr((string) $obydrelo_preset); ?>"
							placeholder="<?php esc_attr_e('Start typing a name or code', 'obydullah-restaurant-loyalty'); ?>">
						<div id="obydrelo-member-results" class="obydrelo-autocomplete" hidden></div>
						<div id="obydrelo-member-selected" class="obydrelo-selected" hidden></div>
					</p>

					<p>
						<label for="obydrelo-reward-id"><?php esc_html_e('Reward', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="obydrelo-reward-id" name="reward_id">
							<option value="0"><?php esc_html_e('Select a reward', 'obydullah-restaurant-loyalty'); ?></option>
							<?php foreach ($obydrelo_rewards as $obydrelo_reward) : ?>
								<option value="<?php echo esc_attr((string) $obydrelo_reward['id']); ?>"
									data-type="<?php echo esc_attr($obydrelo_reward['type']); ?>"
									<?php disabled(!$obydrelo_reward['is_active']); ?>>
									<?php
									echo esc_html(
										sprintf(
											'%s — %s',
											$obydrelo_reward['title'],
											OBYDRELO_Rewards::stock_remaining($obydrelo_reward) === 0
												? __('out of stock', 'obydullah-restaurant-loyalty')
												: OBYDRELO_Format::points($obydrelo_reward['points_cost']) . ' pts'
										)
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="obydrelo-bill-amount"><?php esc_html_e('Bill amount', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="number" id="obydrelo-bill-amount" name="bill_amount" step="0.01" min="0" class="regular-text" value="0">
						<span class="obydrelo-help"><?php esc_html_e('Needed to value percentage rewards and check minimum spend.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>

					<p>
						<label for="obydrelo-bill-reference"><?php esc_html_e('Bill / table reference', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-bill-reference" name="bill_reference" class="regular-text" maxlength="100">
					</p>

					<p>
						<label for="obydrelo-external-id"><?php esc_html_e('Receipt reference', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-external-id" name="external_id" class="regular-text" maxlength="100">
						<span class="obydrelo-help"><?php esc_html_e('Re-using a reference prevents the same reward being issued twice.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Redeem', 'obydullah-restaurant-loyalty'); ?></button>
					</p>
				</form>
			</div>
		</div>

		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Recent redemptions', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obydrelo_redemptions) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No redemptions yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
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
						<?php foreach ($obydrelo_redemptions as $obydrelo_row) : ?>
							<tr>
								<td><code><?php echo esc_html($obydrelo_row->redemption_code); ?></code></td>
								<td>
									<?php echo esc_html(OBYDRELO_Format::points($obydrelo_row->points_spent)); ?> pts
									<?php if ((int) $obydrelo_row->stamps_spent ) : ?>
										<br><small class="obydrelo-muted"><?php echo esc_html((string) (int) $obydrelo_row->stamps_spent); ?> <?php esc_html_e('stamps', 'obydullah-restaurant-loyalty'); ?></small>
									<?php endif; ?>
								</td>
								<td><span class="obydrelo-status obydrelo-status--<?php echo esc_attr($obydrelo_row->status); ?>"><?php echo esc_html($obydrelo_statuses[$obydrelo_row->status] ?? $obydrelo_row->status); ?></span></td>
								<td><?php echo esc_html(OBYDRELO_Format::date($obydrelo_row->expires_at)); ?></td>
								<td><?php echo esc_html(OBYDRELO_Format::datetime($obydrelo_row->issued_at)); ?></td>
								<td>
									<?php if ('issued' === $obydrelo_row->status) : ?>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="obydrelo-inline-form"
											onsubmit="return confirm('<?php echo esc_js(__('Cancel this redemption and return the balance?', 'obydullah-restaurant-loyalty')); ?>');">
											<input type="hidden" name="action" value="obydrelo_cancel_redemption">
											<?php wp_nonce_field(OBYDRELO_NONCE_STAFF); ?>
											<input type="hidden" name="redemption_id" value="<?php echo esc_attr((string) $obydrelo_row->id); ?>">
											<input type="text" name="reason" placeholder="<?php esc_attr_e('Reason', 'obydullah-restaurant-loyalty'); ?>" class="obydrelo-reason">
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
