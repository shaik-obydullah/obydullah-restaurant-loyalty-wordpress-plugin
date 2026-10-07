<?php
/**
 * Member card partial.
 *
 * @param array $obrl_card_args {
 *     @type array $member  Required member row.
 *     @type bool  $compact Render a smaller card.
 * }
 */
if (!defined('ABSPATH')) {
    exit;
}

$obrl_args = wp_parse_args(
    isset($obrl_card_args) ? $obrl_card_args : array(),
    array('member' => null, 'compact' => false)
);
$obrl_member = $obrl_args['member'];
if (!$obrl_member) {
    return;
}

$obrl_card_url = OBRL_Card::member_url($obrl_member);
$obrl_qr_text  = $obrl_card_url ? $obrl_card_url : $obrl_member['member_code'];
$obrl_compact  = !empty($obrl_args['compact']);
?>
<div class="orl-card<?php echo $obrl_compact ? ' orl-card--compact' : ''; ?>" data-qr-text="<?php echo esc_attr($obrl_qr_text); ?>">
	<div class="orl-card__qr" aria-hidden="true"></div>
	<div class="orl-card__body">
		<p class="orl-card__name"><?php echo esc_html(OBRL_Members::full_name($obrl_member)); ?></p>
		<p class="orl-card__code"><?php echo esc_html($obrl_member['member_code']); ?></p>
		<ul class="orl-card__stats">
			<li>
				<span class="orl-card__stat-value"><?php echo esc_html(OBRL_Format::points($obrl_member['points_balance'])); ?></span>
				<span class="orl-card__stat-label"><?php esc_html_e('Points', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
			<li>
				<span class="orl-card__stat-value"><?php echo esc_html((string) $obrl_member['stamps_count']); ?></span>
				<span class="orl-card__stat-label"><?php esc_html_e('Stamps', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
			<li>
				<span class="orl-card__stat-value"><?php echo esc_html((string) $obrl_member['visits_count']); ?></span>
				<span class="orl-card__stat-label"><?php esc_html_e('Visits', 'obydullah-restaurant-loyalty'); ?></span>
			</li>
		</ul>
		<?php if ('active' !== $obrl_member['status']) : ?>
			<p class="orl-card__status orl-status orl-status--<?php echo esc_attr($obrl_member['status']); ?>">
				<?php echo esc_html(OBRL_Members::statuses()[$obrl_member['status']] ?? $obrl_member['status']); ?>
			</p>
		<?php endif; ?>
		<p class="orl-card__url"><?php echo esc_html($obrl_qr_text); ?></p>
	</div>
</div>
