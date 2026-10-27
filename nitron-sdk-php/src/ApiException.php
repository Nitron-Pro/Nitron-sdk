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
    /** @var int */
    private $status;

    /** @var string */
    private $errorCode;

    /** @var array<mixed>|null */
    private $body;

    /**
     * @param array<mixed>|null $body
     */
    public function __construct(int $status, string $errorCode, string $message, ?array $body = null)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->body = $body;
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
