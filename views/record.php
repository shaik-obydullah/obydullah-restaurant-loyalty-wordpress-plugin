<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$obrl_types   = OBRL_Ledger::types();
$obrl_mode    = (string) OBRL_Settings::get('program_mode', 'points');
// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obrl_type    = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'purchase';
$obrl_type    = array_key_exists($obrl_type, $obrl_types) ? $obrl_type : 'purchase';
$obrl_history = OBRL_Ledger::history(array('per_page' => 20));
$obrl_can_adj = current_user_can('orl_apply_adjustments');
$obrl_preset  = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
// phpcs:enable WordPress.Security.NonceVerification
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Record Transaction', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-columns">
		<div class="orl-column orl-column--narrow">
			<div class="orl-panel">
				<h2><?php esc_html_e('New entry', 'obydullah-restaurant-loyalty'); ?></h2>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-form" id="orl-record-form">
					<input type="hidden" name="action" value="orl_record_transaction">
					<?php wp_nonce_field(OBRL_NONCE_STAFF); ?>
					<input type="hidden" name="member_id" id="orl-member-id" value="0">

					<p>
						<label for="orl-member-search"><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-member-search" class="regular-text" autocomplete="off"
							data-preset-member="<?php echo esc_attr((string) $obrl_preset); ?>"
							placeholder="<?php esc_attr_e('Start typing a name or code', 'obydullah-restaurant-loyalty'); ?>">
						<div id="orl-member-results" class="orl-autocomplete" hidden></div>
						<div id="orl-member-selected" class="orl-selected" hidden></div>
					</p>

					<p>
						<label for="orl-type"><?php esc_html_e('Entry type', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="orl-type" name="type">
							<?php foreach ($obrl_types as $obrl_key => $obrl_label) : ?>
								<?php
								if ('adjustment' === $obrl_key && !$obrl_can_adj) {
									continue;
								}
								if (in_array($obrl_key, array('welcome', 'birthday', 'referral', 'expire', 'void', 'redeem'), true)) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr($obrl_key); ?>" <?php selected($obrl_type, $obrl_key); ?>>
									<?php echo esc_html($obrl_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p id="orl-amount-row" <?php echo 'purchase' === $obrl_type ? '' : 'hidden'; ?>>
						<label for="orl-amount"><?php esc_html_e('Purchase amount', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="number" id="orl-amount" name="amount" step="0.01" min="0" class="regular-text"
							value="<?php
							// Read-only prefill of the form value from a previous redirect. It only
							// decides what the form starts with, and the value is verified against
							// the nonce and re-validated when the form is submitted.
							// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefill for redisplay, not an action taken on the value.
							echo esc_attr(isset($_GET['amount']) ? sanitize_text_field(wp_unslash($_GET['amount'])) : '');
							?>">
						<span class="orl-help">
							<?php
							printf(
								/* translators: %s: currency code. */
								esc_html__('Points are calculated from the earn rate.', 'obydullah-restaurant-loyalty'),
								esc_html((string) OBRL_Settings::get('currency_code', ''))
							);
							?>
						</span>
					</p>

					<div id="orl-manual-rows" <?php echo 'purchase' === $obrl_type ? 'hidden' : ''; ?>>
						<p>
							<label for="orl-points"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-points" name="points" step="0.01" class="regular-text" value="0">
							<span class="orl-help"><?php esc_html_e('Use a negative number to deduct.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="orl-stamps"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-stamps" name="stamps" step="1" class="regular-text" value="0">
						</p>
						<p>
							<label for="orl-visits"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-visits" name="visits" step="1" class="regular-text" value="0">
						</p>
					</div>

					<p>
						<label for="orl-external-id"><?php esc_html_e('Receipt / reference', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-external-id" name="external_id" class="regular-text" maxlength="100"
							placeholder="<?php esc_attr_e('Bill or POS reference', 'obydullah-restaurant-loyalty'); ?>">
						<span class="orl-help"><?php esc_html_e('Re-using a reference for the same member and type is rejected, which prevents double posting.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>

					<p>
						<label for="orl-note"><?php esc_html_e('Note', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-note" name="note" class="regular-text" maxlength="255">
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Record entry', 'obydullah-restaurant-loyalty'); ?></button>
					</p>
				</form>
			</div>
		</div>

		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('Latest entries', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_history['items']) : ?>
					<p class="orl-empty"><?php esc_html_e('Nothing recorded yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
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
						$obrl_members_table = OBRL_Members::table();
						foreach ($obrl_history['items'] as $obrl_row) :
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
								<td>
									<a href="<?php echo esc_url(admin_url('admin.php?page=orl-members&member_id=' . (int) $obrl_row->member_id)); ?>">
										<?php echo esc_html($obrl_name ? $obrl_name : __('(deleted)', 'obydullah-restaurant-loyalty')); ?>
									</a>
								</td>
								<td><?php echo esc_html($obrl_types[$obrl_row->type] ?? $obrl_row->type); ?></td>
								<td><?php echo esc_html(OBRL_Format::signed_points($obrl_row->points)); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											'%+d / %+d',
											(int) $obrl_row->stamps,
											(int) $obrl_row->visits
										)
									);
									?>
								</td>
								<td><?php echo esc_html($obrl_row->staff_login ? $obrl_row->staff_login : '—'); ?></td>
								<td><?php echo esc_html(OBRL_Format::datetime($obrl_row->created_at)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
