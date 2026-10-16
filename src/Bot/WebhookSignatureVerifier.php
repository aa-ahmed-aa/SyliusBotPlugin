<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

/**
 * Checks the X-Hub-Signature-256 header Meta signs every webhook call with,
 * so nobody else can post fake messages into the store's carts.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads
 */
final class WebhookSignatureVerifier
{
    public function __construct(
        private readonly string $appSecret,
        private readonly bool $enabled = true,
    ) {
    }

    public function isValid(string $payload, ?string $signatureHeader): bool
    {
        if (!$this->enabled) {
            return true;
        }

        if ($this->appSecret === '' || $signatureHeader === null || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $payload, $this->appSecret), $signatureHeader);
    }
}
