<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only list filters. Every value is sanitised and none of them changes
// state, so a nonce is deliberately omitted: nonces on GET filters would break
// bookmarked and cached list URLs.
// phpcs:disable WordPress.Security.NonceVerification
$obrl_edit_id   = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
$obrl_member    = $obrl_edit_id ? OBRL_Members::get($obrl_edit_id) : null;
$obrl_search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$obrl_status    = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
$obrl_paged     = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
// phpcs:enable WordPress.Security.NonceVerification
$obrl_list      = OBRL_Members::search(
    array(
        'search' => $obrl_search,
        'status' => $obrl_status,
        'page'   => $obrl_paged,
    )
);
$obrl_statuses  = OBRL_Members::statuses();
$obrl_edit_mode = current_user_can('orl_manage_members') ? 'edit' : 'view';
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Members', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-columns">
		<div class="orl-column orl-column--narrow">
			<div class="orl-panel">
				<h2>
					<?php
					echo $obrl_member
						? esc_html__('Edit member', 'obydullah-restaurant-loyalty')
						: esc_html__('Enrol member', 'obydullah-restaurant-loyalty');
					?>
				</h2>

				<?php if ($obrl_member) : ?>
					<div class="orl-member-preview">
						<?php
						$obrl_card_args = array('member' => $obrl_member, 'compact' => true);
						include OBRL_PATH . 'views/member-card.php';
						?>
					</div>
					<?php if (current_user_can('orl_manage_members')) : ?>
						<p>
							<button type="button" class="button-link" id="orl-recalc"
								data-member-id="<?php echo esc_attr((string) $obrl_member['id']); ?>">
								<?php esc_html_e('Rebuild balance from ledger', 'obydullah-restaurant-loyalty'); ?>
							</button>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-form">
					<input type="hidden" name="action" value="orl_save_member">
					<?php wp_nonce_field(OBRL_NONCE_ADMIN); ?>
					<input type="hidden" name="member_id" value="<?php echo esc_attr((string) $obrl_edit_id); ?>">

					<p>
						<label for="orl-first-name"><?php esc_html_e('First name', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-first-name" name="first_name" class="regular-text"
							value="<?php echo esc_attr($obrl_member['first_name'] ?? ''); ?>" maxlength="100">
					</p>
					<p>
						<label for="orl-last-name"><?php esc_html_e('Last name', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-last-name" name="last_name" class="regular-text"
							value="<?php echo esc_attr($obrl_member['last_name'] ?? ''); ?>" maxlength="100">
					</p>
					<p>
						<label for="orl-email"><?php esc_html_e('Email', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="email" id="orl-email" name="email" class="regular-text"
							value="<?php echo esc_attr($obrl_member['email'] ?? ''); ?>" maxlength="100">
					</p>
					<p>
						<label for="orl-phone"><?php esc_html_e('Phone', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-phone" name="phone" class="regular-text"
							value="<?php echo esc_attr($obrl_member['phone'] ?? ''); ?>" maxlength="50">
					</p>
					<p>
						<label for="orl-birthday"><?php esc_html_e('Birthday', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="date" id="orl-birthday" name="birthday"
							value="<?php echo esc_attr($obrl_member['birthday'] ?? ''); ?>">
						<span class="orl-help"><?php esc_html_e('Used for the birthday reward.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>
					<p>
						<label for="orl-status"><?php esc_html_e('Status', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="orl-status" name="status">
							<?php foreach ($obrl_statuses as $obrl_key => $obrl_label) : ?>
								<option value="<?php echo esc_attr($obrl_key); ?>"
									<?php selected($obrl_member['status'] ?? 'active', $obrl_key); ?>>
									<?php echo esc_html($obrl_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="orl-referral-code"><?php esc_html_e('Referrer code', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-referral-code" name="referral_code" class="regular-text"
							placeholder="<?php esc_attr_e('Optional', 'obydullah-restaurant-loyalty'); ?>">
						<span class="orl-help"><?php esc_html_e('Only used when enrolling a new member.', 'obydullah-restaurant-loyalty'); ?></span>
					</p>
					<p>
						<label for="orl-notes"><?php esc_html_e('Notes', 'obydullah-restaurant-loyalty'); ?></label>
						<textarea id="orl-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($obrl_member['notes'] ?? ''); ?></textarea>
					</p>

					<?php if ('edit' === $obrl_edit_mode) : ?>
						<p>
							<button type="submit" class="button button-primary"><?php esc_html_e('Save member', 'obydullah-restaurant-loyalty'); ?></button>
							<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=orl-members')); ?>"><?php esc_html_e('Cancel', 'obydullah-restaurant-loyalty'); ?></a>
						</p>
					<?php endif; ?>
				</form>
			</div>
		</div>

		<div class="orl-column">
			<div class="orl-panel">
				<form method="get" class="orl-search-form">
					<input type="hidden" name="page" value="orl-members">
					<input type="search" name="s" value="<?php echo esc_attr($obrl_search); ?>"
						placeholder="<?php esc_attr_e('Name, email, phone or code', 'obydullah-restaurant-loyalty'); ?>">
					<select name="status">
						<option value=""><?php esc_html_e('All statuses', 'obydullah-restaurant-loyalty'); ?></option>
						<?php foreach ($obrl_statuses as $obrl_key => $obrl_label) : ?>
							<option value="<?php echo esc_attr($obrl_key); ?>" <?php selected($obrl_status, $obrl_key); ?>>
								<?php echo esc_html($obrl_label); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button"><?php esc_html_e('Search', 'obydullah-restaurant-loyalty'); ?></button>
				</form>

				<?php if (!$obrl_list['items']) : ?>
					<p class="orl-empty"><?php esc_html_e('No members found.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
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
						<?php foreach ($obrl_list['items'] as $obrl_row) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url(admin_url('admin.php?page=orl-members&member_id=' . $obrl_row['id'])); ?>">
										<?php echo esc_html(OBRL_Members::full_name($obrl_row)); ?>
									</a>
									<?php if ($obrl_row['email']) : ?>
										<br><small class="orl-muted"><?php echo esc_html($obrl_row['email']); ?></small>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html($obrl_row['member_code']); ?></code></td>
								<td>
									<?php echo esc_html(OBRL_Format::points($obrl_row['points_balance'])); ?> pts<br>
									<small class="orl-muted">
										<?php
										echo esc_html(
											sprintf(
												'%d stamps / %d visits',
												$obrl_row['stamps_count'],
												$obrl_row['visits_count']
											)
										);
										?>
									</small>
								</td>
								<td>
									<span class="orl-status orl-status--<?php echo esc_attr($obrl_row['status']); ?>">
										<?php echo esc_html($obrl_statuses[$obrl_row['status']] ?? $obrl_row['status']); ?>
									</span>
								</td>
								<td><?php echo esc_html(OBRL_Format::date($obrl_row['last_visit_at'])); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ($obrl_list['pages'] > 1) : ?>
						<div class="tablenav">
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									(string) paginate_links(
										array(
											'base'      => add_query_arg('paged', '%#%'),
											'format'    => '',
											'current'   => $obrl_list['page'],
											'total'     => $obrl_list['pages'],
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
