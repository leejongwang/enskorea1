<?php

declare(strict_types=1);

final class ReportStore
{
    private string $reportsPath;

    public function __construct(private readonly string $storagePath)
    {
        $this->reportsPath = rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'reports';
        $this->ensureDirectory($this->reportsPath);
    }

    /** @param array<string, mixed> $analysis */
    public function create(array $analysis, string $inputUrl, string $keyword): array
    {
        $token = bin2hex(random_bytes(32));
        $now = time();
        $days = max(1, (int) appConfig('report_expire_days', 30));

        $report = [
            'token' => $token,
            'created_at' => $now,
            'expires_at' => $now + ($days * 86400),
            'input_url' => $inputUrl,
            'keyword' => $keyword,
            'analysis' => $analysis,
            'paid' => false,
            'unlocked_at' => null,
            'order' => null,
            'payment' => null,
        ];

        $this->write($token, $report);
        return $report;
    }

    /** @return array<string, mixed>|null */
    public function get(string $token): ?array
    {
        if (!$this->validToken($token)) {
            return null;
        }

        $path = $this->path($token);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        if ((int) ($data['expires_at'] ?? 0) < time()) {
            @unlink($path);
            return null;
        }

        return $data;
    }

    /** @param array<string, mixed> $report */
    public function save(array $report): void
    {
        $token = (string) ($report['token'] ?? '');
        if (!$this->validToken($token)) {
            throw new RuntimeException('유효하지 않은 보고서 번호입니다.');
        }
        $this->write($token, $report);
    }

    /** @param array<string, mixed> $order */
    public function attachOrder(string $token, array $order): array
    {
        $report = $this->requireReport($token);
        $report['order'] = $order;
        $this->save($report);
        return $report;
    }

    /** @param array<string, mixed> $payment */
    public function unlock(string $token, array $payment): array
    {
        $report = $this->requireReport($token);
        $report['paid'] = true;
        $report['unlocked_at'] = time();
        $report['payment'] = $payment;
        if (is_array($report['order'])) {
            $report['order']['status'] = 'paid';
        }
        $this->save($report);
        return $report;
    }

    /** @return array<string, mixed> */
    private function requireReport(string $token): array
    {
        $report = $this->get($token);
        if ($report === null) {
            throw new RuntimeException('보고서를 찾을 수 없거나 보관 기간이 끝났습니다.');
        }
        return $report;
    }

    /** @param array<string, mixed> $report */
    private function write(string $token, array $report): void
    {
        $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('보고서 데이터를 저장하지 못했습니다.');
        }

        $path = $this->path($token);
        $temp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temp, $json, LOCK_EX) === false) {
            throw new RuntimeException('보고서 저장 폴더에 쓰기 권한이 없습니다.');
        }
        @chmod($temp, 0600);
        if (!rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('보고서 파일을 저장하지 못했습니다.');
        }
    }

    private function path(string $token): string
    {
        return $this->reportsPath . DIRECTORY_SEPARATOR . $token . '.json';
    }

    private function validToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('저장 폴더를 만들지 못했습니다: ' . $path);
        }
    }
}
