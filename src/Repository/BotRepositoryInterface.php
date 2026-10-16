<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Repository;

use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use SyliusBotPlugin\Entity\BotInterface;

interface BotRepositoryInterface extends RepositoryInterface
{
    public function findOneByChannelAndPageId(string $channel, string $pageId): ?BotInterface;

    /** @return BotInterface[] */
    public function findByChannel(string $channel): array;
}
