<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Unit;

use PHPUnit\Framework\TestCase;
use SyliusBotPlugin\Bot\WebhookSignatureVerifier;

final class WebhookSignatureVerifierTest extends TestCase
{
    public function testAcceptsOnlyTheAppSignature(): void
    {
        $verifier = new WebhookSignatureVerifier('secret');
        $payload = '{"object":"page"}';

        self::assertTrue($verifier->isValid($payload, 'sha256=' . hash_hmac('sha256', $payload, 'secret')));
        self::assertFalse($verifier->isValid($payload, 'sha256=' . hash_hmac('sha256', $payload, 'other')));
        self::assertFalse($verifier->isValid($payload . ' ', 'sha256=' . hash_hmac('sha256', $payload, 'secret')));
        self::assertFalse($verifier->isValid($payload, null));
        self::assertFalse($verifier->isValid($payload, 'sha1=abc'));
    }

    public function testRejectsEverythingWithoutASecret(): void
    {
        self::assertFalse((new WebhookSignatureVerifier(''))->isValid('{}', 'sha256=' . hash_hmac('sha256', '{}', '')));
    }

    public function testCanBeTurnedOff(): void
    {
        self::assertTrue((new WebhookSignatureVerifier('', false))->isValid('{}', null));
    }
}
