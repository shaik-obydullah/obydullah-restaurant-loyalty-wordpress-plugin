<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$bobydrelo_types   = OBYDRELO_Ledger::types();
$bobydrelo_mode    = (string) OBYDRELO_Settings::get('program_mode', 'points');
// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$bobydrelo_type    = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'purchase';
$bobydrelo_type    = array_key_exists($bobydrelo_type, $bobydrelo_types) ? $bobydrelo_type : 'purchase';
$bobydrelo_history = OBYDRELO_Ledger::history(array('per_page' => 20));
$bobydrelo_can_adj = current_user_can('obydrelo_apply_adjustments');
$bobydrelo_preset  = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
// phpcs:enable WordPress.Security.NonceVerification
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Record Transaction', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-columns">
		<div class="obydrelo-column obydrelo-column--narrow">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('New entry', 'obydullah-restaurant-loyalty'); ?></h2>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="obydrelo-form" id="obydrelo-record-form">
					<input type="hidden" name="action" value="obydrelo_record_transaction">
					<?php wp_nonce_field(OBYDRELO_NONCE_STAFF); ?>
					<input type="hidden" name="member_id" id="obydrelo-member-id" value="0">

					<p>
						<label for="obydrelo-member-search"><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-member-search" class="regular-text" autocomplete="off"
							data-preset-member="<?php echo esc_attr((string) $bobydrelo_preset); ?>"
							placeholder="<?php esc_attr_e('Start typing a name or code', 'obydullah-restaurant-loyalty'); ?>">
						<div id="obydrelo-member-results" class="obydrelo-autocomplete" hidden></div>
						<div id="obydrelo-member-selected" class="obydrelo-selected" hidden></div>
					</p>

					<p>
						<label for="obydrelo-type"><?php esc_html_e('Entry type', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="obydrelo-type" name="type">
							<?php foreach ($bobydrelo_types as $bobydrelo_key => $bobydrelo_label) : ?>
								<?php
								if ('adjustment' === $bobydrelo_key && !$bobydrelo_can_adj) {
									continue;
								}
								if (in_array($bobydrelo_key, array('welcome', 'birthday', 'referral', 'expire', 'void', 'redeem'), true)) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr($bobydrelo_key); ?>" <?php selected($bobydrelo_type, $bobydrelo_key); ?>>
									<?php echo esc_html($bobydrelo_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p id="obydrelo-amount-row" <?php echo 'purchase' === $bobydrelo_type ? '' : 'hidden'; ?>>
						<label for="obydrelo-amount"><?php esc_html_e('Purchase amount', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="number" id="obydrelo-amount" name="amount" step="0.01" min="0" class="regular-text"
							value="<?php
							// Read-only prefill of the form value from a previous redirect. It only
							// decides what the form starts with, and the value is verified against
							// the nonce and re-validated when the form is submitted.
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefill for redisplay, not an action taken on the value.
							echo esc_attr(isset($_GET['amount']) ? sanitize_text_field(wp_unslash($_GET['amount'])) : '');
							?>">
						<span class="obydrelo-help">
							<?php
							printf(
								/* translators: %s: currency code. */
								esc_html__('Points are calculated from the earn rate.', 'obydullah-restaurant-loyalty'),
								esc_html((string) OBYDRELO_Settings::get('currency_code', ''))
							);
							?>
						</span>
					</p>

					<div id="obydrelo-manual-rows" <?php echo 'purchase' === $bobydrelo_type ? 'hidden' : ''; ?>>
						<p>
							<label for="obydrelo-points"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-points" name="points" step="0.01" class="regular-text" value="0">
							<span class="obydrelo-help"><?php esc_html_e('Use a negative number to deduct.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="obydrelo-stamps"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-stamps" name="stamps" step="1" class="regular-text" value="0">
						</p>
						<p>
							<label for="obydrelo-visits"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-visits" name="visits" step="1" class="regular-text" value="0">
						</p>
					</div>

					<p>
						<label for="obydrelo-external-id"><?php esc_html_e('Receipt / reference', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-external-id" name="external_id" class="regular-text" maxlength="100"
							placeholder="<?php esc_attr_e('Bill or POS reference', 'obydullah-restaurant-loyalty'); ?>">
						<span class="obydrelo-help"><?php esc_html_e('Re-using a reference for the same member and type is rejected, which prevents double posting.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>

					<p>
						<label for="obydrelo-note"><?php esc_html_e('Note', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-note" name="note" class="regular-text" maxlength="255">
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Record entry', 'obydullah-restaurant-loyalty'); ?></button>
					</p>
				</form>
			</div>
		</div>

		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('Latest entries', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$bobydrelo_history['items']) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('Nothing recorded yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Type', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Stamps / Visits', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Staff', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('When', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$bobydrelo_members_table = OBYDRELO_Members::table();
						foreach ($bobydrelo_history['items'] as $bobydrelo_row) :
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
								<td>
									<a href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-members&member_id=' . (int) $bobydrelo_row->member_id)); ?>">
										<?php echo esc_html($bobydrelo_name ? $bobydrelo_name : __('(deleted)', 'obydullah-restaurant-loyalty')); ?>
									</a>
								</td>
								<td><?php echo esc_html($bobydrelo_types[$bobydrelo_row->type] ?? $bobydrelo_row->type); ?></td>
								<td><?php echo esc_html(OBYDRELO_Format::signed_points($bobydrelo_row->points)); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											'%+d / %+d',
											(int) $bobydrelo_row->stamps,
											(int) $bobydrelo_row->visits
										)
									);
									?>
								</td>
								<td><?php echo esc_html($bobydrelo_row->staff_login ? $bobydrelo_row->staff_login : '—'); ?></td>
								<td><?php echo esc_html(OBYDRELO_Format::datetime($bobydrelo_row->created_at)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
