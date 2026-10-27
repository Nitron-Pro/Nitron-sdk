<?php

declare(strict_types=1);

namespace Nitron\Sdk\Tests;

use Nitron\Sdk\Transport;
use Nitron\Sdk\TransportResponse;

final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $calls = [];

    public function __construct(
        private readonly int $status,
        private readonly string $body,
    ) {
    }

    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return new TransportResponse($this->status, $this->body);
    }
}
