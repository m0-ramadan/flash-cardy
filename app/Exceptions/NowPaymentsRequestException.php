<?php

namespace App\Exceptions;

use RuntimeException;

class NowPaymentsRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array|string|null $responseBody = null
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): array|string|null
    {
        return $this->responseBody;
    }
}
