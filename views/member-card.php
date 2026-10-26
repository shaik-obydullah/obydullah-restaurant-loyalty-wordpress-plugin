<?php
/**
 * Member card partial.
 *
 * @param array $obydrelo_card_args {
 *     @type array $member  Required member row.
 *     @type bool  $compact Render a smaller card.
 * }
 */
if (!defined('ABSPATH')) {
    exit;
}

$obydrelo_args = wp_parse_args(
    isset($obydrelo_card_args) ? $obydrelo_card_args : array(),
    array('member' => null, 'compact' => false)
);
$obydrelo_member = $obydrelo_args['member'];
if (!$obydrelo_member) {
    return;
}

$obydrelo_card_url = OBYDRELO_Card::member_url($obydrelo_member);
$obydrelo_qr_text  = $obydrelo_card_url ? $obydrelo_card_url : $obydrelo_member['member_code'];
$obydrelo_compact  = !empty($obydrelo_args['compact']);
?>
<div class="obydrelo-card<?php echo $obydrelo_compact ? ' obydrelo-card--compact' : ''; ?>" data-qr-text="<?php echo esc_attr($obydrelo_qr_text); ?>">
	<div class="obydrelo-card__qr" aria-hidden="true"></div>
	<div class="obydrelo-card__body">
		<p class="obydrelo-card__name"><?php echo esc_html(OBYDRELO_Members::full_name($obydrelo_member)); ?></p>
		<p class="obydrelo-card__code"><?php echo esc_html($obydrelo_member['member_code']); ?></p>
		<ul class="obydrelo-card__stats">
			<li>
				<span class="obydrelo-card__stat-value"><?php echo esc_html(OBYDRELO_Format::points($obydrelo_member['points_balance'])); ?></span>
				<span class="obydrelo-card__stat-label"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
			<li>
				<span class="obydrelo-card__stat-value"><?php echo esc_html((string) $obydrelo_member['stamps_count']); ?></span>
				<span class="obydrelo-card__stat-label"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
			<li>
				<span class="obydrelo-card__stat-value"><?php echo esc_html((string) $obydrelo_member['visits_count']); ?></span>
				<span class="obydrelo-card__stat-label"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
		</ul>
		<?php if ('active' !== $obydrelo_member['status']) : ?>
			<p class="obydrelo-card__status obydrelo-status obydrelo-status--<?php echo esc_attr($obydrelo_member['status']); ?>">
				<?php echo esc_html(OBYDRELO_Members::statuses()[$obydrelo_member['status']] ?? $obydrelo_member['status']); ?>
			</p>
		<?php endif; ?>
		<p class="obydrelo-card__url"><?php echo esc_html($obydrelo_qr_text); ?></p>
	</div>
</div>
