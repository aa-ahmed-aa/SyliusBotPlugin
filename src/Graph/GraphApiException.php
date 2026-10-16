<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Graph;

/**
 * An error answered by the Graph API, or a call that never got an answer.
 */
final class GraphApiException extends \RuntimeException
{
    /** Graph error code for an access token that expired, was revoked or whose session ended. */
    public const CODE_INVALID_TOKEN = 190;

    public function __construct(
        string $message,
        private readonly ?int $graphCode = null,
        private readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getGraphCode(): ?int
    {
        return $this->graphCode;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isInvalidToken(): bool
    {
        return $this->graphCode === self::CODE_INVALID_TOKEN;
    }
}
