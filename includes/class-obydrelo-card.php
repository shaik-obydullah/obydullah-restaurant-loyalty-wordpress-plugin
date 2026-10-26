<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBYDRELO_Card
{
    /**
     * Canonical member card URL encoded in the QR code. Staff scanning the
     * card land here and the lookup happens on obydrelo_card.
     */
    public static function member_url($member)
    {
        if (empty($member['qr_token'])) {
            return '';
        }
        // add_query_arg() encodes the value itself, so encoding it here would
        // double-encode the token.
        return add_query_arg('obydrelo_card', $member['qr_token'], home_url('/'));
    }

    public static function lookup_from_request()
    {
        // The card token is itself the credential for this lookup and the caller
        // gates it behind a capability check, so no nonce applies.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['obydrelo_card'])) {
            return null;
        }
        $token = sanitize_text_field(wp_unslash($_GET['obydrelo_card']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ('' === $token) {
            return null;
        }
        return OBYDRELO_Members::get_by_qr_token($token);
    }

    public static function rotate_token($member_id)
    {
        global $wpdb;
        $table = OBYDRELO_Members::table();
        $token = wp_generate_password(48, false, false);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A QR token lookup on the scan path. Tokens are per member and change with the record, so a cached row could present a revoked card as valid.
        $result = $wpdb->update(
            $table,
            array('qr_token' => $token, 'updated_at' => current_time('mysql', true)),
            array('id' => (int) $member_id),
            array('%s', '%s'),
            array('%d')
        );
        if (false === $result) {
            return false;
        }
        OBYDRELO_Logger::log(
            'member_card_rotated',
            array('member_id' => (int) $member_id, 'object_type' => 'member', 'object_id' => (int) $member_id, 'severity' => 'warning')
        );
        return $token;
    }
}
