<?php
/**
 * CSV export for the report range. Cells that could be interpreted as
 * spreadsheet formulas are prefixed with an apostrophe.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$today = date('Y-m-d');
$from = v_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-d', strtotime('-6 days'));
$to = v_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : $today;

if ($from > $to) {
    [$from, $to] = [$to, $from];
}
if (strtotime($from) < strtotime($to . ' -366 days')) {
    $from = date('Y-m-d', strtotime($to . ' -365 days'));
}

$orders = fetch_all(
    'SELECT * FROM orders WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY id',
    [$from, $to]
);

$escapeCell = static function (string $value): string {
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
};

$headers = [
    __('admin.order_ref'),
    __('admin.created'),
    __('admin.order_type'),
    __('common.status'),
    __('admin.payment_status'),
    __('common.customer'),
    __('common.phone'),
    __('common.subtotal'),
    __('common.discount'),
    __('common.delivery_fee'),
    __('common.tax'),
    __('common.total'),
    __('admin.amount_paid'),
    __('admin.balance'),
    __('admin.payment_method'),
    'source',
];

audit_log('report.exported', null, null, ['from' => $from, 'to' => $to, 'rows' => count($orders)]);

$filename = 'dhaka-hotel-report-' . $from . '_' . $to . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'wb');
if ($output === false) {
    exit;
}

// UTF-8 BOM so Excel renders Bangla text correctly.
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, array_map($escapeCell, $headers), ',', '"', '\\');

foreach ($orders as $order) {
    $balance = balance_due(
        cents_from_decimal((string) $order['grand_total']),
        cents_from_decimal((string) $order['amount_paid'])
    );

    $row = [
        (string) $order['order_number'],
        (string) $order['created_at'],
        order_type_label((string) $order['order_type']),
        order_status_label((string) $order['status']),
        payment_status_label((string) $order['payment_status']),
        (string) ($order['customer_name'] ?? ''),
        (string) ($order['customer_phone'] ?? ''),
        (string) $order['subtotal'],
        (string) $order['discount'],
        (string) $order['delivery_fee'],
        (string) $order['tax'],
        (string) $order['grand_total'],
        (string) $order['amount_paid'],
        decimal_from_cents($balance),
        $order['payment_method'] !== null ? payment_method_label((string) $order['payment_method']) : '',
        (string) $order['source'],
    ];

    fputcsv($output, array_map($escapeCell, $row), ',', '"', '\\');
}

fclose($output);
