<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$token = trim((string) ($_GET['token'] ?? ''));
$code = trim((string) ($_GET['code'] ?? 'PAYMENT_FAILED'));
$message = trim((string) ($_GET['message'] ?? '결제가 취소되었거나 인증에 실패했습니다.'));

renderHeader('결제 취소 또는 실패');
?>
<section class="simple-panel">
    <span class="eyebrow">PAYMENT FAILED</span>
    <h1>결제가 완료되지 않았어요.</h1>
    <p><?= e($message) ?></p>
    <small>오류 코드: <?= e($code) ?></small>
    <div class="simple-actions">
        <?php if ($token !== ''): ?>
            <a class="primary-link" href="<?= e(appUrl('checkout.php?token=' . rawurlencode($token))) ?>">다시 시도하기</a>
            <a class="ghost-link" href="<?= e(appUrl('report.php?token=' . rawurlencode($token))) ?>">무료 결과 보기</a>
        <?php else: ?>
            <a class="primary-link" href="<?= e(appUrl()) ?>">홈으로</a>
        <?php endif; ?>
    </div>
</section>
<?php renderFooter(); ?>
