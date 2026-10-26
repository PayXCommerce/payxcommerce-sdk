<?php

declare(strict_types=1);

namespace PayXCommerce\Exceptions;

class ApiException extends \RuntimeException
{
    private array $responseHeaders;
    private ?int $retryAfter;

    public function __construct(
        string $message,
        private ?int $statusCode = null,
        private ?string $payxErrorCode = null,
        private ?string $rawResponseBody = null,
        private array $errors = [],
        array $responseHeaders = [],
    ) {
        parent::__construct($message);
        $this->responseHeaders = \PayXCommerce\Http\ResponseMetadata::headers($responseHeaders);
        $this->retryAfter = \PayXCommerce\Http\ResponseMetadata::retryAfter($this->responseHeaders['retry-after'] ?? null);
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

    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfter;
    }
}
