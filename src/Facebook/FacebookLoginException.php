<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Facebook;

final class FacebookLoginException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $channel, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getChannel(): string
    {
        return $this->channel;
    }
}
