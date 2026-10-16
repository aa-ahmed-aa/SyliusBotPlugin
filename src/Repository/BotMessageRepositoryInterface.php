<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Repository;

use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use SyliusBotPlugin\Entity\BotMessageInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;

interface BotMessageRepositoryInterface extends RepositoryInterface
{
    /**
     * The latest $limit messages of a conversation, oldest first.
     *
     * @return BotMessageInterface[]
     */
    public function findLatestForSubscriber(BotSubscriberInterface $subscriber, int $limit = 500): array;
}
