<?php

declare(strict_types=1);

final class RateLimiter
{
    private string $path;

    public function __construct(string $storagePath)
    {
        $this->path = rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'limits';
        if (!is_dir($this->path) && !mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException('사용량 제한 폴더를 만들지 못했습니다.');
        }
    }

    public function consume(string $identifier, int $hourlyLimit, int $dailyLimit): void
    {
        $key = hash('sha256', $identifier);
        $file = $this->path . DIRECTORY_SEPARATOR . $key . '.json';
        $now = time();

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('사용량 제한 정보를 확인하지 못했습니다.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('잠시 후 다시 시도해 주세요.');
            }

            $raw = stream_get_contents($handle);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($data)) {
                $data = [];
            }

            $events = array_values(array_filter(
                (array) ($data['events'] ?? []),
                static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $now - 86400
            ));

            $hourly = count(array_filter($events, static fn (int $timestamp): bool => $timestamp >= $now - 3600));
            $daily = count($events);

            if ($hourly >= $hourlyLimit) {
                throw new RuntimeException('한 시간 무료 분석 횟수를 모두 사용했습니다. 잠시 후 다시 시도해 주세요.');
            }
            if ($daily >= $dailyLimit) {
                throw new RuntimeException('오늘 무료 분석 횟수를 모두 사용했습니다. 내일 다시 이용해 주세요.');
            }

            $events[] = $now;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(['events' => $events], JSON_UNESCAPED_SLASHES));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
