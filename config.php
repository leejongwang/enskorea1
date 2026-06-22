<?php

declare(strict_types=1);

/**
 * 다담 SEO 체크 설정
 *
 * payment_mode
 * - demo: 실제 돈이 오가지 않는 화면·기능 테스트 모드
 * - toss: 토스페이먼츠 테스트키 또는 라이브키 사용
 */
return [
    'app_name' => '다담 SEO 체크',
    'base_url' => '', // 비워두면 현재 도메인을 자동 인식합니다. 예: https://seo.example.com

    'payment_mode' => 'demo',
    'report_price' => 4900,
    'report_expire_days' => 30,
    'free_check_count' => 3,

    'storage_path' => __DIR__ . '/storage',

    'toss' => [
        // 토스페이먼츠 개발자센터의 API 개별 연동 키를 사용하세요.
        // 클라이언트 키는 브라우저에 노출되어도 되지만 시크릿 키는 서버에만 보관해야 합니다.
        'client_key' => '',
        'secret_key' => '',
    ],

    'rate_limit' => [
        'hourly' => 10,
        'daily' => 30,
    ],

    // 사이트 하단과 약관에 표시할 사업자 정보입니다. 실제 정보로 바꿔주세요.
    'business' => [
        'company' => '상호명 입력',
        'representative' => '대표자명 입력',
        'business_number' => '사업자등록번호 입력',
        'mail_order_number' => '통신판매업 신고번호 입력',
        'address' => '사업장 주소 입력',
        'phone' => '연락처 입력',
        'email' => '이메일 입력',
    ],
];
