<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obrl_range   = isset($_GET['range']) ? sanitize_text_field(wp_unslash($_GET['range'])) : '30';
// phpcs:enable WordPress.Security.NonceVerification
$obrl_range   = in_array($obrl_range, array('7', '30', '90', '365'), true) ? $obrl_range : '30';
$obrl_report  = OBRL_Reports::build($obrl_range);
$obrl_csv_url = wp_nonce_url(
    admin_url('admin-post.php?action=orl_export&report=' . esc_attr($obrl_report['range'])),
    OBRL_NONCE_ADMIN
);
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Reports', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-toolbar">
		<form method="get">
			<input type="hidden" name="page" value="orl-reports">
			<label for="orl-range"><?php esc_html_e('Period', 'obydullah-restaurant-loyalty'); ?></label>
			<select id="orl-range" name="range">
				<?php
				$obrl_ranges = array(
					'7'   => __('Last 7 days', 'obydullah-restaurant-loyalty'),
					'30'  => __('Last 30 days', 'obydullah-restaurant-loyalty'),
					'90'  => __('Last 90 days', 'obydullah-restaurant-loyalty'),
					'365' => __('Last 12 months', 'obydullah-restaurant-loyalty'),
				);
				foreach ($obrl_ranges as $obrl_rk => $obrl_rl) :
					?>
					<option value="<?php echo esc_attr($obrl_rk); ?>" <?php selected($obrl_range, $obrl_rk); ?>>
						<?php echo esc_html($obrl_rl); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e('Apply', 'obydullah-restaurant-loyalty'); ?></button>
		</form>
		<a class="button button-primary" href="<?php echo esc_url($obrl_csv_url); ?>">
			<?php esc_html_e('Export CSV', 'obydullah-restaurant-loyalty'); ?>
		</a>
	</div>

	<div class="orl-stat-grid">
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(OBRL_Format::money($obrl_report['revenue'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Recorded spend', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n($obrl_report['transactions'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Transactions', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n($obrl_report['new_members'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('New members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(OBRL_Format::points($obrl_report['points_earned'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Points earned', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(OBRL_Format::points(abs($obrl_report['points_redeemed']))); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Points redeemed', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n($obrl_report['redemptions'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Redemptions', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
	</div>

	<div class="orl-columns">
		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('Top members by spend', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_report['top_members']) : ?>
					<p class="orl-empty"><?php esc_html_e('No spend recorded in this period.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Code', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Spend', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obrl_report['top_members'] as $obrl_row) : ?>
							<tr>
								<td><?php echo esc_html($obrl_row->name); ?></td>
								<td><code><?php echo esc_html($obrl_row->member_code); ?></code></td>
								<td><?php echo esc_html(OBRL_Format::money($obrl_row->total_spend)); ?></td>
								<td><?php echo esc_html(number_format_i18n((int) $obrl_row->visits)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('Reward usage', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_report['reward_usage']) : ?>
					<p class="orl-empty"><?php esc_html_e('No rewards redeemed in this period.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Reward', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Redemptions', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Value given', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obrl_report['reward_usage'] as $obrl_row) : ?>
							<tr>
								<td><?php echo esc_html($obrl_row->title); ?></td>
								<td><?php echo esc_html(number_format_i18n((int) $obrl_row->count)); ?></td>
								<td><?php echo esc_html(OBRL_Format::money($obrl_row->value)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="orl-panel">
				<h2><?php esc_html_e('Daily activity', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php
				$obrl_max = $obrl_report['max_daily'] > 0 ? $obrl_report['max_daily'] : 1;
				?>
				<ul class="orl-bars">
					<?php foreach ($obrl_report['daily'] as $obrl_day) : ?>
						<li>
							<span class="orl-bars__label"><?php echo esc_html($obrl_day->day); ?></span>
							<span class="orl-bars__track">
								<span class="orl-bars__fill" style="width: <?php echo esc_attr((string) round(((int) $obrl_day->count / $obrl_max) * 100, 1)); ?>%"></span>
							</span>
							<span class="orl-bars__value"><?php echo esc_html(number_format_i18n((int) $obrl_day->count)); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
</div>
