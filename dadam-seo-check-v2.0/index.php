<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/SeoAnalyzer.php';

$error = '';
$url = '';
$keyword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim((string) ($_POST['url'] ?? ''));
    $keyword = trim((string) ($_POST['keyword'] ?? ''));
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!verifyCsrf($token)) {
        $error = '요청이 만료되었습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.';
    } else {
        try {
            $limit = (array) appConfig('rate_limit', []);
            $limiter = new RateLimiter((string) appConfig('storage_path'));
            $limiter->consume(
                clientIp(),
                max(1, (int) ($limit['hourly'] ?? 10)),
                max(1, (int) ($limit['daily'] ?? 30))
            );

            $analyzer = new SeoAnalyzer();
            $result = $analyzer->analyze($url, $keyword);
            $report = reportStore()->create($result, $url, $keyword);
            redirectTo(appUrl('report.php?token=' . rawurlencode((string) $report['token'])));
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

renderHeader(
    (string) appConfig('app_name', '다담 SEO 체크'),
    '홈페이지 주소와 목표 키워드를 입력하면 메인 페이지의 SEO 상태를 실제 HTML 기준으로 무료 진단합니다.'
);
?>
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">무료 홈페이지 SEO 분석</span>
        <h1>내 홈페이지, 구글이<br><em>좋아할 상태일까?</em></h1>
        <p>홈페이지 주소 하나만 넣으면 제목, 본문 구조, 색인 설정, 이미지와 링크를 실제 HTML 기준으로 확인합니다. 기본 점수와 핵심 문제는 무료예요.</p>
        <div class="hero-pills">
            <span>기본 진단 무료</span>
            <span>회원가입 없음</span>
            <span>상세 보고서 선택 구매</span>
        </div>
    </div>

    <form class="analyze-form" method="post" data-analyze-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label for="url">분석할 홈페이지 주소</label>
        <div class="input-wrap">
            <span>↗</span>
            <input id="url" name="url" type="text" inputmode="url" autocomplete="url"
                   placeholder="https://example.com" value="<?= e($url) ?>" required>
        </div>

        <label for="keyword">목표 키워드 <small>선택 입력</small></label>
        <div class="input-wrap">
            <span>⌕</span>
            <input id="keyword" name="keyword" type="text" maxlength="80"
                   placeholder="예: 홈페이지 SEO 분석" value="<?= e($keyword) ?>">
        </div>

        <button type="submit" class="primary-button">
            <span class="button-text">무료 분석 시작</span>
            <span class="button-loader" aria-hidden="true"></span>
        </button>
        <p class="form-note">게시글 주소를 넣어도 해당 도메인의 홈페이지 첫 화면을 분석합니다.</p>
    </form>
</section>

<?php if ($error !== ''): ?>
    <section class="notice error" role="alert">
        <strong>분석하지 못했어요.</strong>
        <p><?= e($error) ?></p>
    </section>
<?php endif; ?>

<section class="feature-grid">
    <article><span>FREE</span><h2>기본 점수 공개</h2><p>종합 점수, 통과·개선·심각 항목 수와 우선 수정 문제 3개를 무료로 확인합니다.</p></article>
    <article><span>DETAIL</span><h2>상세 보고서</h2><p>전체 오류, 항목별 수정 방법, 키워드·이미지·링크·기술 SEO 정보를 결제 후 확인합니다.</p></article>
    <article><span>SECURE</span><h2>서버 결제 검증</h2><p>상세 결과는 화면에 숨겨두지 않고 서버에 보관하며 결제 승인이 끝난 뒤에만 전송합니다.</p></article>
</section>
<?php renderFooter(); ?>
