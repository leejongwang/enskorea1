<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
$business = (array) appConfig('business', []);
renderHeader('환불정책');
?>
<section class="legal-page">
    <span class="eyebrow">REFUND POLICY</span>
    <h1>취소 및 환불정책</h1>
    <p class="legal-updated">실제 운영 전 사업 형태와 전자상거래 관련 법령에 맞게 검토·수정하세요.</p>

    <h2>1. 결제 전</h2>
    <p>결제창에서 승인을 완료하기 전에는 언제든 결제를 취소할 수 있습니다.</p>

    <h2>2. 보고서 열람 전</h2>
    <p>결제는 완료됐으나 시스템 오류로 상세 보고서가 제공되지 않은 경우 운영자 확인 후 전액 환불하거나 보고서를 다시 제공할 수 있습니다.</p>

    <h2>3. 보고서 열람 후</h2>
    <p>상세 보고서가 정상적으로 잠금 해제되어 디지털 콘텐츠 제공이 시작된 뒤에는 단순 변심 환불이 제한될 수 있습니다. 다만 중복 결제, 결제 금액 오류, 서비스 미제공 등 운영자 귀책 사유가 확인되면 환불합니다.</p>

    <h2>4. 문의 방법</h2>
    <p>주문번호와 결제일을 포함해 <?= e((string) ($business['email'] ?? '')) ?> 또는 <?= e((string) ($business['phone'] ?? '')) ?>로 문의해 주세요.</p>
</section>
<?php renderFooter(); ?>
