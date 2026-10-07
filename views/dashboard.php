<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$obrl_stats   = OBRL_Members::stats();
$obrl_mode    = (string) OBRL_Settings::get('program_mode', 'points');
$obrl_rewards = OBRL_Rewards::list_rewards(array('active_only' => true));
$obrl_bdays   = OBRL_Birthday::upcoming(30, 8);
$obrl_tx      = OBRL_Ledger::history(array('per_page' => 10));
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Loyalty Dashboard', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-stat-grid">
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n($obrl_stats['total'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Total members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n($obrl_stats['active'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Active members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n($obrl_stats['new_this_month'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('New this month', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(OBRL_Format::points($obrl_stats['points_outstanding'])); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Points outstanding', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="orl-stat">
			<span class="orl-stat__value"><?php echo esc_html(number_format_i18n(count($obrl_rewards))); ?></span>
			<span class="orl-stat__label"><?php esc_html_e('Active rewards', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
	</div>

	<div class="orl-columns">
		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('Recent activity', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_tx['items']) : ?>
					<p class="orl-empty"><?php esc_html_e('No transactions recorded yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Type', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Change', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('When', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$obrl_members_table = OBRL_Members::table();
						foreach ($obrl_tx['items'] as $obrl_row) :
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
							$obrl_name = $wpdb->get_var(
								$wpdb->prepare(
									'SELECT CONCAT(first_name, \' \', last_name) FROM %i WHERE id = %d',
									$obrl_members_table,
									(int) $obrl_row->member_id
								)
							);
							?>
							<tr>
								<td><?php echo esc_html($obrl_name ? $obrl_name : __('(deleted)', 'obydullah-restaurant-loyalty')); ?></td>
								<td><?php echo esc_html(OBRL_Ledger::types()[$obrl_row->type] ?? $obrl_row->type); ?></td>
								<td>
									<?php if ((float) $obrl_row->points) : ?>
										<strong><?php echo esc_html(OBRL_Format::signed_points($obrl_row->points)); ?></strong>
									<?php elseif ((int) $obrl_row->stamps) : ?>
										<strong>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: signed stamp count. */
												'%+d stamps',
												(int) $obrl_row->stamps
											)
										);
										?>
										</strong>
									<?php elseif ((int) $obrl_row->visits) : ?>
										<strong>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: signed visit count. */
												'%+d visits',
												(int) $obrl_row->visits
											)
										);
										?>
										</strong>
									<?php else : ?>
										<span class="orl-muted"><?php esc_html_e('Amount only', 'obydullah-restaurant-loyalty'); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html(OBRL_Format::datetime($obrl_row->created_at)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('Upcoming birthdays', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_bdays) : ?>
					<p class="orl-empty"><?php esc_html_e('No birthdays in the next 30 days.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<ul class="orl-list">
						<?php foreach ($obrl_bdays as $obrl_bday) : ?>
							<li>
								<span class="orl-list__main">
									<?php echo esc_html(OBRL_Members::full_name($obrl_bday)); ?>
									<small><?php echo esc_html(OBRL_Format::date($obrl_bday->birthday)); ?></small>
								</span>
								<?php if (!empty($obrl_bday->is_today)) : ?>
									<span class="orl-badge orl-badge--today"><?php esc_html_e('Today', 'obydullah-restaurant-loyalty'); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="orl-panel">
				<h2><?php esc_html_e('Quick actions', 'obydullah-restaurant-loyalty'); ?></h2>
				<p class="orl-button-row">
					<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=orl-record')); ?>"><?php esc_html_e('Record transaction', 'obydullah-restaurant-loyalty'); ?></a>
					<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=orl-redeem')); ?>"><?php esc_html_e('Redeem reward', 'obydullah-restaurant-loyalty'); ?></a>
					<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=orl-members&member_id=0')); ?>"><?php esc_html_e('Enrol member', 'obydullah-restaurant-loyalty'); ?></a>
				</p>
				<?php if (empty($obrl_mode)) : ?>
					<p class="orl-muted"><?php esc_html_e('Set the program mode in Settings before recording purchases.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
