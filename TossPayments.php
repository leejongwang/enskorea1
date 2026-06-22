<?php

declare(strict_types=1);

final class TossPayments
{
    private const API_BASE = 'https://api.tosspayments.com';

    public function __construct(private readonly string $secretKey)
    {
        if ($secretKey === '') {
            throw new RuntimeException('토스페이먼츠 시크릿 키가 설정되지 않았습니다.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('서버에 PHP cURL 확장 기능이 필요합니다.');
        }
    }

    /** @return array<string, mixed> */
    public function confirm(string $paymentKey, string $orderId, int $amount): array
    {
        return $this->request('POST', '/v1/payments/confirm', [
            'paymentKey' => $paymentKey,
            'orderId' => $orderId,
            'amount' => $amount,
        ]);
    }

    /** @return array<string, mixed> */
    public function getByOrderId(string $orderId): array
    {
        return $this->request('GET', '/v1/payments/orders/' . rawurlencode($orderId));
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::API_BASE . $path);
        if ($ch === false) {
            throw new RuntimeException('결제 API 요청을 준비하지 못했습니다.');
        }

        $headers = [
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                curl_close($ch);
                throw new RuntimeException('결제 요청 데이터를 만들지 못했습니다.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('토스페이먼츠 서버에 연결하지 못했습니다: ' . $error);
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new RuntimeException('결제 API 응답 형식을 확인하지 못했습니다.');
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($data['message'] ?? '결제 승인에 실패했습니다.');
            $code = (string) ($data['code'] ?? 'PAYMENT_ERROR');
            throw new RuntimeException($message . ' [' . $code . ']');
        }

        return $data;
    }
}
