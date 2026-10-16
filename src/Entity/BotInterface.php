<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

/**
 * A connected Facebook Page (Messenger) or Instagram professional account.
 */
interface BotInterface extends ResourceInterface, TimestampableInterface
{
    public const CHANNEL_MESSENGER = 'messenger';

    public const CHANNEL_INSTAGRAM = 'instagram';

    public const CHANNELS = [self::CHANNEL_MESSENGER, self::CHANNEL_INSTAGRAM];

    public function getPageName(): ?string;

    public function setPageName(?string $pageName): void;

    /** Facebook Page id for Messenger, Instagram account id for Instagram. */
    public function getPageId(): ?string;

    public function setPageId(?string $pageId): void;

    public function getPageAccessToken(): ?string;

    public function setPageAccessToken(?string $pageAccessToken): void;

    public function getPageImageUrl(): ?string;

    public function setPageImageUrl(?string $pageImageUrl): void;

    public function getChannelType(): ?string;

    public function setChannelType(?string $channelType): void;

    /** For Instagram: the Facebook Page the account is linked to. Webhooks are subscribed on it. */
    public function getFacebookPageId(): ?string;

    public function setFacebookPageId(?string $facebookPageId): void;

    public function isDisabled(): bool;

    public function setDisabled(bool $disabled): void;

    /** @return array<string, string> */
    public function getSettings(): array;

    /** @param array<string, string|null> $settings */
    public function setSettings(array $settings): void;

    public function getSetting(string $key): string;
}
