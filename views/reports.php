<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$bobydrelo_range   = isset($_GET['range']) ? sanitize_text_field(wp_unslash($_GET['range'])) : '30';
// phpcs:enable WordPress.Security.NonceVerification
$bobydrelo_range   = in_array($bobydrelo_range, array('7', '30', '90', '365'), true) ? $bobydrelo_range : '30';
$bobydrelo_report  = OBYDRELO_Reports::build($bobydrelo_range);
$bobydrelo_csv_url = wp_nonce_url(
    admin_url('admin-post.php?action=obydrelo_export&report=' . esc_attr($bobydrelo_report['range'])),
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
				$bobydrelo_ranges = array(
					'7'   => __('Last 7 days', 'obydullah-restaurant-loyalty'),
					'30'  => __('Last 30 days', 'obydullah-restaurant-loyalty'),
					'90'  => __('Last 90 days', 'obydullah-restaurant-loyalty'),
					'365' => __('Last 12 months', 'obydullah-restaurant-loyalty'),
				);
				foreach ($bobydrelo_ranges as $bobydrelo_rk => $bobydrelo_rl) :
					?>
					<option value="<?php echo esc_attr($bobydrelo_rk); ?>" <?php selected($bobydrelo_range, $bobydrelo_rk); ?>>
						<?php echo esc_html($bobydrelo_rl); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e('Apply', 'obydullah-restaurant-loyalty'); ?></button>
		</form>
		<a class="button button-primary" href="<?php echo esc_url($bobydrelo_csv_url); ?>">
			<?php esc_html_e('Export CSV', 'obydullah-restaurant-loyalty'); ?>
		</a>
	</div>

	<div class="obydrelo-stat-grid">
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::money($bobydrelo_report['revenue'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Recorded spend', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($bobydrelo_report['transactions'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Transactions', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($bobydrelo_report['new_members'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('New members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::points($bobydrelo_report['points_earned'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Points earned', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::points(abs($bobydrelo_report['points_redeemed']))); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Points redeemed', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($bobydrelo_report['redemptions'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Redemptions', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
	</div>

	<div class="obydrelo-columns">
		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Top members by spend', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$bobydrelo_report['top_members']) : ?>
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
						<?php foreach ($bobydrelo_report['top_members'] as $bobydrelo_row) : ?>
							<tr>
								<td><?php echo esc_html($bobydrelo_row->name); ?></td>
								<td><code><?php echo esc_html($bobydrelo_row->member_code); ?></code></td>
								<td><?php echo esc_html(OBYDRELO_Format::money($bobydrelo_row->total_spend)); ?></td>
								<td><?php echo esc_html(number_format_i18n((int) $bobydrelo_row->visits)); ?></td>
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
				<?php if (!$bobydrelo_report['reward_usage']) : ?>
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
						<?php foreach ($bobydrelo_report['reward_usage'] as $bobydrelo_row) : ?>
							<tr>
								<td><?php echo esc_html($bobydrelo_row->title); ?></td>
								<td><?php echo esc_html(number_format_i18n((int) $bobydrelo_row->count)); ?></td>
								<td><?php echo esc_html(OBYDRELO_Format::money($bobydrelo_row->value)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Daily activity', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php
				$bobydrelo_max = $bobydrelo_report['max_daily'] > 0 ? $bobydrelo_report['max_daily'] : 1;
				?>
				<ul class="obydrelo-bars">
					<?php foreach ($bobydrelo_report['daily'] as $bobydrelo_day) : ?>
						<li>
							<span class="obydrelo-bars__label"><?php echo esc_html($bobydrelo_day->day); ?></span>
							<span class="obydrelo-bars__track">
								<span class="obydrelo-bars__fill" style="width: <?php echo esc_attr((string) round(((int) $bobydrelo_day->count / $bobydrelo_max) * 100, 1)); ?>%"></span>
							</span>
							<span class="obydrelo-bars__value"><?php echo esc_html(number_format_i18n((int) $bobydrelo_day->count)); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
