<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if ((string) appConfig('payment_mode', 'demo') !== 'demo') {
    http_response_code(403);
    exit('데모 결제 모드가 아닙니다.');
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
$token = trim((string) ($_POST['token'] ?? ''));
if (!verifyCsrf($csrf)) {
    http_response_code(403);
    exit('요청이 만료되었습니다.');
}

$report = reportStore()->get($token);
if ($report === null) {
    http_response_code(404);
    exit('보고서를 찾을 수 없습니다.');
}

$order = is_array($report['order'] ?? null) ? $report['order'] : [];
reportStore()->unlock($token, [
    'provider' => 'demo',
    'payment_key' => 'DEMO-' . bin2hex(random_bytes(8)),
    'order_id' => (string) ($order['id'] ?? ''),
    'amount' => (int) ($order['amount'] ?? appConfig('report_price', 4900)),
    'status' => 'DONE',
    'approved_at' => date(DATE_ATOM),
]);

redirectTo(appUrl('report.php?token=' . rawurlencode($token)));
