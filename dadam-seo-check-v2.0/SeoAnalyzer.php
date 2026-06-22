<?php

declare(strict_types=1);

final class SeoAnalyzer
{
    private const MAX_DOWNLOAD_BYTES = 2_000_000;
    private const CONNECT_TIMEOUT = 5;
    private const TOTAL_TIMEOUT = 12;

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $inputUrl, string $keyword = ''): array
    {
        $this->assertEnvironment();

        $url = $this->normalizeUrl($inputUrl);
        $keyword = trim($keyword);

        $this->assertSafePublicUrl($url);
        $response = $this->fetchHtml($url);

        if ($response['status'] >= 400) {
            throw new RuntimeException('대상 페이지가 HTTP ' . $response['status'] . ' 상태를 반환했습니다.');
        }

        $contentType = strtolower((string) ($response['content_type'] ?? ''));
        if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) {
            throw new RuntimeException('HTML 페이지가 아닙니다. 확인된 Content-Type: ' . $contentType);
        }

        $html = (string) $response['body'];
        if (trim($html) === '') {
            throw new RuntimeException('페이지 내용을 가져오지 못했습니다.');
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();

        if (!$loaded) {
            throw new RuntimeException('페이지 HTML을 해석하지 못했습니다.');
        }

        $xpath = new DOMXPath($dom);
        $finalUrl = (string) ($response['final_url'] ?? $url);
        $baseHost = strtolower((string) parse_url($finalUrl, PHP_URL_HOST));

        $title = $this->firstNodeText($xpath, '//title');
        $description = $this->metaContent($xpath, 'description');
        $robots = $this->metaContent($xpath, 'robots');
        $viewport = $this->metaContent($xpath, 'viewport');
        $canonical = $this->linkHrefByRel($xpath, 'canonical');
        $ogTitle = $this->metaPropertyContent($xpath, 'og:title');
        $ogDescription = $this->metaPropertyContent($xpath, 'og:description');

        $h1Texts = $this->nodeTexts($xpath, '//h1');
        $h2Texts = $this->nodeTexts($xpath, '//h2');

        $bodyText = $this->extractVisibleBodyText($dom, $xpath);
        $charCount = mb_strlen(preg_replace('/\s+/u', '', $bodyText) ?? '', 'UTF-8');
        $wordCount = $this->countWords($bodyText);

        $keywordCount = $keyword !== '' ? $this->countOccurrences($bodyText, $keyword) : 0;
        $keywordInTitle = $keyword !== '' && mb_stripos($title, $keyword, 0, 'UTF-8') !== false;
        $keywordInDescription = $keyword !== '' && mb_stripos($description, $keyword, 0, 'UTF-8') !== false;
        $keywordInH1 = $keyword !== '' && $this->arrayContainsKeyword($h1Texts, $keyword);

        $images = $this->inspectImages($xpath);
        $links = $this->inspectLinks($xpath, $baseHost);

        $noindex = preg_match('/(^|[\s,])noindex([\s,]|$)/i', $robots) === 1;
        $nofollow = preg_match('/(^|[\s,])nofollow([\s,]|$)/i', $robots) === 1;
        $hasStructuredData = $xpath->query('//script[@type="application/ld+json"]')->length > 0;
        $isHttps = strtolower((string) parse_url($finalUrl, PHP_URL_SCHEME)) === 'https';
        $hasLang = trim((string) $dom->documentElement?->getAttribute('lang')) !== '';

        $metrics = [
            'title' => $title,
            'title_length' => mb_strlen($title, 'UTF-8'),
            'description' => $description,
            'description_length' => mb_strlen($description, 'UTF-8'),
            'robots' => $robots,
            'viewport' => $viewport,
            'canonical' => $canonical,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'h1' => $h1Texts,
            'h1_count' => count($h1Texts),
            'h2' => $h2Texts,
            'h2_count' => count($h2Texts),
            'body_char_count' => $charCount,
            'body_word_count' => $wordCount,
            'keyword' => $keyword,
            'keyword_count' => $keywordCount,
            'keyword_in_title' => $keywordInTitle,
            'keyword_in_description' => $keywordInDescription,
            'keyword_in_h1' => $keywordInH1,
            'image_count' => $images['total'],
            'image_missing_alt' => $images['missing_alt'],
            'image_alt_rate' => $images['alt_rate'],
            'internal_links' => $links['internal'],
            'external_links' => $links['external'],
            'empty_links' => $links['empty'],
            'noindex' => $noindex,
            'nofollow' => $nofollow,
            'https' => $isHttps,
            'structured_data' => $hasStructuredData,
            'has_lang' => $hasLang,
            'status_code' => $response['status'],
            'load_time_ms' => $response['load_time_ms'],
            'page_size_kb' => round(strlen($html) / 1024, 1),
            'final_url' => $finalUrl,
        ];

        $checks = $this->buildChecks($metrics);
        $score = array_sum(array_column($checks, 'earned'));
        $maxScore = array_sum(array_column($checks, 'max'));
        $normalized = $maxScore > 0 ? (int) round(($score / $maxScore) * 100) : 0;

        usort($checks, static function (array $a, array $b): int {
            $priorityOrder = ['critical' => 0, 'warning' => 1, 'good' => 2, 'info' => 3];
            return ($priorityOrder[$a['status']] ?? 9) <=> ($priorityOrder[$b['status']] ?? 9);
        });

        return [
            'score' => max(0, min(100, $normalized)),
            'grade' => $this->grade(max(0, min(100, $normalized))),
            'metrics' => $metrics,
            'checks' => $checks,
            'summary' => [
                'good' => count(array_filter($checks, fn (array $c): bool => $c['status'] === 'good')),
                'warning' => count(array_filter($checks, fn (array $c): bool => $c['status'] === 'warning')),
                'critical' => count(array_filter($checks, fn (array $c): bool => $c['status'] === 'critical')),
            ],
        ];
    }

    private function assertEnvironment(): void
    {
        $missing = [];

        if (PHP_VERSION_ID < 80100) {
            $missing[] = 'PHP 8.1 이상';
        }
        if (!function_exists('curl_init')) {
            $missing[] = 'cURL';
        }
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            $missing[] = 'DOM/XML';
        }
        if (!function_exists('mb_strlen') || !function_exists('mb_stripos')) {
            $missing[] = 'mbstring';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                '서버 환경이 부족합니다: ' . implode(', ', $missing) . '. health.php에서 설치 상태를 확인해 주세요.'
            );
        }
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('분석할 홈페이지 주소를 입력해 주세요.');
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('올바른 홈페이지 주소를 입력해 주세요.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('HTTP 또는 HTTPS 주소만 분석할 수 있습니다.');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            throw new InvalidArgumentException('주소에서 도메인을 확인할 수 없습니다.');
        }

        // 사용자가 게시글·상품·관리자 페이지 주소를 붙여 넣어도
        // 경로·쿼리·해시를 제거하고 해당 도메인의 홈페이지부터 분석합니다.
        $port = parse_url($url, PHP_URL_PORT);
        $authority = $host . ($port !== null ? ':' . (int) $port : '');

        return $scheme . '://' . $authority . '/';
    }

    private function assertSafePublicUrl(string $url): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            throw new InvalidArgumentException('주소에서 도메인을 확인할 수 없습니다.');
        }

        $hostLower = strtolower($host);
        if ($hostLower === 'localhost' || str_ends_with($hostLower, '.local')) {
            throw new RuntimeException('로컬 네트워크 주소는 분석할 수 없습니다.');
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = gethostbynamel($host);
            if ($resolved === false || $resolved === []) {
                throw new RuntimeException('도메인의 IP 주소를 확인하지 못했습니다.');
            }
            $ips = $resolved;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new RuntimeException('보안을 위해 내부망·사설 IP 주소는 분석할 수 없습니다.');
            }
        }
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return array{status:int, body:string, content_type:string, final_url:string, load_time_ms:int}
     */
    private function fetchHtml(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('서버에서 PHP cURL 확장 기능을 사용할 수 없습니다.');
        }

        $body = '';
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('페이지 요청을 준비하지 못했습니다.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT,
            CURLOPT_USERAGENT => 'DadamSEOCheck/1.0 (+https://example.com)',
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.7',
                'Accept-Language: ko-KR,ko;q=0.9,en;q=0.7',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                $length = strlen($chunk);
                if (strlen($body) + $length > self::MAX_DOWNLOAD_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return $length;
            },
        ]);

        $startedAt = microtime(true);
        $ok = curl_exec($ch);
        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($ok === false) {
            if ($errno === CURLE_WRITE_ERROR && strlen($body) >= self::MAX_DOWNLOAD_BYTES) {
                throw new RuntimeException('페이지 용량이 너무 큽니다. 2MB 이하의 HTML 페이지만 분석할 수 있습니다.');
            }
            throw new RuntimeException('페이지를 불러오지 못했습니다: ' . ($error !== '' ? $error : '알 수 없는 오류'));
        }

        if ($finalUrl !== '') {
            $this->assertSafePublicUrl($finalUrl);
        }

        return [
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType,
            'final_url' => $finalUrl !== '' ? $finalUrl : $url,
            'load_time_ms' => $elapsed,
        ];
    }

    private function firstNodeText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        return $this->cleanText((string) $nodes->item(0)?->textContent);
    }

    /** @return list<string> */
    private function nodeTexts(DOMXPath $xpath, string $query): array
    {
        $results = [];
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return [];
        }
        foreach ($nodes as $node) {
            $text = $this->cleanText((string) $node->textContent);
            if ($text !== '') {
                $results[] = $text;
            }
        }
        return $results;
    }

    private function metaContent(DOMXPath $xpath, string $name): string
    {
        $query = sprintf('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]/@content', strtolower($name));
        $nodes = $xpath->query($query);
        return ($nodes !== false && $nodes->length > 0) ? trim((string) $nodes->item(0)?->nodeValue) : '';
    }

    private function metaPropertyContent(DOMXPath $xpath, string $property): string
    {
        $query = sprintf('//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]/@content', strtolower($property));
        $nodes = $xpath->query($query);
        return ($nodes !== false && $nodes->length > 0) ? trim((string) $nodes->item(0)?->nodeValue) : '';
    }

    private function linkHrefByRel(DOMXPath $xpath, string $rel): string
    {
        $query = sprintf('//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " %s ")]/@href', strtolower($rel));
        $nodes = $xpath->query($query);
        return ($nodes !== false && $nodes->length > 0) ? trim((string) $nodes->item(0)?->nodeValue) : '';
    }

    private function extractVisibleBodyText(DOMDocument $dom, DOMXPath $xpath): string
    {
        foreach (['//script', '//style', '//noscript', '//svg', '//template'] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false) {
                continue;
            }
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
            }
            foreach ($toRemove as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        return $this->cleanText((string) ($body?->textContent ?? ''));
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function countWords(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);
        return count($matches[0] ?? []);
    }

    private function countOccurrences(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }
        return mb_substr_count(mb_strtolower($haystack, 'UTF-8'), mb_strtolower($needle, 'UTF-8'), 'UTF-8');
    }

    /** @param list<string> $texts */
    private function arrayContainsKeyword(array $texts, string $keyword): bool
    {
        foreach ($texts as $text) {
            if (mb_stripos($text, $keyword, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    /** @return array{total:int, missing_alt:int, alt_rate:int} */
    private function inspectImages(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//img');
        if ($nodes === false || $nodes->length === 0) {
            return ['total' => 0, 'missing_alt' => 0, 'alt_rate' => 100];
        }

        $missing = 0;
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement || !$node->hasAttribute('alt') || trim($node->getAttribute('alt')) === '') {
                $missing++;
            }
        }

        $total = $nodes->length;
        return [
            'total' => $total,
            'missing_alt' => $missing,
            'alt_rate' => (int) round((($total - $missing) / $total) * 100),
        ];
    }

    /** @return array{internal:int, external:int, empty:int} */
    private function inspectLinks(DOMXPath $xpath, string $baseHost): array
    {
        $internal = 0;
        $external = 0;
        $empty = 0;
        $nodes = $xpath->query('//a[@href]');
        if ($nodes === false) {
            return compact('internal', 'external', 'empty');
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $href = trim($node->getAttribute('href'));
            if ($href === '' || $href === '#' || str_starts_with(strtolower($href), 'javascript:')) {
                $empty++;
                continue;
            }
            if (str_starts_with($href, '/') || str_starts_with($href, '#') || str_starts_with($href, '?')) {
                $internal++;
                continue;
            }
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if ($host === '' || $host === $baseHost || str_ends_with($host, '.' . $baseHost)) {
                $internal++;
            } else {
                $external++;
            }
        }

        return compact('internal', 'external', 'empty');
    }

    /**
     * @param array<string, mixed> $m
     * @return list<array<string, mixed>>
     */
    private function buildChecks(array $m): array
    {
        $checks = [];

        $this->addCheck($checks, '페이지 제목', 12,
            $m['title_length'] >= 15 && $m['title_length'] <= 60,
            $m['title_length'] === 0 ? '제목 태그가 없습니다.' : '제목 길이: ' . $m['title_length'] . '자',
            $m['title_length'] === 0 ? 'title 태그를 추가하세요.' : '검색 결과에서 잘리지 않도록 제목을 약 15~60자로 맞춰보세요.',
            $m['title_length'] === 0 ? 'critical' : 'warning'
        );

        $this->addCheck($checks, '메타 설명', 10,
            $m['description_length'] >= 70 && $m['description_length'] <= 160,
            $m['description_length'] === 0 ? '메타 설명이 없습니다.' : '메타 설명 길이: ' . $m['description_length'] . '자',
            '페이지 핵심 내용을 자연스럽게 설명하는 meta description을 약 70~160자로 작성하세요.',
            $m['description_length'] === 0 ? 'critical' : 'warning'
        );

        $this->addCheck($checks, 'H1 제목 구조', 10,
            $m['h1_count'] === 1,
            'H1 태그 ' . $m['h1_count'] . '개',
            $m['h1_count'] === 0 ? '페이지 주제를 나타내는 H1을 1개 추가하세요.' : 'H1은 핵심 제목 1개만 사용하는 편이 좋습니다.',
            $m['h1_count'] === 0 ? 'critical' : 'warning'
        );

        $this->addCheck($checks, '본문 분량', 8,
            $m['body_char_count'] >= 800,
            '공백 제외 본문 약 ' . number_format((int) $m['body_char_count']) . '자',
            '검색 의도를 충분히 해결하도록 고유한 설명과 예시를 보강하세요.',
            $m['body_char_count'] < 300 ? 'critical' : 'warning'
        );

        $this->addCheck($checks, '소제목 사용', 6,
            $m['h2_count'] >= 2,
            'H2 태그 ' . $m['h2_count'] . '개',
            '긴 본문은 H2 소제목으로 주제를 나눠 읽기 쉽게 구성하세요.',
            'warning'
        );

        $this->addCheck($checks, 'Canonical 주소', 8,
            $m['canonical'] !== '',
            $m['canonical'] !== '' ? 'Canonical이 설정되어 있습니다.' : 'Canonical이 없습니다.',
            '중복 URL 문제를 줄이려면 대표 주소를 가리키는 canonical 태그를 설정하세요.',
            'warning'
        );

        $this->addCheck($checks, '검색 색인 허용', 10,
            !$m['noindex'],
            $m['noindex'] ? 'robots 메타에 noindex가 있습니다.' : 'noindex가 감지되지 않았습니다.',
            '검색 노출이 목적이라면 robots 메타의 noindex를 제거하세요.',
            'critical'
        );

        $this->addCheck($checks, 'HTTPS 보안 연결', 8,
            $m['https'],
            $m['https'] ? 'HTTPS가 적용되어 있습니다.' : 'HTTP 주소입니다.',
            'SSL 인증서를 적용하고 HTTP를 HTTPS로 301 리디렉션하세요.',
            'critical'
        );

        $imageOk = $m['image_count'] === 0 || $m['image_alt_rate'] >= 80;
        $this->addCheck($checks, '이미지 ALT', 7,
            $imageOk,
            $m['image_count'] === 0
                ? '분석된 이미지가 없습니다.'
                : 'ALT 작성률 ' . $m['image_alt_rate'] . '% · 누락 ' . $m['image_missing_alt'] . '개',
            '의미 있는 이미지에는 내용을 설명하는 ALT 문구를 작성하세요.',
            'warning'
        );

        $this->addCheck($checks, '모바일 Viewport', 6,
            $m['viewport'] !== '',
            $m['viewport'] !== '' ? 'Viewport 설정이 있습니다.' : 'Viewport 설정이 없습니다.',
            '모바일 화면 대응을 위해 viewport 메타 태그를 추가하세요.',
            'warning'
        );

        $this->addCheck($checks, '언어 속성', 4,
            $m['has_lang'],
            $m['has_lang'] ? 'HTML lang 속성이 있습니다.' : 'HTML lang 속성이 없습니다.',
            '문서 언어에 맞게 html 태그에 lang="ko"를 추가하세요.',
            'warning'
        );

        $this->addCheck($checks, '구조화 데이터', 4,
            $m['structured_data'],
            $m['structured_data'] ? 'JSON-LD 구조화 데이터가 있습니다.' : '구조화 데이터가 없습니다.',
            '콘텐츠 유형에 맞는 Schema.org JSON-LD 적용을 검토하세요.',
            'info'
        );

        $this->addCheck($checks, '내부 링크', 4,
            $m['internal_links'] >= 2,
            '내부 링크 ' . $m['internal_links'] . '개',
            '관련 글이나 주요 페이지로 연결되는 내부 링크를 추가하세요.',
            'info'
        );

        if ($m['keyword'] !== '') {
            $this->addCheck($checks, '목표 키워드 배치', 9,
                $m['keyword_in_title'] && $m['keyword_in_h1'] && $m['keyword_count'] >= 2,
                '본문 ' . $m['keyword_count'] . '회 · 제목 ' . ($m['keyword_in_title'] ? '포함' : '미포함') . ' · H1 ' . ($m['keyword_in_h1'] ? '포함' : '미포함'),
                '목표 키워드를 제목과 H1에 자연스럽게 넣고 본문에는 억지스럽지 않게 사용하세요.',
                $m['keyword_count'] === 0 ? 'critical' : 'warning'
            );
        }

        return $checks;
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function addCheck(
        array &$checks,
        string $name,
        int $max,
        bool $pass,
        string $detail,
        string $action,
        string $failStatus
    ): void {
        $checks[] = [
            'name' => $name,
            'max' => $max,
            'earned' => $pass ? $max : 0,
            'status' => $pass ? 'good' : $failStatus,
            'detail' => $detail,
            'action' => $pass ? '현재 상태가 양호합니다.' : $action,
        ];
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => '최상',
            $score >= 80 => '양호',
            $score >= 65 => '보통',
            $score >= 45 => '개선 필요',
            default => '위험',
        };
    }
}
