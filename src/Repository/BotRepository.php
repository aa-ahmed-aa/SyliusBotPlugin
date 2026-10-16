<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use SyliusBotPlugin\Entity\BotInterface;

class BotRepository extends EntityRepository implements BotRepositoryInterface
{
    public function findOneByChannelAndPageId(string $channel, string $pageId): ?BotInterface
    {
        /** @var BotInterface|null $bot */
        $bot = $this->findOneBy(['channelType' => $channel, 'pageId' => $pageId]);

        return $bot;
    }

    public function findByChannel(string $channel): array
    {
        /** @var BotInterface[] $bots */
        $bots = $this->findBy(['channelType' => $channel], ['pageName' => 'ASC']);

        return $bots;
    }
}
