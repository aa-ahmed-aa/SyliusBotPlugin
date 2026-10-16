<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

/**
 * The products a chat may offer: enabled, sold in the conversation's channel,
 * and with at least one variant that can be bought.
 */
final class ProductCatalog
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly int $perPage,
    ) {
    }

    /**
     * @return array{0: ProductInterface[], 1: bool} the page of products, and whether another page follows
     */
    public function getPage(ChannelInterface $channel, int $page): array
    {
        if (!$this->productRepository instanceof EntityRepository) {
            return [[], false];
        }

        /** @var ProductInterface[] $products */
        $products = $this->productRepository->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('o.enabled = :enabled')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * $this->perPage)
            ->setMaxResults($this->perPage + 1)
            ->getQuery()
            ->getResult()
        ;

        $hasMore = count($products) > $this->perPage;
        $products = array_slice($products, 0, $this->perPage);

        return [array_values(array_filter($products, fn (ProductInterface $product): bool => $this->isAvailable($product, $channel))), $hasMore];
    }

    public function find(int $id, ChannelInterface $channel): ?ProductInterface
    {
        $product = $this->productRepository->find($id);

        return $product instanceof ProductInterface && $product->isEnabled() && $product->hasChannel($channel) ? $product : null;
    }

    /**
     * Products by id, in the order asked for, leaving out any that cannot be sold.
     *
     * @param int[] $ids
     *
     * @return ProductInterface[]
     */
    public function findAvailable(array $ids, ChannelInterface $channel): array
    {
        $products = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $product = $this->find($id, $channel);
            if ($product !== null && $this->isAvailable($product, $channel)) {
                $products[] = $product;
            }
        }

        return array_slice($products, 0, $this->perPage);
    }

    /** @return ProductVariantInterface[] */
    public function getSellableVariants(ProductInterface $product, ChannelInterface $channel): array
    {
        $variants = [];
        foreach ($product->getEnabledVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface || $variant->getChannelPricingForChannel($channel) === null) {
                continue;
            }

            if (!$variant->isTracked() || $variant->isInStock()) {
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    public function isAvailable(ProductInterface $product, ChannelInterface $channel): bool
    {
        return $this->getSellableVariants($product, $channel) !== [];
    }
}
