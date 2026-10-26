<?php
if (!defined('ABSPATH')) {
    exit;
}

$obydrelo_referrals = OBYDRELO_Referrals::list_referrals(array('limit' => 100));
$obydrelo_statuses  = OBYDRELO_Referrals::statuses();
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Referrals', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<?php if (!OBYDRELO_Settings::get('referral_enabled')) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e('Referrals are currently disabled in Settings.', 'obydullah-restaurant-loyalty'); ?></p>
		</div>
	<?php endif; ?>

	<div class="obydrelo-panel">
		<h2><?php esc_html_e('All referrals', 'obydullah-restaurant-loyalty'); ?></h2>
		<?php if (!$obydrelo_referrals) : ?>
			<p class="obydrelo-empty"><?php esc_html_e('No referrals recorded yet.', 'obydullah-restaurant-loyalty'); ?></p>
		<?php else : ?>
			<table class="widefat striped obydrelo-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Referrer', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Referred member', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Status', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Qualifies on', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Reward', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('When', 'obydullah-restaurant-loyalty'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($obydrelo_referrals as $obydrelo_row) : ?>
					<tr>
						<td>
							<?php
							echo esc_html(
								trim($obydrelo_row->referrer_first . ' ' . $obydrelo_row->referrer_last)
									? trim($obydrelo_row->referrer_first . ' ' . $obydrelo_row->referrer_last)
									: __('(deleted)', 'obydullah-restaurant-loyalty')
							);
							?>
							<br><small class="obydrelo-muted"><code><?php echo esc_html($obydrelo_row->referrer_code); ?></code></small>
						</td>
						<td>
							<?php
							echo esc_html(
								trim($obydrelo_row->referred_first . ' ' . $obydrelo_row->referred_last)
									? trim($obydrelo_row->referred_first . ' ' . $obydrelo_row->referred_last)
									: __('(deleted)', 'obydullah-restaurant-loyalty')
							);
							?>
							<br><small class="obydrelo-muted"><code><?php echo esc_html($obydrelo_row->referred_code); ?></code></small>
						</td>
						<td><span class="obydrelo-status obydrelo-status--<?php echo esc_attr($obydrelo_row->status); ?>"><?php echo esc_html($obydrelo_statuses[$obydrelo_row->status] ?? $obydrelo_row->status); ?></span></td>
						<td><?php echo esc_html($obydrelo_row->qualification); ?></td>
						<td>
							<?php echo esc_html(OBYDRELO_Format::points($obydrelo_row->reward_referrer_points)); ?> /
							<?php echo esc_html(OBYDRELO_Format::points($obydrelo_row->reward_referred_points)); ?>
						</td>
						<td><?php echo esc_html(OBYDRELO_Format::datetime($obydrelo_row->created_at)); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
