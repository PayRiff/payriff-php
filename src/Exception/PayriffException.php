<?php

declare(strict_types=1);

namespace Payriff\Exception;

class PayriffException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 0,
        private readonly ?string $resultCode = null,
        private readonly ?string $responseId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getResultCode(): ?string
    {
        return $this->resultCode;
    }

    public function getResponseId(): ?string
    {
        return $this->responseId;
    }
}
