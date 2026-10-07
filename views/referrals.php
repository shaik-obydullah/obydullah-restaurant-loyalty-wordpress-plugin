<?php
if (!defined('ABSPATH')) {
    exit;
}

$obrl_referrals = OBRL_Referrals::list_referrals(array('limit' => 100));
$obrl_statuses  = OBRL_Referrals::statuses();
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Referrals', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<?php if (!OBRL_Settings::get('referral_enabled')) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e('Referrals are currently disabled in Settings.', 'obydullah-restaurant-loyalty'); ?></p>
		</div>
	<?php endif; ?>

	<div class="orl-panel">
		<h2><?php esc_html_e('All referrals', 'obydullah-restaurant-loyalty'); ?></h2>
		<?php if (!$obrl_referrals) : ?>
			<p class="orl-empty"><?php esc_html_e('No referrals recorded yet.', 'obydullah-restaurant-loyalty'); ?></p>
		<?php else : ?>
			<table class="widefat striped orl-table">
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
				<?php foreach ($obrl_referrals as $obrl_row) : ?>
					<tr>
						<td>
							<?php
							echo esc_html(
								trim($obrl_row->referrer_first . ' ' . $obrl_row->referrer_last)
									? trim($obrl_row->referrer_first . ' ' . $obrl_row->referrer_last)
									: __('(deleted)', 'obydullah-restaurant-loyalty')
							);
							?>
							<br><small class="orl-muted"><code><?php echo esc_html($obrl_row->referrer_code); ?></code></small>
						</td>
						<td>
							<?php
							echo esc_html(
								trim($obrl_row->referred_first . ' ' . $obrl_row->referred_last)
									? trim($obrl_row->referred_first . ' ' . $obrl_row->referred_last)
									: __('(deleted)', 'obydullah-restaurant-loyalty')
							);
							?>
							<br><small class="orl-muted"><code><?php echo esc_html($obrl_row->referred_code); ?></code></small>
						</td>
						<td><span class="orl-status orl-status--<?php echo esc_attr($obrl_row->status); ?>"><?php echo esc_html($obrl_statuses[$obrl_row->status] ?? $obrl_row->status); ?></span></td>
						<td><?php echo esc_html($obrl_row->qualification); ?></td>
						<td>
							<?php echo esc_html(OBRL_Format::points($obrl_row->reward_referrer_points)); ?> /
							<?php echo esc_html(OBRL_Format::points($obrl_row->reward_referred_points)); ?>
						</td>
						<td><?php echo esc_html(OBRL_Format::datetime($obrl_row->created_at)); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
