<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obrl_edit_id = isset($_GET['reward_id']) ? absint($_GET['reward_id']) : 0;
// phpcs:enable WordPress.Security.NonceVerification
$obrl_reward  = $obrl_edit_id ? OBRL_Rewards::get($obrl_edit_id) : null;
$obrl_rewards = OBRL_Rewards::list_rewards();
$obrl_types   = OBRL_Rewards::types();
?>
<div class="wrap orl-wrap">
	<h1 class="orl-page-title"><?php esc_html_e('Rewards', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBRL_Admin::notice_from_query(); ?>

	<div class="orl-columns">
		<div class="orl-column orl-column--narrow">
			<div class="orl-panel">
				<h2>
					<?php
					echo $obrl_reward
						? esc_html__('Edit reward', 'obydullah-restaurant-loyalty')
						: esc_html__('Add reward', 'obydullah-restaurant-loyalty');
					?>
				</h2>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-form">
					<input type="hidden" name="action" value="orl_save_reward">
					<?php wp_nonce_field(OBRL_NONCE_ADMIN); ?>
					<input type="hidden" name="reward_id" value="<?php echo esc_attr((string) $obrl_edit_id); ?>">

					<p>
						<label for="orl-title"><?php esc_html_e('Title', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="orl-title" name="title" class="regular-text" maxlength="191"
							value="<?php echo esc_attr($obrl_reward['title'] ?? ''); ?>" required>
					</p>
					<p>
						<label for="orl-type"><?php esc_html_e('Type', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="orl-type" name="type">
							<?php foreach ($obrl_types as $obrl_key => $obrl_label) : ?>
								<option value="<?php echo esc_attr($obrl_key); ?>" <?php selected($obrl_reward['type'] ?? 'fixed_value', $obrl_key); ?>>
									<?php echo esc_html($obrl_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<fieldset class="orl-fieldset">
						<legend><?php esc_html_e('Cost', 'obydullah-restaurant-loyalty'); ?></legend>
						<p>
							<label for="orl-points-cost"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-points-cost" name="points_cost" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['points_cost'] ?? 0)); ?>">
						</p>
						<p>
							<label for="orl-stamps-cost"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-stamps-cost" name="stamps_cost" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['stamps_cost'] ?? 0)); ?>">
						</p>
						<p>
							<label for="orl-visits-cost"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-visits-cost" name="visits_cost" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['visits_cost'] ?? 0)); ?>">
						</p>
					</fieldset>

					<fieldset class="orl-fieldset">
						<legend><?php esc_html_e('Value', 'obydullah-restaurant-loyalty'); ?></legend>
						<p>
							<label for="orl-value"><?php esc_html_e('Fixed value', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-value" name="value" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['value'] ?? 0)); ?>">
						</p>
						<p>
							<label for="orl-percent"><?php esc_html_e('Percent off', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-percent" name="percent_off" step="0.01" min="0" max="100" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['percent_off'] ?? 0)); ?>">
						</p>
						<p>
							<label for="orl-max-discount"><?php esc_html_e('Max discount', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-max-discount" name="max_discount" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['max_discount'] ?? 0)); ?>">
							<span class="orl-help"><?php esc_html_e('Caps a percentage reward. 0 means no cap.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="orl-min-purchase"><?php esc_html_e('Minimum purchase', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-min-purchase" name="min_purchase" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['min_purchase'] ?? 0)); ?>">
						</p>
						<p>
							<label for="orl-item-name"><?php esc_html_e('Free item name', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="text" id="orl-item-name" name="item_name" class="regular-text" maxlength="191"
								value="<?php echo esc_attr($obrl_reward['item_name'] ?? ''); ?>">
						</p>
					</fieldset>

					<fieldset class="orl-fieldset">
						<legend><?php esc_html_e('Limits', 'obydullah-restaurant-loyalty'); ?></legend>
						<p>
							<label for="orl-usage-limit"><?php esc_html_e('Total stock', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-usage-limit" name="usage_limit" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr(null === ($obrl_reward['usage_limit'] ?? null) ? '' : (string) $obrl_reward['usage_limit']); ?>">
							<span class="orl-help"><?php esc_html_e('Leave empty for unlimited.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="orl-per-member"><?php esc_html_e('Limit per member', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-per-member" name="per_member_limit" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['per_member_limit'] ?? 0)); ?>">
						</p>
						<p>
							<label for="orl-validity"><?php esc_html_e('Valid for (days)', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-validity" name="validity_days" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['validity_days'] ?? 0)); ?>">
							<span class="orl-help"><?php esc_html_e('0 uses the program default.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="orl-sort"><?php esc_html_e('Sort order', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="orl-sort" name="sort_order" step="1" class="regular-text"
								value="<?php echo esc_attr((string) ($obrl_reward['sort_order'] ?? 0)); ?>">
						</p>
						<p>
							<label>
								<input type="checkbox" name="is_active" value="1"
									<?php checked(!isset($obrl_reward['is_active']) || $obrl_reward['is_active']); ?>>
								<?php esc_html_e('Active', 'obydullah-restaurant-loyalty'); ?>
							</label>
						</p>
					</fieldset>

					<p>
						<label for="orl-description"><?php esc_html_e('Description', 'obydullah-restaurant-loyalty'); ?></label>
						<textarea id="orl-description" name="description" rows="3" class="large-text"><?php echo esc_textarea($obrl_reward['description'] ?? ''); ?></textarea>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Save reward', 'obydullah-restaurant-loyalty'); ?></button>
						<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=orl-rewards')); ?>"><?php esc_html_e('Cancel', 'obydullah-restaurant-loyalty'); ?></a>
					</p>
				</form>
			</div>
		</div>

		<div class="orl-column">
			<div class="orl-panel">
				<h2><?php esc_html_e('All rewards', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obrl_rewards) : ?>
					<p class="orl-empty"><?php esc_html_e('No rewards defined yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped orl-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Reward', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Type', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Cost', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Stock', 'obydullah-restaurant-loyalty'); ?></th>
								<th><?php esc_html_e('Active', 'obydullah-restaurant-loyalty'); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($obrl_rewards as $obrl_row) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url(admin_url('admin.php?page=orl-rewards&reward_id=' . $obrl_row['id'])); ?>">
										<?php echo esc_html($obrl_row['title']); ?>
									</a>
								</td>
								<td><?php echo esc_html($obrl_types[$obrl_row['type']] ?? $obrl_row['type']); ?></td>
								<td>
									<?php echo esc_html(OBRL_Format::points($obrl_row['points_cost'])); ?> pts
									<?php if ($obrl_row['stamps_cost']) : ?>
										/ <?php echo esc_html((string) $obrl_row['stamps_cost']); ?> <?php esc_html_e('stamps', 'obydullah-restaurant-loyalty'); ?>
									<?php endif; ?>
									<?php if ($obrl_row['visits_cost']) : ?>
										/ <?php echo esc_html((string) $obrl_row['visits_cost']); ?> <?php esc_html_e('visits', 'obydullah-restaurant-loyalty'); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$obrl_stock = OBRL_Rewards::stock_remaining($obrl_row);
									echo esc_html(
										null === $obrl_stock
											? __('Unlimited', 'obydullah-restaurant-loyalty')
											: number_format_i18n($obrl_stock) . ' / ' . number_format_i18n($obrl_row['usage_limit'])
									);
									?>
								</td>
								<td><?php echo $obrl_row['is_active'] ? '&#10003;' : '&mdash;'; ?></td>
								<td>
									<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="orl-inline-form"
										onsubmit="return confirm('<?php echo esc_js(__('Remove this reward?', 'obydullah-restaurant-loyalty')); ?>');">
										<input type="hidden" name="action" value="orl_delete_reward">
										<?php wp_nonce_field(OBRL_NONCE_ADMIN); ?>
										<input type="hidden" name="reward_id" value="<?php echo esc_attr((string) $obrl_row['id']); ?>">
										<button type="submit" class="button button-small"><?php esc_html_e('Delete', 'obydullah-restaurant-loyalty'); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
