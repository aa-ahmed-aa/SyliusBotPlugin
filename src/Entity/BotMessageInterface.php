<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;

/**
 * One message of a conversation, kept so the admin can read it back.
 */
interface BotMessageInterface extends ResourceInterface
{
    /** Sent by the customer. */
    public const DIRECTION_INBOUND = 'in';

    /** Sent by the store. */
    public const DIRECTION_OUTBOUND = 'out';

    public function getSubscriber(): ?BotSubscriberInterface;

    public function setSubscriber(?BotSubscriberInterface $subscriber): void;

    public function getDirection(): string;

    public function setDirection(string $direction): void;

    public function isInbound(): bool;

    public function getText(): string;

    public function setText(string $text): void;

    public function getCreatedAt(): \DateTimeInterface;

    public function setCreatedAt(\DateTimeInterface $createdAt): void;
}
