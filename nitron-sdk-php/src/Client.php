<?php

declare(strict_types=1);

namespace Nitron\Sdk;

use InvalidArgumentException;

/**
 * HTTP client for /api/v1/gateway.
 *
 * Money fields are integer rials, the same numbers the API returns.
 * The base URL is the origin only, for example https://api.example.ir.
 */
final class Client
{
    public const VERSION = '1.0.0';

    private const GATEWAY = '/api/v1/gateway';

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /** @var Transport */
    private $transport;

    public function __construct(string $baseUrl, string $apiKey, ?Transport $transport = null, int $timeoutSeconds = 60)
    {
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            throw new InvalidArgumentException('base URL is empty');
        }
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
        $this->transport = $transport ?? new CurlTransport($timeoutSeconds);
    }

    /**
     * Call any gateway route. $path is absolute from the origin
     * (/api/v1/gateway/...) or relative to that prefix (usage, telegram/messages).
     *
     * @param array<string, mixed>|null $json
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     * @return array<mixed>
     */
    public function request(string $method, string $path, ?array $json = null, array $query = [], array $headers = []): array
    {
        $method = strtoupper($method);
        $url = $this->url($this->expandPath($path), $query);

        $sent = [
            'Accept' => 'application/json',
            'User-Agent' => 'nitron-sdk-php/' . self::VERSION,
        ];
        foreach ($headers as $name => $value) {
            $sent[$name] = $value;
        }
        $sent['X-API-Key'] = $this->apiKey;

        $rawBody = null;
        if ($json !== null) {
            $rawBody = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $sent['Content-Type'] = 'application/json; charset=utf-8';
        }

        $response = $this->transport->send($method, $url, $sent, $rawBody);
        if ($response->status === 0) {
            throw new ApiException(0, 'transport', $response->body !== '' ? $response->body : 'empty response', null);
        }

        $decoded = json_decode($response->body, true, 512, JSON_BIGINT_AS_STRING);
        $payload = is_array($decoded) ? $decoded : null;

        if ($response->status < 200 || $response->status >= 300) {
            $this->reject($response->status, $response->body, $payload);
        }
        if ($payload === null) {
            throw new ApiException($response->status, (string) $response->status, 'response is not a JSON object or array', null);
        }
        $marker = $payload['status'] ?? null;
        if ($marker === 'error' || $marker === 'unknown') {
            $this->reject($response->status, $response->body, $payload);
        }

        return $payload;
    }

    /**
     * POST /api/v1/gateway/hub/execute
     *
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function execute(array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', self::GATEWAY . '/hub/execute', $body, [], $this->idempotency($idempotencyKey, $body));
    }

    /**
     * GET /api/v1/gateway/me
     *
     * @return array<mixed>
     */
    public function me(): array
    {
        return $this->request('GET', self::GATEWAY . '/me');
    }

    /**
     * GET /api/v1/gateway/wallet/balance
     *
     * Balances are integer rials.
     *
     * @return array<mixed>
     */
    public function walletBalance(): array
    {
        return $this->request('GET', self::GATEWAY . '/wallet/balance');
    }

    /**
     * GET /api/v1/gateway/services. Only services this key may spend, with rial unit_price.
     *
     * @return array<mixed>
     */
    public function services(): array
    {
        return $this->request('GET', self::GATEWAY . '/services');
    }

    /**
     * GET /api/v1/gateway/usage. Query keys: from, to, service_code, idempotency_key, page, per_page.
     *
     * @param array<string, scalar|null> $query
     * @return array<mixed>
     */
    public function usage(array $query = []): array
    {
        return $this->request('GET', self::GATEWAY . '/usage', null, $query);
    }

    /**
     * GET /api/v1/gateway/wallet/transactions.
     * Query keys: from, to, service_code, idempotency_key, type (debit|credit), page, per_page.
     *
     * @param array<string, scalar|null> $query
     * @return array<mixed>
     */
    public function walletTransactions(array $query = []): array
    {
        return $this->request('GET', self::GATEWAY . '/wallet/transactions', null, $query);
    }

    /**
     * POST /api/v1/gateway/payment/request. amount is rials. data.payment_url, data.id, data.callback_url.
     *
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function requestPayment(array $body): array
    {
        return $this->request('POST', self::GATEWAY . '/payment/request', $body);
    }

    /**
     * GET /api/v1/gateway/payment/{authority}. data.paid is true after the wallet is credited.
     *
     * @return array<mixed>
     */
    public function paymentStatus(string $authority): array
    {
        return $this->request('GET', self::GATEWAY . '/payment/' . rawurlencode($authority));
    }

    /**
     * GET /api/v1/gateway/documents/{id}. data.html is the document. format=html returns the file.
     *
     * @param array<string, scalar|null> $query
     * @return array<mixed>
     */
    public function document(string $id, array $query = []): array
    {
        return $this->request('GET', self::GATEWAY . '/documents/' . rawurlencode($id), null, $query);
    }

    /**
     * POST /api/v1/gateway/sms/send
     *
     * OTP uses to, template_code, code and service_code sms_otp.
     *
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function sendSms(array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', self::GATEWAY . '/sms/send', $body, [], $this->idempotency($idempotencyKey, $body));
    }

    /**
     * POST /api/v1/gateway/telegram/send
     *
     * Sends Idempotency-Key when $idempotencyKey or body idempotency_key is set.
     *
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function sendTelegram(array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', self::GATEWAY . '/telegram/send', $body, [], $this->idempotency($idempotencyKey, $body));
    }

    /**
     * POST /api/v1/gateway/bale/send
     *
     * Sends Idempotency-Key when $idempotencyKey or body idempotency_key is set.
     *
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function sendBale(array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', self::GATEWAY . '/bale/send', $body, [], $this->idempotency($idempotencyKey, $body));
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function url(string $path, array $query): string
    {
        $url = $this->baseUrl . $path;
        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function expandPath(string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException('path is empty');
        }
        if ($path === '' || $path[0] !== '/') {
            $path = self::GATEWAY . '/' . ltrim($path, '/');
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, string>
     */
    private function idempotency(?string $key, array $body): array
    {
        if ($key === null || $key === '') {
            $fromBody = $body['idempotency_key'] ?? null;
            $key = is_string($fromBody) ? $fromBody : null;
        }
        if ($key === null || $key === '') {
            return [];
        }
        if (preg_match("/[\r\n]/", $key) === 1) {
            throw new InvalidArgumentException('Idempotency-Key must be a single line');
        }

        return ['Idempotency-Key' => $key];
    }

    /**
     * @param array<mixed>|null $payload
     */
    private function reject(int $status, string $raw, ?array $payload): void
    {
        $code = (string) $status;
        $message = '';
        if ($payload !== null) {
            $errorCode = $payload['error_code'] ?? null;
            $numeric = $payload['code'] ?? null;
            if (is_string($errorCode) && $errorCode !== '') {
                $code = $errorCode;
            } elseif ((is_string($numeric) || is_int($numeric)) && (string) $numeric !== '') {
                $code = (string) $numeric;
            }
            if (isset($payload['message']) && is_string($payload['message']) && $payload['message'] !== '') {
                $message = $payload['message'];
            }
        }
        if ($message === '') {
            $trimmed = trim($raw);
            $message = $trimmed !== '' ? $trimmed : 'HTTP ' . $status;
        }

        throw new ApiException($status, $code, $message, $payload);
    }
}
