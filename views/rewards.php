<?php
if (!defined('ABSPATH')) {
    exit;
}

// Read-only display parameter, sanitised above. It selects a view state and
// changes nothing, so a nonce is deliberately omitted to keep URLs shareable.
// phpcs:disable WordPress.Security.NonceVerification
$obydrelo_edit_id = isset($_GET['reward_id']) ? absint($_GET['reward_id']) : 0;
// phpcs:enable WordPress.Security.NonceVerification
$obydrelo_reward  = $obydrelo_edit_id ? OBYDRELO_Rewards::get($obydrelo_edit_id) : null;
$obydrelo_rewards = OBYDRELO_Rewards::list_rewards();
$obydrelo_types   = OBYDRELO_Rewards::types();
?>
<div class="wrap obydrelo-wrap">
	<h1 class="obydrelo-page-title"><?php esc_html_e('Rewards', 'obydullah-restaurant-loyalty'); ?></h1>
	<?php OBYDRELO_Admin::notice_from_query(); ?>

	<div class="obydrelo-columns">
		<div class="obydrelo-column obydrelo-column--narrow">
			<div class="obydrelo-panel">
				<h2>
					<?php
					echo $obydrelo_reward
						? esc_html__('Edit reward', 'obydullah-restaurant-loyalty')
						: esc_html__('Add reward', 'obydullah-restaurant-loyalty');
					?>
				</h2>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="obydrelo-form">
					<input type="hidden" name="action" value="obydrelo_save_reward">
					<?php wp_nonce_field(OBYDRELO_NONCE_ADMIN); ?>
					<input type="hidden" name="reward_id" value="<?php echo esc_attr((string) $obydrelo_edit_id); ?>">

					<p>
						<label for="obydrelo-title"><?php esc_html_e('Title', 'obydullah-restaurant-loyalty'); ?></label>
						<input type="text" id="obydrelo-title" name="title" class="regular-text" maxlength="191"
							value="<?php echo esc_attr($obydrelo_reward['title'] ?? ''); ?>" required>
					</p>
					<p>
						<label for="obydrelo-type"><?php esc_html_e('Type', 'obydullah-restaurant-loyalty'); ?></label>
						<select id="obydrelo-type" name="type">
							<?php foreach ($obydrelo_types as $obydrelo_key => $obydrelo_label) : ?>
								<option value="<?php echo esc_attr($obydrelo_key); ?>" <?php selected($obydrelo_reward['type'] ?? 'fixed_value', $obydrelo_key); ?>>
									<?php echo esc_html($obydrelo_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<fieldset class="obydrelo-fieldset">
						<legend><?php esc_html_e('Cost', 'obydullah-restaurant-loyalty'); ?></legend>
						<p>
							<label for="obydrelo-points-cost"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-points-cost" name="points_cost" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['points_cost'] ?? 0)); ?>">
						</p>
						<p>
							<label for="obydrelo-stamps-cost"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-stamps-cost" name="stamps_cost" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['stamps_cost'] ?? 0)); ?>">
						</p>
						<p>
							<label for="obydrelo-visits-cost"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-visits-cost" name="visits_cost" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['visits_cost'] ?? 0)); ?>">
						</p>
					</fieldset>

					<fieldset class="obydrelo-fieldset">
						<legend><?php esc_html_e('Value', 'obydullah-restaurant-loyalty'); ?></legend>
						<p>
							<label for="obydrelo-value"><?php esc_html_e('Fixed value', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-value" name="value" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['value'] ?? 0)); ?>">
						</p>
						<p>
							<label for="obydrelo-percent"><?php esc_html_e('Percent off', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-percent" name="percent_off" step="0.01" min="0" max="100" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['percent_off'] ?? 0)); ?>">
						</p>
						<p>
							<label for="obydrelo-max-discount"><?php esc_html_e('Max discount', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-max-discount" name="max_discount" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['max_discount'] ?? 0)); ?>">
							<span class="obydrelo-help"><?php esc_html_e('Caps a percentage reward. 0 means no cap.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="obydrelo-min-purchase"><?php esc_html_e('Minimum purchase', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-min-purchase" name="min_purchase" step="0.01" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['min_purchase'] ?? 0)); ?>">
						</p>
						<p>
							<label for="obydrelo-item-name"><?php esc_html_e('Free item name', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="text" id="obydrelo-item-name" name="item_name" class="regular-text" maxlength="191"
								value="<?php echo esc_attr($obydrelo_reward['item_name'] ?? ''); ?>">
						</p>
					</fieldset>

					<fieldset class="obydrelo-fieldset">
						<legend><?php esc_html_e('Limits', 'obydullah-restaurant-loyalty'); ?></legend>
						<p>
							<label for="obydrelo-usage-limit"><?php esc_html_e('Total stock', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-usage-limit" name="usage_limit" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr(null === ($obydrelo_reward['usage_limit'] ?? null) ? '' : (string) $obydrelo_reward['usage_limit']); ?>">
							<span class="obydrelo-help"><?php esc_html_e('Leave empty for unlimited.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="obydrelo-per-member"><?php esc_html_e('Limit per member', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-per-member" name="per_member_limit" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['per_member_limit'] ?? 0)); ?>">
						</p>
						<p>
							<label for="obydrelo-validity"><?php esc_html_e('Valid for (days)', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-validity" name="validity_days" step="1" min="0" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['validity_days'] ?? 0)); ?>">
							<span class="obydrelo-help"><?php esc_html_e('0 uses the program default.', 'obydullah-restaurant-loyalty'); ?></span>
						</p>
						<p>
							<label for="obydrelo-sort"><?php esc_html_e('Sort order', 'obydullah-restaurant-loyalty'); ?></label>
							<input type="number" id="obydrelo-sort" name="sort_order" step="1" class="regular-text"
								value="<?php echo esc_attr((string) ($obydrelo_reward['sort_order'] ?? 0)); ?>">
						</p>
						<p>
							<label>
								<input type="checkbox" name="is_active" value="1"
									<?php checked(!isset($obydrelo_reward['is_active']) || $obydrelo_reward['is_active']); ?>>
								<?php esc_html_e('Active', 'obydullah-restaurant-loyalty'); ?>
							</label>
						</p>
					</fieldset>

					<p>
						<label for="obydrelo-description"><?php esc_html_e('Description', 'obydullah-restaurant-loyalty'); ?></label>
						<textarea id="obydrelo-description" name="description" rows="3" class="large-text"><?php echo esc_textarea($obydrelo_reward['description'] ?? ''); ?></textarea>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Save reward', 'obydullah-restaurant-loyalty'); ?></button>
						<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-rewards')); ?>"><?php esc_html_e('Cancel', 'obydullah-restaurant-loyalty'); ?></a>
					</p>
				</form>
			</div>
		</div>

		<div class="obydrelo-column">
			<div class="obydrelo-panel">
				<h2><?php esc_html_e('All rewards', 'obydullah-restaurant-loyalty'); ?></h2>
				<?php if (!$obydrelo_rewards) : ?>
					<p class="obydrelo-empty"><?php esc_html_e('No rewards defined yet.', 'obydullah-restaurant-loyalty'); ?></p>
				<?php else : ?>
					<table class="widefat striped obydrelo-table">
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
						<?php foreach ($obydrelo_rewards as $obydrelo_row) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url(admin_url('admin.php?page=obydrelo-rewards&reward_id=' . $obydrelo_row['id'])); ?>">
										<?php echo esc_html($obydrelo_row['title']); ?>
									</a>
								</td>
								<td><?php echo esc_html($obydrelo_types[$obydrelo_row['type']] ?? $obydrelo_row['type']); ?></td>
								<td>
									<?php echo esc_html(OBYDRELO_Format::points($obydrelo_row['points_cost'])); ?> pts
									<?php if ($obydrelo_row['stamps_cost']) : ?>
										/ <?php echo esc_html((string) $obydrelo_row['stamps_cost']); ?> <?php esc_html_e('stamps', 'obydullah-restaurant-loyalty'); ?>
									<?php endif; ?>
									<?php if ($obydrelo_row['visits_cost']) : ?>
										/ <?php echo esc_html((string) $obydrelo_row['visits_cost']); ?> <?php esc_html_e('visits', 'obydullah-restaurant-loyalty'); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$obydrelo_stock = OBYDRELO_Rewards::stock_remaining($obydrelo_row);
									echo esc_html(
										null === $obydrelo_stock
											? __('Unlimited', 'obydullah-restaurant-loyalty')
											: number_format_i18n($obydrelo_stock) . ' / ' . number_format_i18n($obydrelo_row['usage_limit'])
									);
									?>
								</td>
								<td><?php echo $obydrelo_row['is_active'] ? '&#10003;' : '&mdash;'; ?></td>
								<td>
									<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="obydrelo-inline-form"
										onsubmit="return confirm('<?php echo esc_js(__('Remove this reward?', 'obydullah-restaurant-loyalty')); ?>');">
										<input type="hidden" name="action" value="obydrelo_delete_reward">
										<?php wp_nonce_field(OBYDRELO_NONCE_ADMIN); ?>
										<input type="hidden" name="reward_id" value="<?php echo esc_attr((string) $obydrelo_row['id']); ?>">
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
