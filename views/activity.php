<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$bobydrelo_table   = OBYDRELO_Schema::table('activity_logs');
// Read-only pagination parameter, already coerced to a positive integer. It
// changes nothing, so a nonce is deliberately omitted.
// phpcs:disable WordPress.Security.NonceVerification
$bobydrelo_paged   = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
// phpcs:enable WordPress.Security.NonceVerification
$bobydrelo_per     = 30;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
$bobydrelo_total   = $bobydrelo_table ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $bobydrelo_table)) : 0;
$bobydrelo_pages   = (int) ceil($bobydrelo_total / $bobydrelo_per);
$bobydrelo_offset  = ($bobydrelo_paged - 1) * $bobydrelo_per;
$bobydrelo_rows    = $bobydrelo_table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Renders the balances being displayed. See the class that owns the table for why those stay uncached.
    ? $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
            $bobydrelo_table,
            $bobydrelo_per,
            $bobydrelo_offset
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
				esc_html(_n('%s entry recorded.', '%s entries recorded.', $bobydrelo_total, 'obydullah-restaurant-loyalty')),
				esc_html(number_format_i18n($bobydrelo_total))
			);
			?>
		</p>

		<?php if (!$bobydrelo_rows) : ?>
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
				<?php foreach ($bobydrelo_rows as $bobydrelo_row) : ?>
					<tr>
						<td><?php echo esc_html(OBYDRELO_Format::datetime($bobydrelo_row->created_at)); ?></td>
						<td><code><?php echo esc_html($bobydrelo_row->action); ?></code></td>
						<td>
							<?php echo esc_html($bobydrelo_row->user_login ? $bobydrelo_row->user_login : $bobydrelo_row->actor_type); ?>
							<br><small class="obydrelo-muted"><?php echo esc_html($bobydrelo_row->ip_address); ?></small>
						</td>
						<td>
							<?php echo esc_html($bobydrelo_row->object_type); ?>
							<?php if ((int) $bobydrelo_row->object_id) : ?>
								#<?php echo esc_html((string) (int) $bobydrelo_row->object_id); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html($bobydrelo_row->message); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ($bobydrelo_pages > 1) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							(string) paginate_links(
								array(
									'base'      => add_query_arg('paged', '%#%'),
									'format'    => '',
									'current'   => $bobydrelo_paged,
									'total'     => $bobydrelo_pages,
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
