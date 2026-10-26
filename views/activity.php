<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$obydrelo_table   = OBYDRELO_Schema::table('activity_logs');
// Read-only pagination parameter, already coerced to a positive integer. It
// changes nothing, so a nonce is deliberately omitted.
// phpcs:disable WordPress.Security.NonceVerification
$obydrelo_paged   = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
// phpcs:enable WordPress.Security.NonceVerification
$obydrelo_per     = 30;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
$obydrelo_total   = $obydrelo_table ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $obydrelo_table)) : 0;
$obydrelo_pages   = (int) ceil($obydrelo_total / $obydrelo_per);
$obydrelo_offset  = ($obydrelo_paged - 1) * $obydrelo_per;
$obydrelo_rows    = $obydrelo_table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
    ? $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
            $obydrelo_table,
            $obydrelo_per,
            $obydrelo_offset
        )
    )
    : array();
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Activity Log', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-panel">
		<p class="obydrelo-muted">
			<?php
			printf(
				/* translators: %s: number of log entries. */
				esc_html(_n('%s entry recorded.', '%s entries recorded.', $obydrelo_total, 'obydullah-restaurant-loyalty')),
				esc_html(number_format_i18n($obydrelo_total))
			);
			?>
		</p>

		<?php if (!$obydrelo_rows) : ?>
			<p class="obydrelo-empty"><?php esc_html_e('Nothing logged yet.', 'obydullah-restaurant-loyalty'); ?></p>
		<?php else : ?>
			<table class="widefat striped obydrelo-table">
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
				<?php foreach ($obydrelo_rows as $obydrelo_row) : ?>
					<tr>
						<td><?php echo esc_html(OBYDRELO_Format::datetime($obydrelo_row->created_at)); ?></td>
						<td><code><?php echo esc_html($obydrelo_row->action); ?></code></td>
						<td>
							<?php echo esc_html($obydrelo_row->user_login ? $obydrelo_row->user_login : $obydrelo_row->actor_type); ?>
							<br><small class="obydrelo-muted"><?php echo esc_html($obydrelo_row->ip_address); ?></small>
						</td>
						<td>
							<?php echo esc_html($obydrelo_row->object_type); ?>
							<?php if ((int) $obydrelo_row->object_id) : ?>
								#<?php echo esc_html((string) (int) $obydrelo_row->object_id); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html($obydrelo_row->message); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ($obydrelo_pages > 1) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							(string) paginate_links(
								array(
									'base'      => add_query_arg('paged', '%#%'),
									'format'    => '',
									'current'   => $obydrelo_paged,
									'total'     => $obydrelo_pages,
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
