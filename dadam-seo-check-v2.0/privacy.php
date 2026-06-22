<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
$business = (array) appConfig('business', []);
renderHeader('개인정보처리방침');
?>
<section class="legal-page">
    <span class="eyebrow">PRIVACY POLICY</span>
    <h1>개인정보처리방침</h1>
    <p class="legal-updated">시행일: 2026년 6월 22일</p>

    <h2>1. 처리하는 정보</h2>
    <p>서비스 운영 과정에서 분석 대상 URL, 목표 키워드, 접속 IP, 접속 시간, 결제 주문번호 및 결제 상태가 처리될 수 있습니다. 카드번호 등 결제수단 정보는 토스페이먼츠가 처리하며 본 서비스 서버에 저장하지 않습니다.</p>

    <h2>2. 이용 목적</h2>
    <p>홈페이지 분석 제공, 부정 이용 방지, 사용량 제한, 결제 승인 확인, 고객 문의 처리 목적으로 정보를 이용합니다.</p>

    <h2>3. 보관 기간</h2>
    <p>분석 보고서는 생성일부터 <?= (int) appConfig('report_expire_days', 30) ?>일간 보관한 뒤 삭제할 수 있습니다. 결제 관련 정보는 관계 법령상 필요한 기간 동안 별도로 보관할 수 있습니다.</p>

    <h2>4. 제3자 제공 및 처리 위탁</h2>
    <p>결제 서비스 이용 시 결제 처리를 위해 토스페이먼츠에 필요한 주문 및 결제 정보가 전달됩니다.</p>

    <h2>5. 문의</h2>
    <p>개인정보 관련 문의: <?= e((string) ($business['email'] ?? '')) ?> / <?= e((string) ($business['phone'] ?? '')) ?></p>
</section>
<?php renderFooter(); ?>
