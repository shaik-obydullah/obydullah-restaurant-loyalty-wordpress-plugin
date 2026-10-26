<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obydrelo_range   = isset($_GET['range']) ? sanitize_text_field(wp_unslash($_GET['range'])) : '30';
// phpcs:enable WordPress.Security.NonceVerification
$obydrelo_range   = in_array($obydrelo_range, array('7', '30', '90', '365'), true) ? $obydrelo_range : '30';
$obydrelo_report  = OBYDRELO_Reports::build($obydrelo_range);
$obydrelo_csv_url = wp_nonce_url(
    admin_url('admin-post.php?action=obydrelo_export&report=' . esc_attr($obydrelo_report['range'])),
    OBYDRELO_NONCE_ADMIN
);
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Reports', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-toolbar">
		<form method="get">
			<input type="hidden" name="page" value="obydrelo-reports">
			<label for="obydrelo-range"><?php esc_html_e('Period', 'obydullah-restaurant-loyalty'); ?></label>
			<select id="obydrelo-range" name="range">
				<?php
				$obydrelo_ranges = array(
					'7'   => __('Last 7 days', 'obydullah-restaurant-loyalty'),
					'30'  => __('Last 30 days', 'obydullah-restaurant-loyalty'),
					'90'  => __('Last 90 days', 'obydullah-restaurant-loyalty'),
					'365' => __('Last 12 months', 'obydullah-restaurant-loyalty'),
				);
				foreach ($obydrelo_ranges as $obydrelo_rk => $obydrelo_rl) :
					?>
					<option value="<?php echo esc_attr($obydrelo_rk); ?>" <?php selected($obydrelo_range, $obydrelo_rk); ?>>
						<?php echo esc_html($obydrelo_rl); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e('Apply', 'obydullah-restaurant-loyalty'); ?></button>
		</form>
		<a class="button button-primary" href="<?php echo esc_url($obydrelo_csv_url); ?>">
			<?php esc_html_e('Export CSV', 'obydullah-restaurant-loyalty'); ?>
		</a>
	</div>

	<div class="obydrelo-stat-grid">
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::money($obydrelo_report['revenue'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Recorded spend', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($obydrelo_report['transactions'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Transactions', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($obydrelo_report['new_members'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('New members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::points($obydrelo_report['points_earned'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Points earned', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::points(abs($obydrelo_report['points_redeemed']))); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Points redeemed', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($obydrelo_report['redemptions'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Redemptions', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
	</div>

	<div class="obydrelo-columns">
		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Top members by spend', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obydrelo_report['top_members']) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No spend recorded in this period.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Code', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Spend', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obydrelo_report['top_members'] as $obydrelo_row) : ?>
							<tr>
								<td><?php echo esc_html($obydrelo_row->name); ?></td>
								<td><code><?php echo esc_html($obydrelo_row->member_code); ?></code></td>
								<td><?php echo esc_html(OBYDRELO_Format::money($obydrelo_row->total_spend)); ?></td>
								<td><?php echo esc_html(number_format_i18n((int) $obydrelo_row->visits)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Reward usage', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obydrelo_report['reward_usage']) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No rewards redeemed in this period.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Reward', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Redemptions', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Value given', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obydrelo_report['reward_usage'] as $obydrelo_row) : ?>
							<tr>
								<td><?php echo esc_html($obydrelo_row->title); ?></td>
								<td><?php echo esc_html(number_format_i18n((int) $obydrelo_row->count)); ?></td>
								<td><?php echo esc_html(OBYDRELO_Format::money($obydrelo_row->value)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Daily activity', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php
				$obydrelo_max = $obydrelo_report['max_daily'] > 0 ? $obydrelo_report['max_daily'] : 1;
				?>
				<ul class="obydrelo-bars">
					<?php foreach ($obydrelo_report['daily'] as $obydrelo_day) : ?>
						<li>
							<span class="obydrelo-bars__label"><?php echo esc_html($obydrelo_day->day); ?></span>
							<span class="obydrelo-bars__track">
								<span class="obydrelo-bars__fill" style="width: <?php echo esc_attr((string) round(((int) $obydrelo_day->count / $obydrelo_max) * 100, 1)); ?>%"></span>
							</span>
							<span class="obydrelo-bars__value"><?php echo esc_html(number_format_i18n((int) $obydrelo_day->count)); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
