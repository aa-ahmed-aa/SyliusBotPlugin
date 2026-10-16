<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Controller\Admin;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;
use SyliusBotPlugin\Repository\BotMessageRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * One bot user: their conversation with the store's pages, and the orders of
 * the customer the bot created for them.
 */
final class BotSubscriberController extends AbstractController
{
    /**
     * @param RepositoryInterface<BotSubscriberInterface> $subscriberRepository
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     */
    public function __construct(
        private readonly RepositoryInterface $subscriberRepository,
        private readonly BotMessageRepositoryInterface $messageRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function show(int $id): Response
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber instanceof BotSubscriberInterface) {
            throw $this->createNotFoundException();
        }

        $orders = [];
        $customer = $subscriber->getCustomer();
        if ($customer !== null) {
            /** @var OrderInterface[] $orders */
            $orders = $this->orderRepository->findByCustomer($customer);
            usort($orders, static fn (OrderInterface $a, OrderInterface $b): int => ($b->getCheckoutCompletedAt() ?? $b->getCreatedAt()) <=> ($a->getCheckoutCompletedAt() ?? $a->getCreatedAt()));
        }

        return $this->render('@SyliusBotPlugin/admin/bot_subscriber/show.html.twig', [
            'subscriber' => $subscriber,
            'customer' => $customer,
            'messages' => $this->messageRepository->findLatestForSubscriber($subscriber),
            'orders' => $orders,
        ]);
    }
}
