<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$obrl_table   = OBRL_Schema::table('activity_logs');
// Read-only pagination parameter, already coerced to a positive integer. It
// changes nothing, so a nonce is deliberately omitted.
// phpcs:disable WordPress.Security.NonceVerification
$obrl_paged   = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
// phpcs:enable WordPress.Security.NonceVerification
$obrl_per     = 30;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
$obrl_total   = $obrl_table ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $obrl_table)) : 0;
$obrl_pages   = (int) ceil($obrl_total / $obrl_per);
$obrl_offset  = ($obrl_paged - 1) * $obrl_per;
$obrl_rows    = $obrl_table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
    ? $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
            $obrl_table,
            $obrl_per,
            $obrl_offset
        )
    )
    : array();
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Activity Log', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-panel">
		<p class="orl-muted">
			<?php
			printf(
				/* translators: %s: number of log entries. */
				esc_html(_n('%s entry recorded.', '%s entries recorded.', $obrl_total, 'obydullah-restaurant-loyalty')),
				esc_html(number_format_i18n($obrl_total))
			);
			?>
		</p>

		<?php if (!$obrl_rows) : ?>
			<p class="orl-empty"><?php esc_html_e('Nothing logged yet.', 'obydullah-restaurant-loyalty'); ?></p>
		<?php else : ?>
			<table class="widefat striped orl-table">
				<thead>
					<tr>
						<th><?php esc_html_e('When', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Action', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Actor', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Object', 'obydullah-restaurant-loyalty'); ?></th>
						<th><?php esc_html_e('Message', 'obydullah-restaurant-loyalty'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($obrl_rows as $obrl_row) : ?>
					<tr>
						<td><?php echo esc_html(OBRL_Format::datetime($obrl_row->created_at)); ?></td>
						<td><code><?php echo esc_html($obrl_row->action); ?></code></td>
						<td>
							<?php echo esc_html($obrl_row->user_login ? $obrl_row->user_login : $obrl_row->actor_type); ?>
							<br><small class="orl-muted"><?php echo esc_html($obrl_row->ip_address); ?></small>
						</td>
						<td>
							<?php echo esc_html($obrl_row->object_type); ?>
							<?php if ((int) $obrl_row->object_id) : ?>
								#<?php echo esc_html((string) (int) $obrl_row->object_id); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html($obrl_row->message); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ($obrl_pages > 1) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							(string) paginate_links(
								array(
									'base'      => add_query_arg('paged', '%#%'),
									'format'    => '',
									'current'   => $obrl_paged,
									'total'     => $obrl_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
