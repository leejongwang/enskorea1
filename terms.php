<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
$business = (array) appConfig('business', []);
renderHeader('이용약관');
?>
<section class="legal-page">
    <span class="eyebrow">TERMS OF SERVICE</span>
    <h1>이용약관</h1>
    <p class="legal-updated">시행일: 2026년 6월 22일</p>

    <h2>1. 서비스 내용</h2>
    <p><?= e((string) appConfig('app_name')) ?>는 사용자가 입력한 공개 홈페이지의 HTML을 자동 분석하여 참고용 SEO 점수와 개선 정보를 제공합니다.</p>

    <h2>2. 분석 결과의 한계</h2>
    <p>자동 분석 결과는 검색 순위, 검색엔진 색인, 매출 또는 광고 승인을 보장하지 않습니다. 로그인, 봇 차단, 자바스크립트 렌더링 등 외부 사이트 환경에 따라 일부 항목이 정확히 확인되지 않을 수 있습니다.</p>

    <h2>3. 유료 상세 보고서</h2>
    <p>유료 상세 보고서는 결제한 보고서 1건의 전체 분석 결과와 수정 안내를 제공하는 디지털 서비스입니다. 보고서 보관 기간은 생성일부터 <?= (int) appConfig('report_expire_days', 30) ?>일입니다.</p>

    <h2>4. 이용 제한</h2>
    <p>내부망 접근 시도, 자동화된 대량 요청, 서버 부하 유발, 타인의 권리를 침해하는 사용은 제한될 수 있습니다.</p>

    <h2>5. 운영자 정보</h2>
    <p>상호: <?= e((string) ($business['company'] ?? '')) ?><br>
       대표자: <?= e((string) ($business['representative'] ?? '')) ?><br>
       연락처: <?= e((string) ($business['phone'] ?? '')) ?><br>
       이메일: <?= e((string) ($business['email'] ?? '')) ?></p>
</section>
<?php renderFooter(); ?>
