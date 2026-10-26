<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$bobydrelo_stats   = OBYDRELO_Members::stats();
$bobydrelo_mode    = (string) OBYDRELO_Settings::get('program_mode', 'points');
$bobydrelo_rewards = OBYDRELO_Rewards::list_rewards(array('active_only' => true));
$bobydrelo_bdays   = OBYDRELO_Birthday::upcoming(30, 8);
$bobydrelo_tx      = OBYDRELO_Ledger::history(array('per_page' => 10));
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Loyalty Dashboard', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-stat-grid">
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($bobydrelo_stats['total'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Total members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($bobydrelo_stats['active'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Active members', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n($bobydrelo_stats['new_this_month'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('New this month', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(OBYDRELO_Format::points($bobydrelo_stats['points_outstanding'])); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Points outstanding', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
		<div class="obydrelo-stat">
			<span class="obydrelo-stat__value"><?php echo esc_html(number_format_i18n(count($bobydrelo_rewards))); ?></span>
			<span class="obydrelo-stat__label"><?php esc_html_e('Active rewards', 'obydullah-restaurant-loyalty'); ?></span>
		</div>
	</div>

	<div class="obydrelo-columns">
		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Recent activity', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$bobydrelo_tx['items']) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No transactions recorded yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
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
						$bobydrelo_members_table = OBYDRELO_Members::table();
						foreach ($bobydrelo_tx['items'] as $bobydrelo_row) :
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
							$bobydrelo_name = $wpdb->get_var(
								$wpdb->prepare(
									'SELECT CONCAT(first_name, \' \', last_name) FROM %i WHERE id = %d',
									$bobydrelo_members_table,
									(int) $bobydrelo_row->member_id
								)
							);
							?>
							<tr>
								<td><?php echo esc_html($bobydrelo_name ? $bobydrelo_name : __('(deleted)', 'obydullah-restaurant-loyalty')); ?></td>
								<td><?php echo esc_html(OBYDRELO_Ledger::types()[$bobydrelo_row->type] ?? $bobydrelo_row->type); ?></td>
								<td>
									<?php if ((float) $bobydrelo_row->points) : ?>
										<strong><?php echo esc_html(OBYDRELO_Format::signed_points($bobydrelo_row->points)); ?></strong>
									<?php elseif ((int) $bobydrelo_row->stamps) : ?>
										<strong>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: signed stamp count. */
												'%+d stamps',
												(int) $bobydrelo_row->stamps
											)
										);
										?>
										</strong>
									<?php elseif ((int) $bobydrelo_row->visits) : ?>
										<strong>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: signed visit count. */
												'%+d visits',
												(int) $bobydrelo_row->visits
											)
										);
										?>
										</strong>
									<?php else : ?>
										<span class="obydrelo-muted"><?php esc_html_e('Amount only', 'obydullah-restaurant-loyalty'); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html(OBYDRELO_Format::datetime($bobydrelo_row->created_at)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Upcoming birthdays', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$bobydrelo_bdays) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No birthdays in the next 30 days.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<ul class="obydrelo-list">
						<?php foreach ($bobydrelo_bdays as $bobydrelo_bday) : ?>
							<li>
								<span class="obydrelo-list__main">
									<?php echo esc_html(OBYDRELO_Members::full_name($bobydrelo_bday)); ?>
									<small><?php echo esc_html(OBYDRELO_Format::date($bobydrelo_bday->birthday)); ?></small>
								</span>
								<?php if (!empty($bobydrelo_bday->is_today)) : ?>
									<span class="obydrelo-badge obydrelo-badge--today"><?php esc_html_e('Today', 'obydullah-restaurant-loyalty'); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Quick actions', 'obydullah-restaurant-loyalty'); ?></h2>
				<p class="obydrelo-button-row">
					<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-record')); ?>"><?php esc_html_e('Record transaction', 'obydullah-restaurant-loyalty'); ?></a>
					<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-redeem')); ?>"><?php esc_html_e('Redeem reward', 'obydullah-restaurant-loyalty'); ?></a>
					<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-members&member_id=0')); ?>"><?php esc_html_e('Enrol member', 'obydullah-restaurant-loyalty'); ?></a>
				</p>
				<?php if (empty($bobydrelo_mode)) : ?>
					<p class="obydrelo-muted"><?php esc_html_e('Set the program mode in Settings before recording purchases.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
