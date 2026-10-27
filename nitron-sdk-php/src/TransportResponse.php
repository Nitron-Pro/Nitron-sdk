<?php

declare(strict_types=1);

namespace Nitron\Sdk;

final class TransportResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
