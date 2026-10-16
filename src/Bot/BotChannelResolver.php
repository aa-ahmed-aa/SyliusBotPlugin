<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use SyliusBotPlugin\Entity\BotInterface;

/**
 * The sales channel a page sells in: the one chosen in its settings, or the
 * store's first enabled channel.
 */
final class BotChannelResolver
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    public function resolve(BotInterface $bot): ChannelInterface
    {
        $code = $bot->getSetting('channel_code');
        if ($code !== '') {
            $channel = $this->channelRepository->findOneByCode($code);
            if ($channel instanceof ChannelInterface && $channel->isEnabled()) {
                return $channel;
            }
        }

        $channel = $this->channelRepository->findBy(['enabled' => true], ['id' => 'ASC'], 1)[0] ?? null;
        if (!$channel instanceof ChannelInterface) {
            throw new \RuntimeException('The store has no enabled channel to sell in.');
        }

        return $channel;
    }
}
