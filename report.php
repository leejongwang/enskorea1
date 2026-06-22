<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$token = trim((string) ($_GET['token'] ?? ''));
$report = reportStore()->get($token);

if ($report === null) {
    http_response_code(404);
    renderHeader('보고서를 찾을 수 없음');
    ?>
    <section class="simple-panel">
        <span class="eyebrow">REPORT NOT FOUND</span>
        <h1>보고서를 찾을 수 없어요.</h1>
        <p>주소가 잘못됐거나 보고서 보관 기간이 끝났습니다.</p>
        <a class="primary-link" href="<?= e(appUrl()) ?>">새로 분석하기</a>
    </section>
    <?php
    renderFooter();
    exit;
}

$analysis = (array) ($report['analysis'] ?? []);
$metrics = (array) ($analysis['metrics'] ?? []);
$checks = array_values((array) ($analysis['checks'] ?? []));
$paid = (bool) ($report['paid'] ?? false);
$freeCount = max(1, (int) appConfig('free_check_count', 3));
$visibleChecks = $paid ? $checks : array_slice($checks, 0, $freeCount);
$hiddenCount = max(0, count($checks) - count($visibleChecks));
$host = (string) parse_url((string) ($metrics['final_url'] ?? ''), PHP_URL_HOST);

renderHeader(($paid ? '상세 SEO 보고서' : '무료 SEO 진단') . ' - ' . ($host !== '' ? $host : '홈페이지'));
?>
<section class="results report-page" id="results">
    <div class="result-head">
        <div>
            <span class="eyebrow"><?= $paid ? '상세 보고서 잠금 해제 완료' : '무료 분석 완료' ?></span>
            <h1><?= e($host !== '' ? $host : '홈페이지 분석') ?></h1>
            <a href="<?= e((string) ($metrics['final_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) ($metrics['final_url'] ?? '')) ?></a>
            <?php if ($paid): ?><span class="paid-badge">결제 완료 · 전체 결과 공개</span><?php endif; ?>
        </div>
        <div class="score-ring" style="--score: <?= (int) ($analysis['score'] ?? 0) ?>">
            <div>
                <strong><?= (int) ($analysis['score'] ?? 0) ?></strong>
                <span>/ 100</span>
                <small><?= e((string) ($analysis['grade'] ?? '')) ?></small>
            </div>
        </div>
    </div>

    <div class="summary-grid">
        <article><span class="summary-dot good"></span><strong><?= (int) (($analysis['summary']['good'] ?? 0)) ?></strong><small>통과 항목</small></article>
        <article><span class="summary-dot warning"></span><strong><?= (int) (($analysis['summary']['warning'] ?? 0)) ?></strong><small>개선 항목</small></article>
        <article><span class="summary-dot critical"></span><strong><?= (int) (($analysis['summary']['critical'] ?? 0)) ?></strong><small>심각한 문제</small></article>
        <article><span class="summary-dot info"></span><strong><?= number_format((int) ($metrics['load_time_ms'] ?? 0)) ?><small>ms</small></strong><small>HTML 응답 시간</small></article>
    </div>

    <div class="metric-grid free-metrics">
        <article class="metric-card wide"><span>페이지 제목</span><strong><?= e((string) (($metrics['title'] ?? '') ?: '제목 없음')) ?></strong><small><?= (int) ($metrics['title_length'] ?? 0) ?>자</small></article>
        <article class="metric-card wide"><span>메타 설명</span><strong><?= e((string) (($metrics['description'] ?? '') ?: '메타 설명 없음')) ?></strong><small><?= (int) ($metrics['description_length'] ?? 0) ?>자</small></article>
        <article class="metric-card"><span>H1 구조</span><strong><?= (int) ($metrics['h1_count'] ?? 0) ?>개</strong><small>권장 1개</small></article>
        <article class="metric-card"><span>HTTPS</span><strong><?= !empty($metrics['https']) ? '적용됨' : '미적용' ?></strong><small>보안 연결</small></article>
    </div>

    <?php if ($paid): ?>
        <div class="section-title">
            <div><span class="eyebrow">전체 세부 지표</span><h2>홈페이지 구성 한눈에 보기</h2></div>
            <button type="button" class="ghost-button" data-print>인쇄·PDF 저장</button>
        </div>
        <div class="metric-grid">
            <article class="metric-card"><span>본문 분량</span><strong><?= number_format((int) ($metrics['body_char_count'] ?? 0)) ?>자</strong><small>공백 제외</small></article>
            <article class="metric-card"><span>제목 구조</span><strong>H1 <?= (int) ($metrics['h1_count'] ?? 0) ?> · H2 <?= (int) ($metrics['h2_count'] ?? 0) ?></strong><small>HTML 헤딩 기준</small></article>
            <article class="metric-card"><span>이미지 ALT</span><strong><?= (int) ($metrics['image_alt_rate'] ?? 0) ?>%</strong><small><?= (int) ($metrics['image_missing_alt'] ?? 0) ?>개 누락</small></article>
            <article class="metric-card"><span>링크</span><strong>내부 <?= (int) ($metrics['internal_links'] ?? 0) ?> · 외부 <?= (int) ($metrics['external_links'] ?? 0) ?></strong><small>빈 링크 <?= (int) ($metrics['empty_links'] ?? 0) ?>개</small></article>
            <article class="metric-card"><span>Canonical</span><strong><?= e((string) (($metrics['canonical'] ?? '') ?: '없음')) ?></strong><small>대표 URL</small></article>
            <article class="metric-card"><span>검색 색인</span><strong><?= !empty($metrics['noindex']) ? '차단됨' : '허용 상태' ?></strong><small>meta robots 기준</small></article>
            <article class="metric-card"><span>모바일 설정</span><strong><?= !empty($metrics['viewport']) ? '설정됨' : '누락' ?></strong><small>viewport</small></article>
            <article class="metric-card"><span>구조화 데이터</span><strong><?= !empty($metrics['structured_data']) ? '발견됨' : '없음' ?></strong><small>JSON-LD 기준</small></article>
            <?php if ((string) ($metrics['keyword'] ?? '') !== ''): ?>
                <article class="metric-card wide"><span>목표 키워드</span><strong><?= e((string) $metrics['keyword']) ?></strong><small>본문 <?= (int) ($metrics['keyword_count'] ?? 0) ?>회 · 제목 <?= !empty($metrics['keyword_in_title']) ? '포함' : '미포함' ?> · H1 <?= !empty($metrics['keyword_in_h1']) ? '포함' : '미포함' ?></small></article>
            <?php endif; ?>
            <article class="metric-card"><span>HTML 용량</span><strong><?= e((string) ($metrics['page_size_kb'] ?? 0)) ?>KB</strong><small>수신 HTML</small></article>
            <article class="metric-card"><span>HTTP 상태</span><strong><?= (int) ($metrics['status_code'] ?? 0) ?></strong><small>최종 응답</small></article>
        </div>
    <?php endif; ?>

    <div class="section-title">
        <div>
            <span class="eyebrow"><?= $paid ? '전체 진단 결과' : '무료 공개 문제' ?></span>
            <h2><?= $paid ? '무엇부터 고치면 될까요?' : '우선 확인할 문제 ' . count($visibleChecks) . '개' ?></h2>
        </div>
        <?php if (!$paid): ?><span class="free-label">무료 공개</span><?php endif; ?>
    </div>

    <div class="check-list">
        <?php foreach ($visibleChecks as $check): ?>
            <article class="check-card <?= e((string) ($check['status'] ?? 'info')) ?>">
                <div class="status-icon" aria-hidden="true"><?= ($check['status'] ?? '') === 'good' ? '✓' : (($check['status'] ?? '') === 'critical' ? '!' : '•') ?></div>
                <div class="check-content">
                    <div class="check-title-row">
                        <h3><?= e((string) ($check['name'] ?? '')) ?></h3>
                        <span class="status-pill"><?= e(statusLabel((string) ($check['status'] ?? 'info'))) ?></span>
                    </div>
                    <p><?= e((string) ($check['detail'] ?? '')) ?></p>
                    <div class="action-box">
                        <strong><?= ($check['status'] ?? '') === 'good' ? '상태' : '수정 방법' ?></strong>
                        <span><?= e((string) ($check['action'] ?? '')) ?></span>
                    </div>
                </div>
                <div class="points"><?= (int) ($check['earned'] ?? 0) ?><small>/<?= (int) ($check['max'] ?? 0) ?></small></div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$paid): ?>
        <section class="paywall-card">
            <div class="lock-orb">🔒</div>
            <div class="paywall-copy">
                <span class="eyebrow">상세 보고서</span>
                <h2>아직 공개되지 않은 진단 <?= $hiddenCount ?>개가 있어요.</h2>
                <p>전체 문제와 수정 방법, 본문·키워드·이미지·링크·Canonical·색인 설정을 모두 확인할 수 있습니다.</p>
                <ul>
                    <li>전체 오류와 점수 계산 근거</li>
                    <li>항목별 구체적인 수정 방법</li>
                    <li>인쇄 및 PDF 저장</li>
                    <li>보고서 <?= (int) appConfig('report_expire_days', 30) ?>일 보관</li>
                </ul>
            </div>
            <div class="paywall-price">
                <small>1회 상세 보고서</small>
                <strong><?= e(formatWon((int) appConfig('report_price', 4900))) ?></strong>
                <a class="primary-link pay-button" href="<?= e(appUrl('checkout.php?token=' . rawurlencode($token))) ?>">전체 분석 열기</a>
                <span>결제 전에는 청구되지 않습니다.</span>
            </div>
        </section>
    <?php else: ?>
        <section class="unlocked-note">
            <strong>전체 보고서가 열렸습니다.</strong>
            <span>이 주소는 보관 기간 동안 다시 열 수 있으니 북마크해 두세요.</span>
        </section>
    <?php endif; ?>

    <aside class="disclaimer">이 점수는 페이지에서 자동 확인 가능한 기술·콘텐츠 항목을 기준으로 계산한 참고 지표입니다. 실제 검색 순위, 색인 여부, 매출을 보장하지 않습니다.</aside>
</section>
<?php renderFooter(); ?>
