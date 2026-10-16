<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use SyliusBotPlugin\Entity\BotMessageInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;

class BotMessageRepository extends EntityRepository implements BotMessageRepositoryInterface
{
    public function findLatestForSubscriber(BotSubscriberInterface $subscriber, int $limit = 500): array
    {
        /** @var BotMessageInterface[] $messages */
        $messages = $this->createQueryBuilder('o')
            ->andWhere('o.subscriber = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult()
        ;

        return array_reverse($messages);
    }
}
