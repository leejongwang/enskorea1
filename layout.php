<?php

declare(strict_types=1);

function renderHeader(string $title, string $description = ''): void
{
    $appName = (string) appConfig('app_name', '다담 SEO 체크');
    $fullTitle = $title === $appName ? $title : $title . ' | ' . $appName;
    ?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title><?= e($fullTitle) ?></title>
    <?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/style.css?v=2.0.0">
</head>
<body>
<div class="page-shell">
    <header class="topbar">
        <a class="brand" href="<?= e(appUrl()) ?>" aria-label="다담 SEO 체크 홈">
            <span class="brand-mark">D</span>
            <span>
                <strong><?= e($appName) ?></strong>
                <small>한눈에 보는 홈페이지 진단</small>
            </span>
        </a>
        <span class="beta-badge"><?= appConfig('payment_mode') === 'demo' ? 'DEMO MODE' : 'SEO REPORT' ?></span>
    </header>
    <main>
<?php
}

function renderFooter(): void
{
    ?>
    </main>
    <footer class="site-footer">
        <div>
            <strong><?= e((string) appConfig('app_name', '다담 SEO 체크')) ?></strong>
            <span>자동 분석 결과는 참고용이며 검색 순위를 보장하지 않습니다.</span>
        </div>
        <nav>
            <a href="<?= e(appUrl('terms.php')) ?>">이용약관</a>
            <a href="<?= e(appUrl('privacy.php')) ?>">개인정보처리방침</a>
            <a href="<?= e(appUrl('refund.php')) ?>">환불정책</a>
        </nav>
        <small><?= e(businessFooter()) ?></small>
    </footer>
</div>
<script src="assets/app.js?v=2.0.0"></script>
</body>
</html>
<?php
}
