<?php

declare(strict_types=1);

namespace Nitron\Sdk;

final class TransportResponse
{
    /** @var int */
    public $status;

    /** @var string */
    public $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }
}
