<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;

/**
 * Everything a reply needs: which page answers, to whom, in which sales
 * channel and language, and the customer's cart there.
 */
final class Conversation
{
    public function __construct(
        public readonly BotInterface $bot,
        public readonly BotSubscriberInterface $subscriber,
        public readonly ChannelInterface $channel,
        public readonly string $localeCode,
        public readonly OrderInterface $cart,
    ) {
    }

    public function getRecipientId(): string
    {
        return (string) $this->subscriber->getBotSubscriberId();
    }

    public function isInstagram(): bool
    {
        return $this->bot->getChannelType() === BotInterface::CHANNEL_INSTAGRAM;
    }
}
