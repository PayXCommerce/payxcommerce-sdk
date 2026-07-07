<?php

declare(strict_types=1);

namespace PayXCommerce\Exceptions;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?string $payxErrorCode = null,
        private readonly ?string $rawResponseBody = null,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function payxErrorCode(): ?string
    {
        return $this->payxErrorCode;
    }

    public function rawResponseBody(): ?string
    {
        return $this->rawResponseBody;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
