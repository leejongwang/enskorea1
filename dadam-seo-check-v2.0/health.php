<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$checks = [
    'PHP 8.1 이상' => PHP_VERSION_ID >= 80100,
    'cURL 확장' => function_exists('curl_init'),
    'DOM/XML 확장' => class_exists('DOMDocument') && class_exists('DOMXPath'),
    'mbstring 확장' => function_exists('mb_strlen'),
    'JSON 지원' => function_exists('json_encode'),
    '세션 사용 가능' => session_status() === PHP_SESSION_ACTIVE,
    '저장 폴더 존재' => is_dir((string) appConfig('storage_path')),
    '저장 폴더 쓰기 가능' => is_writable((string) appConfig('storage_path')),
];

$mode = (string) appConfig('payment_mode', 'demo');
$paymentReady = $mode === 'demo' || (
    trim((string) appConfig('toss.client_key', '')) !== ''
    && trim((string) appConfig('toss.secret_key', '')) !== ''
);
$checks['결제 설정 (' . $mode . ')'] = $paymentReady;
$allOk = !in_array(false, $checks, true);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>서버 환경 점검</title>
    <style>
        body{margin:0;background:#f5f7fb;color:#172033;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans KR",sans-serif}.wrap{width:min(760px,calc(100% - 32px));margin:50px auto}.head,.card{background:#fff;border:1px solid #e5e9f2;border-radius:22px;padding:26px;box-shadow:0 18px 50px rgba(39,48,77,.08)}.head{margin-bottom:18px}.head h1{margin:8px 0}.ok{color:#15966a}.bad{color:#df4a5b}.row{display:flex;justify-content:space-between;gap:20px;padding:15px 0;border-bottom:1px solid #edf0f5}.row:last-child{border:0}.row strong:last-child{font-weight:900}.note{margin-top:20px;color:#687187;line-height:1.7}code{background:#f0f2f7;padding:3px 6px;border-radius:6px}
    </style>
</head>
<body><div class="wrap">
    <section class="head"><small>다담 SEO 체크 v2.0</small><h1 class="<?= $allOk ? 'ok' : 'bad' ?>"><?= $allOk ? '서버 사용 준비 완료' : '확인이 필요한 항목이 있습니다' ?></h1><p>PHP <?= e(PHP_VERSION) ?> · 결제 모드 <?= e($mode) ?></p></section>
    <section class="card">
        <?php foreach ($checks as $name => $ok): ?><div class="row"><strong><?= e($name) ?></strong><strong class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '정상' : '확인 필요' ?></strong></div><?php endforeach; ?>
        <p class="note">실제 토스 결제로 전환하려면 <code>config.php</code>의 payment_mode를 <code>toss</code>로 바꾸고 서로 짝이 맞는 클라이언트 키와 시크릿 키를 입력하세요. 점검이 끝나면 보안을 위해 health.php를 삭제하거나 접근을 제한하는 것이 좋습니다.</p>
    </section>
</div></body></html>
