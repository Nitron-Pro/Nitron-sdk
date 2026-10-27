<?php

declare(strict_types=1);

namespace Nitron\Sdk;

interface Transport
{
    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse;
}
