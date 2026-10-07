<?php
if (!defined('ABSPATH')) {
    exit;
}

final class OBRL_CSV
{
    public static function handle_export()
    {
        // The caller (OBRL_Admin::handle_export) has already enforced the
        // capability and the nonce, so only the range needs reading here.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $report = isset($_REQUEST['report']) ? sanitize_text_field(wp_unslash($_REQUEST['report'])) : '30';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $report = in_array($report, array('7', '30', '90', '365'), true) ? $report : '30';

        $filename = 'loyalty-report-' . gmdate('Y-m-d') . '-' . $report . 'd.csv';
        self::stream($filename, self::rows($report));
    }

    public static function rows($days)
    {
        $report  = OBRL_Reports::build($days);
        $members = OBRL_Members::table();
        $tx      = OBRL_Schema::table('member_transactions');

        $rows = array();
        $rows[] = array(
            __('Date', 'obydullah-restaurant-loyalty'),
            __('Member code', 'obydullah-restaurant-loyalty'),
            __('Member name', 'obydullah-restaurant-loyalty'),
            __('Email', 'obydullah-restaurant-loyalty'),
            __('Type', 'obydullah-restaurant-loyalty'),
            __('Points', 'obydullah-restaurant-loyalty'),
            __('Stamps', 'obydullah-restaurant-loyalty'),
            __('Visits', 'obydullah-restaurant-loyalty'),
            __('Amount', 'obydullah-restaurant-loyalty'),
            __('Balance after', 'obydullah-restaurant-loyalty'),
            __('Staff', 'obydullah-restaurant-loyalty'),
            __('Note', 'obydullah-restaurant-loyalty'),
        );

        global $wpdb;
        if ($tx && $members) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- An export of the same aggregates. A cached copy would be exported as the file the member requests, which is worse than a slow export.
            $records = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        t.created_at, t.type, t.points, t.stamps, t.visits, t.amount,
                        t.points_after, t.staff_login, t.note,
                        m.member_code, m.first_name, m.last_name, m.email
                     FROM %i t
                     LEFT JOIN %i m ON m.id = t.member_id
                     WHERE t.created_at >= %s
                     ORDER BY t.created_at ASC",
                    $tx,
                    $members,
                    $report['since']
                )
            );

            $types = OBRL_Ledger::types();
            foreach ((array) $records as $row) {
                $rows[] = array(
                    $row->created_at,
                    $row->member_code,
                    trim($row->first_name . ' ' . $row->last_name),
                    $row->email,
                    $types[$row->type] ?? $row->type,
                    $row->points,
                    $row->stamps,
                    $row->visits,
                    $row->amount,
                    null === $row->points_after ? '' : $row->points_after,
                    $row->staff_login,
                    $row->note,
                );
            }
        }

        $rows[] = array();
        $rows[] = array(__('Summary', 'obydullah-restaurant-loyalty'));
        $rows[] = array(__('Recorded spend', 'obydullah-restaurant-loyalty'), $report['revenue']);
        $rows[] = array(__('Transactions', 'obydullah-restaurant-loyalty'), $report['transactions']);
        $rows[] = array(__('New members', 'obydullah-restaurant-loyalty'), $report['new_members']);
        $rows[] = array(__('Points earned', 'obydullah-restaurant-loyalty'), $report['points_earned']);
        $rows[] = array(__('Points redeemed', 'obydullah-restaurant-loyalty'), $report['points_redeemed']);
        $rows[] = array(__('Redemptions', 'obydullah-restaurant-loyalty'), $report['redemptions']);

        return $rows;
    }

    public static function stream($filename, $rows)
    {
        if (headers_sent()) {
            return;
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        if (false === $output) {
            return;
        }

        // BOM so spreadsheet apps detect UTF-8. php://output is not a file, so
        // the WP_Filesystem stream sniffs do not apply to this streaming export.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
        fputs($output, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        // php://output must be closed to flush the streamed rows before exit().
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($output);
        exit;
    }
}
