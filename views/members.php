<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only list filters. Every value is sanitised and none of them changes
// state, so a nonce is deliberately omitted: nonces on GET filters would break
// bookmarked and cached list URLs.
// phpcs:disable WordPress.Security.NonceVerification
$obydrelo_edit_id   = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
$obydrelo_member    = $obydrelo_edit_id ? OBYDRELO_Members::get($obydrelo_edit_id) : null;
$obydrelo_search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$obydrelo_status    = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$obydrelo_paged     = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
// phpcs:enable WordPress.Security.NonceVerification
$obydrelo_list      = OBYDRELO_Members::search(
    array(
        'search' => $obydrelo_search,
        'status' => $obydrelo_status,
        'page'   => $obydrelo_paged,
    )
);
$obydrelo_statuses  = OBYDRELO_Members::statuses();
$obydrelo_edit_mode = current_user_can('obydrelo_manage_members') ? 'edit' : 'view';
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Members', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-columns">
		<div class="obydrelo-column obydrelo-column--narrow">
			<div class="obydrelo-panel">
				<h2>
					<?php
					echo $obydrelo_member
						? esc_html__('Edit member', 'obydullah-restaurant-loyalty')
						: esc_html__('Enrol member', 'obydullah-restaurant-loyalty');
					?>
				</h2>

				<?php if ($obydrelo_member) : ?>
					<div class="obydrelo-member-preview">
						<?php
						$obydrelo_card_args = array('member' => $obydrelo_member, 'compact' => true);
						include OBYDRELO_PATH . 'views/member-card.php';
						?>
					</div>
					<?php if (current_user_can('obydrelo_manage_members')) : ?>
						<p>
							<button type="button" class="button-link" id="obydrelo-recalc"
								data-member-id="<?php echo esc_attr((string) $obydrelo_member['id']); ?>">
								<?php esc_html_e('Rebuild balance from ledger', 'obydullah-restaurant-loyalty'); ?>
							</button>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="obydrelo-form">
					<input type="hidden" name="action" value="obydrelo_save_member">
					<?php wp_nonce_field(OBYDRELO_NONCE_ADMIN); ?>
					<input type="hidden" name="member_id" value="<?php echo esc_attr((string) $obydrelo_edit_id); ?>">

					<p>
						<label for="obydrelo-first-name"><?php esc_html_e('First name', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-first-name" name="first_name" class="regular-text"
							value="<?php echo esc_attr($obydrelo_member['first_name'] ?? ''); ?>" maxlength="100">
					</p>
					<p>
						<label for="obydrelo-last-name"><?php esc_html_e('Last name', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-last-name" name="last_name" class="regular-text"
							value="<?php echo esc_attr($obydrelo_member['last_name'] ?? ''); ?>" maxlength="100">
					</p>
					<p>
						<label for="obydrelo-email"><?php esc_html_e('Email', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="email" id="obydrelo-email" name="email" class="regular-text"
							value="<?php echo esc_attr($obydrelo_member['email'] ?? ''); ?>" maxlength="100">
					</p>
					<p>
						<label for="obydrelo-phone"><?php esc_html_e('Phone', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-phone" name="phone" class="regular-text"
							value="<?php echo esc_attr($obydrelo_member['phone'] ?? ''); ?>" maxlength="50">
					</p>
					<p>
						<label for="obydrelo-birthday"><?php esc_html_e('Birthday', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="date" id="obydrelo-birthday" name="birthday"
							value="<?php echo esc_attr($obydrelo_member['birthday'] ?? ''); ?>">
						<span class="obydrelo-help"><?php esc_html_e('Used for the birthday reward.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>
					<p>
						<label for="obydrelo-status"><?php esc_html_e('Status', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="obydrelo-status" name="status">
							<?php foreach ($obydrelo_statuses as $obydrelo_key => $obydrelo_label) : ?>
								<option value="<?php echo esc_attr($obydrelo_key); ?>"
									<?php selected($obydrelo_member['status'] ?? 'active', $obydrelo_key); ?>>
									<?php echo esc_html($obydrelo_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="obydrelo-referral-code"><?php esc_html_e('Referrer code', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-referral-code" name="referral_code" class="regular-text"
							placeholder="<?php esc_attr_e('Optional', 'obydullah-restaurant-loyalty'); ?>">
						<span class="obydrelo-help"><?php esc_html_e('Only used when enrolling a new member.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>
					<p>
						<label for="obydrelo-notes"><?php esc_html_e('Notes', 'obydullah-restaurant-loyalty'); ?></label>
						<textarea id="obydrelo-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($obydrelo_member['notes'] ?? ''); ?></textarea>
					</p>

					<?php if ('edit' === $obydrelo_edit_mode) : ?>
						<p>
							<button type="submit" class="button button-primary"><?php esc_html_e('Save member', 'obydullah-restaurant-loyalty'); ?></button>
							<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-members')); ?>"><?php esc_html_e('Cancel', 'obydullah-restaurant-loyalty'); ?></a>
						</p>
					<?php endif; ?>
				</form>
			</div>
		</div>

		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<form method="get" class="obydrelo-search-form">
					<input type="hidden" name="page" value="obydrelo-members">
					<input type="search" name="s" value="<?php echo esc_attr($obydrelo_search); ?>"
						placeholder="<?php esc_attr_e('Name, email, phone or code', 'obydullah-restaurant-loyalty'); ?>">
					<select name="status">
						<option value=""><?php esc_html_e('All statuses', 'obydullah-restaurant-loyalty'); ?></option>
						<?php foreach ($obydrelo_statuses as $obydrelo_key => $obydrelo_label) : ?>
							<option value="<?php echo esc_attr($obydrelo_key); ?>" <?php selected($obydrelo_status, $obydrelo_key); ?>>
								<?php echo esc_html($obydrelo_label); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button"><?php esc_html_e('Search', 'obydullah-restaurant-loyalty'); ?></button>
				</form>

				<?php if (!$obydrelo_list['items']) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No members found.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Member', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Code', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Balance', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Status', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Last visit', 'obydullah-restaurant-loyalty'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obydrelo_list['items'] as $obydrelo_row) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-members&member_id=' . $obydrelo_row['id'])); ?>">
										<?php echo esc_html(OBYDRELO_Members::full_name($obydrelo_row)); ?>
									</a>
									<?php if ($obydrelo_row['email']) : ?>
										<br><small class="obydrelo-muted"><?php echo esc_html($obydrelo_row['email']); ?></small>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html($obydrelo_row['member_code']); ?></code></td>
								<td>
									<?php echo esc_html(OBYDRELO_Format::points($obydrelo_row['points_balance'])); ?> pts<br>
									<small class="obydrelo-muted">
										<?php
										echo esc_html(
											sprintf(
												'%d stamps / %d visits',
												$obydrelo_row['stamps_count'],
												$obydrelo_row['visits_count']
											)
										);
										?>
									</small>
								</td>
								<td>
									<span class="obydrelo-status obydrelo-status--<?php echo esc_attr($obydrelo_row['status']); ?>">
										<?php echo esc_html($obydrelo_statuses[$obydrelo_row['status']] ?? $obydrelo_row['status']); ?>
									</span>
								</td>
								<td><?php echo esc_html(OBYDRELO_Format::date($obydrelo_row['last_visit_at'])); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ($obydrelo_list['pages'] > 1) : ?>
						<div class="tablenav">
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									(string) paginate_links(
										array(
											'base'      => add_query_arg('paged', '%#%'),
											'format'    => '',
											'current'   => $obydrelo_list['page'],
											'total'     => $obydrelo_list['pages'],
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
	</div>
</div>
