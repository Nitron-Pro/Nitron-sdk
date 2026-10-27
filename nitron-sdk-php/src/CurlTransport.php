<?php

declare(strict_types=1);

namespace Nitron\Sdk;

final class CurlTransport implements Transport
{
    public function __construct(private readonly int $timeoutSeconds = 60)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new ApiException(0, 'transport', 'curl_init failed', null);
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);
        $raw = curl_exec($handle);
        if ($raw === false) {
            $message = curl_error($handle);
            unset($handle);
            throw new ApiException(0, 'transport', $message !== '' ? $message : 'network error', null);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        unset($handle);

        return new TransportResponse($status, is_string($raw) ? $raw : '');
    }
}
