<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/TossPayments.php';

$token = trim((string) ($_GET['token'] ?? ''));
$paymentKey = trim((string) ($_GET['paymentKey'] ?? ''));
$orderId = trim((string) ($_GET['orderId'] ?? ''));
$returnedAmount = (int) ($_GET['amount'] ?? 0);
$report = reportStore()->get($token);
$error = '';

try {
    if ($report === null) {
        throw new RuntimeException('보고서를 찾을 수 없거나 보관 기간이 끝났습니다.');
    }
    if (!empty($report['paid'])) {
        redirectTo(appUrl('report.php?token=' . rawurlencode($token)));
    }
    if ((string) appConfig('payment_mode') !== 'toss') {
        throw new RuntimeException('토스페이먼츠 결제 모드가 아닙니다.');
    }

    $order = is_array($report['order'] ?? null) ? $report['order'] : null;
    if ($order === null) {
        throw new RuntimeException('서버에 저장된 주문 정보를 찾을 수 없습니다.');
    }

    $storedOrderId = (string) ($order['id'] ?? '');
    $storedAmount = (int) ($order['amount'] ?? 0);
    if ($paymentKey === '' || $orderId === '') {
        throw new RuntimeException('결제 인증 정보가 비어 있습니다.');
    }
    if (!hash_equals($storedOrderId, $orderId)) {
        throw new RuntimeException('주문번호가 일치하지 않습니다.');
    }
    if ($returnedAmount !== $storedAmount) {
        throw new RuntimeException('결제 금액이 서버에 저장된 금액과 다릅니다.');
    }

    $client = new TossPayments((string) appConfig('toss.secret_key', ''));
    try {
        $payment = $client->confirm($paymentKey, $storedOrderId, $storedAmount);
    } catch (Throwable $confirmError) {
        // 새로고침이나 네트워크 오류로 승인 응답을 놓친 경우 주문 조회로 최종 상태를 재확인합니다.
        try {
            $payment = $client->getByOrderId($storedOrderId);
        } catch (Throwable) {
            throw $confirmError;
        }
    }

    $status = (string) ($payment['status'] ?? '');
    $approvedAmount = (int) ($payment['totalAmount'] ?? $payment['balanceAmount'] ?? 0);
    if ($status !== 'DONE') {
        throw new RuntimeException('결제가 최종 승인 상태가 아닙니다: ' . ($status !== '' ? $status : 'UNKNOWN'));
    }
    if ($approvedAmount !== $storedAmount) {
        throw new RuntimeException('승인된 결제 금액이 주문 금액과 일치하지 않습니다.');
    }
    if ((string) ($payment['orderId'] ?? '') !== $storedOrderId) {
        throw new RuntimeException('승인된 주문번호가 일치하지 않습니다.');
    }

    reportStore()->unlock($token, [
        'provider' => 'toss',
        'payment_key' => (string) ($payment['paymentKey'] ?? $paymentKey),
        'order_id' => $storedOrderId,
        'amount' => $storedAmount,
        'status' => $status,
        'method' => (string) ($payment['method'] ?? ''),
        'approved_at' => (string) ($payment['approvedAt'] ?? date(DATE_ATOM)),
        'receipt_url' => (string) (($payment['receipt']['url'] ?? '')),
    ]);

    redirectTo(appUrl('report.php?token=' . rawurlencode($token)));
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

http_response_code(400);
renderHeader('결제 승인 확인 필요');
?>
<section class="simple-panel">
    <span class="eyebrow">PAYMENT CHECK</span>
    <h1>결제를 완료하지 못했어요.</h1>
    <p><?= e($error) ?></p>
    <div class="simple-actions">
        <a class="primary-link" href="<?= e(appUrl('checkout.php?token=' . rawurlencode($token))) ?>">다시 결제하기</a>
        <a class="ghost-link" href="<?= e(appUrl('report.php?token=' . rawurlencode($token))) ?>">무료 결과로 돌아가기</a>
    </div>
</section>
<?php renderFooter(); ?>
