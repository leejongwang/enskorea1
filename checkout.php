<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$token = trim((string) ($_GET['token'] ?? ''));
$report = reportStore()->get($token);

if ($report === null) {
    redirectTo(appUrl());
}
if (!empty($report['paid'])) {
    redirectTo(appUrl('report.php?token=' . rawurlencode($token)));
}

$price = (int) appConfig('report_price', 4900);
$mode = (string) appConfig('payment_mode', 'demo');
$order = is_array($report['order'] ?? null) ? $report['order'] : null;

if ($order === null || (string) ($order['status'] ?? '') === 'failed' || (int) ($order['amount'] ?? 0) !== $price) {
    $order = [
        'id' => 'DADAM-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)),
        'amount' => $price,
        'status' => 'ready',
        'created_at' => time(),
    ];
    $report = reportStore()->attachOrder($token, $order);
}

$clientKey = trim((string) appConfig('toss.client_key', ''));
$configError = '';
if ($mode === 'toss' && $clientKey === '') {
    $configError = 'config.php에 토스페이먼츠 클라이언트 키를 입력해 주세요.';
}

$analysis = (array) ($report['analysis'] ?? []);
$metrics = (array) ($analysis['metrics'] ?? []);
$host = (string) parse_url((string) ($metrics['final_url'] ?? ''), PHP_URL_HOST);

renderHeader('상세 SEO 보고서 결제');
?>
<section class="checkout-shell">
    <div class="checkout-card">
        <div>
            <span class="eyebrow">상세 보고서 잠금 해제</span>
            <h1><?= e($host !== '' ? $host : '홈페이지') ?></h1>
            <p>무료 진단에서 숨겨진 전체 오류와 구체적인 수정 방법을 확인합니다.</p>
        </div>
        <div class="checkout-summary">
            <div><span>상품</span><strong>상세 SEO 분석 보고서 1건</strong></div>
            <div><span>보관 기간</span><strong><?= (int) appConfig('report_expire_days', 30) ?>일</strong></div>
            <div class="checkout-total"><span>결제 금액</span><strong><?= e(formatWon($price)) ?></strong></div>
        </div>

        <div class="checkout-benefits">
            <span>✓ 전체 진단 항목 공개</span>
            <span>✓ 항목별 수정 방법</span>
            <span>✓ 인쇄·PDF 저장</span>
        </div>

        <?php if ($configError !== ''): ?>
            <div class="notice error"><strong>결제 설정이 필요합니다.</strong><p><?= e($configError) ?></p></div>
        <?php elseif ($mode === 'demo'): ?>
            <div class="demo-banner">
                <strong>현재 데모 결제 모드입니다.</strong>
                <span>실제 돈은 결제되지 않고 버튼을 누르면 상세 결과만 열립니다.</span>
            </div>
            <form method="post" action="<?= e(appUrl('demo-unlock.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="primary-button pay-now">데모로 <?= e(formatWon($price)) ?> 결제 완료 처리</button>
            </form>
        <?php else: ?>
            <button type="button" id="payment-button" class="primary-button pay-now">카드·간편결제로 <?= e(formatWon($price)) ?> 결제</button>
            <p id="payment-error" class="inline-error" hidden></p>
            <script src="https://js.tosspayments.com/v2/standard"></script>
            <script>
            (() => {
                const button = document.getElementById('payment-button');
                const errorBox = document.getElementById('payment-error');
                const clientKey = <?= json_encode($clientKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                const tossPayments = TossPayments(clientKey);
                const payment = tossPayments.payment({ customerKey: TossPayments.ANONYMOUS });

                button.addEventListener('click', async () => {
                    button.disabled = true;
                    errorBox.hidden = true;
                    try {
                        await payment.requestPayment({
                            method: 'CARD',
                            amount: { currency: 'KRW', value: <?= $price ?> },
                            orderId: <?= json_encode((string) $order['id']) ?>,
                            orderName: '상세 SEO 분석 보고서',
                            successUrl: <?= json_encode(appUrl('payment-success.php?token=' . rawurlencode($token))) ?>,
                            failUrl: <?= json_encode(appUrl('payment-fail.php?token=' . rawurlencode($token))) ?>,
                            card: {
                                flowMode: 'DEFAULT',
                                useCardPoint: false,
                                useAppCardOnly: false
                            }
                        });
                    } catch (error) {
                        if (error && error.code === 'USER_CANCEL') {
                            errorBox.textContent = '결제가 취소되었습니다.';
                        } else {
                            errorBox.textContent = error?.message || '결제창을 열지 못했습니다.';
                        }
                        errorBox.hidden = false;
                        button.disabled = false;
                    }
                });
            })();
            </script>
        <?php endif; ?>

        <div class="checkout-links">
            <a href="<?= e(appUrl('report.php?token=' . rawurlencode($token))) ?>">무료 결과로 돌아가기</a>
            <a href="<?= e(appUrl('refund.php')) ?>">환불정책</a>
        </div>
    </div>
</section>
<?php renderFooter(); ?>
