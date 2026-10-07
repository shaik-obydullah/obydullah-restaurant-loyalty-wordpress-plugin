<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_Format
{
    public static function money($amount)
    {
        $currency = (string) OBRL_Settings::get('currency_code', '');
        $decimals = (int) get_option('woocommerce_currency_num_decimal', 2);
        $formatted = number_format_i18n((float) $amount, $decimals);
        $position = (string) get_option('woocommerce_currency_pos', 'left');
        $sep = (string) get_option('woocommerce_currency_separator', '.');

        if ('' === $currency) {
            return $formatted;
        }
        if ('right' === $position) {
            return $formatted . $sep . $currency;
        }
        if ('right_space' === $position) {
            return $formatted . ' ' . $currency;
        }
        if ('left_space' === $position) {
            return $currency . ' ' . $formatted;
        }
        return $currency . $sep . $formatted;
    }

    public static function points($amount)
    {
        $value = (float) $amount;
        return (floor($value) === $value && abs($value) < PHP_INT_MAX)
            ? number_format_i18n((int) $value)
            : number_format_i18n($value, 2);
    }

    public static function signed_points($amount)
    {
        $amount = (float) $amount;
        $label = self::points(abs($amount));
        if ($amount > 0) {
            return '+' . $label;
        }
        if ($amount < 0) {
            return '-' . $label;
        }
        return $label;
    }

    public static function date($value, $format = '')
    {
        if (empty($value) || '0000-00-00 00:00:00' === $value) {
            return '—';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '—';
        }
        $format = $format ? $format : get_option('date_format', 'Y-m-d');
        return date_i18n($format, $timestamp);
    }

    public static function datetime($value)
    {
        if (empty($value) || '0000-00-00 00:00:00' === $value) {
            return '—';
        }
        $timestamp = strtotime($value);
        if (!$timestamp) {
            return '—';
        }
        return date_i18n(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $timestamp);
    }

    public static function unit_label($plural = false)
    {
        $mode = (string) OBRL_Settings::get('program_mode', 'points');
        $map = array(
            'points' => 'points_label',
            'stamps' => 'stamps_label',
            'visits' => 'visits_label',
        );
        if (!isset($map[$mode])) {
            $mode = 'points';
        }
        return (string) OBRL_Settings::get($map[$mode], ucfirst($mode));
    }
}
