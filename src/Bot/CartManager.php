<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class CartManager
{
    /**
     * @param FactoryInterface<OrderItemInterface> $orderItemFactory
     */
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Adds one unit of the product's only sellable variant. Answers false when
     * there is no such single variant or not enough stock.
     */
    public function add(OrderInterface $cart, ProductInterface $product): bool
    {
        $channel = $cart->getChannel();
        if ($channel === null) {
            return false;
        }

        $variants = $this->catalog->getSellableVariants($product, $channel);
        if (count($variants) !== 1) {
            return false;
        }

        $variant = $variants[0];
        $inCart = 0;
        foreach ($cart->getItems() as $item) {
            if ($item->getVariant() === $variant) {
                $inCart += $item->getQuantity();
            }
        }

        if (!$this->availabilityChecker->isStockSufficient($variant, $inCart + 1)) {
            return false;
        }

        $item = $this->orderItemFactory->createNew();
        $item->setVariant($variant);
        $this->quantityModifier->modify($item, 1);
        $this->orderModifier->addToOrder($cart, $item);

        $this->entityManager->flush();

        return true;
    }

    /**
     * Removes an item, but only one of this cart's own: the id comes from a
     * chat button and must not reach another customer's cart.
     */
    public function remove(OrderInterface $cart, int $itemId): ?OrderItemInterface
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $itemId) {
                $this->orderModifier->removeFromOrder($cart, $item);
                $this->entityManager->flush();

                return $item;
            }
        }

        return null;
    }

    public function clear(OrderInterface $cart): void
    {
        $cart->clearItems();
        $this->orderProcessor->process($cart);
        $this->entityManager->flush();
    }
}
