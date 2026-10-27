<?php

declare(strict_types=1);

namespace Nitron\Sdk;

use RuntimeException;

/**
 * One error type for transport failures and non-success HTTP responses.
 *
 * status() is the HTTP status (0 when no response arrived).
 * code() is the API error code: JSON error_code, otherwise JSON code, otherwise the status.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
        private readonly ?array $body = null,
    ) {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    public function body(): ?array
    {
        return $this->body;
    }
}
