<?php
/**
 * Member card partial.
 *
 * @param array $bobydrelo_card_args {
 *     @type array $member  Required member row.
 *     @type bool  $compact Render a smaller card.
 * }
 */
if (!defined('ABSPATH')) {
    exit;
}

$bobydrelo_args = wp_parse_args(
    isset($bobydrelo_card_args) ? $bobydrelo_card_args : array(),
    array('member' => null, 'compact' => false)
);
$bobydrelo_member = $bobydrelo_args['member'];
if (!$bobydrelo_member) {
    return;
}

$bobydrelo_card_url = OBYDRELO_Card::member_url($bobydrelo_member);
$bobydrelo_qr_text  = $bobydrelo_card_url ? $bobydrelo_card_url : $bobydrelo_member['member_code'];
$bobydrelo_compact  = !empty($bobydrelo_args['compact']);
?>
<div class="obydrelo-card<?php echo $bobydrelo_compact ? ' obydrelo-card--compact' : ''; ?>" data-qr-text="<?php echo esc_attr($bobydrelo_qr_text); ?>">
	<div class="obydrelo-card__qr" aria-hidden="true"></div>
	<div class="obydrelo-card__body">
		<p class="obydrelo-card__name"><?php echo esc_html(OBYDRELO_Members::full_name($bobydrelo_member)); ?></p>
		<p class="obydrelo-card__code"><?php echo esc_html($bobydrelo_member['member_code']); ?></p>
		<ul class="obydrelo-card__stats">
			<li>
				<span class="obydrelo-card__stat-value"><?php echo esc_html(OBYDRELO_Format::points($bobydrelo_member['points_balance'])); ?></span>
				<span class="obydrelo-card__stat-label"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
			<li>
				<span class="obydrelo-card__stat-value"><?php echo esc_html((string) $bobydrelo_member['stamps_count']); ?></span>
				<span class="obydrelo-card__stat-label"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
			<li>
				<span class="obydrelo-card__stat-value"><?php echo esc_html((string) $bobydrelo_member['visits_count']); ?></span>
				<span class="obydrelo-card__stat-label"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
		</ul>
		<?php if ('active' !== $bobydrelo_member['status']) : ?>
			<p class="obydrelo-card__status obydrelo-status obydrelo-status--<?php echo esc_attr($bobydrelo_member['status']); ?>">
				<?php echo esc_html(OBYDRELO_Members::statuses()[$bobydrelo_member['status']] ?? $bobydrelo_member['status']); ?>
			</p>
		<?php endif; ?>
		<p class="obydrelo-card__url"><?php echo esc_html($bobydrelo_qr_text); ?></p>
	</div>
</div>
