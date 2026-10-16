<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Carousel cards for product lists and the cart.
 */
final class ProductCardBuilder
{
    private const IMAGE_FILTER = 'sylius_shop_product_thumbnail';

    public function __construct(
        private readonly MessageFactory $messageFactory,
        private readonly ProductCatalog $catalog,
        private readonly StoreUrlGenerator $urlGenerator,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly CacheManager $imagineCacheManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param ProductInterface[] $products
     *
     * @return array<int, array<string, mixed>>
     */
    public function productCards(Conversation $conversation, array $products, ?int $nextPage = null): array
    {
        $elements = [];

        foreach ($products as $product) {
            $variants = $this->catalog->getSellableVariants($product, $conversation->channel);
            if ($variants === []) {
                continue;
            }

            $pricing = $variants[0]->getChannelPricingForChannel($conversation->channel);
            $productUrl = $this->urlGenerator->product($conversation->channel, $conversation->localeCode, $product);

            $buttons = [];
            if (count($variants) === 1) {
                $buttons[] = $this->messageFactory->postbackButton(
                    $this->trans('add_to_cart', $conversation),
                    ['type' => IncomingMessage::ACTION_ADD_TO_CART, 'product_id' => (int) $product->getId()],
                );
            } else {
                // Choosing a size or colour is easier on the product page than in a chat.
                $buttons[] = $this->messageFactory->urlButton($this->trans('choose_options', $conversation), $productUrl);
            }
            $buttons[] = $this->messageFactory->urlButton($this->trans('view_on_website', $conversation), $productUrl);

            $translation = $product->getTranslation($conversation->localeCode);

            $elements[] = $this->messageFactory->element(
                (string) ($translation->getName() ?? $product->getName()),
                trim($this->price($pricing, $conversation) . ' · ' . strip_tags((string) $translation->getShortDescription()), ' ·'),
                $this->imageUrl($product),
                $buttons,
            );
        }

        if ($nextPage !== null && $elements !== []) {
            $elements[] = $this->messageFactory->element(
                $this->trans('see_more', $conversation),
                '',
                null,
                [$this->messageFactory->postbackButton($this->trans('see_more', $conversation), ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => $nextPage])],
            );
        }

        return $elements;
    }

    /** @return array<int, array<string, mixed>> */
    public function cartCards(Conversation $conversation): array
    {
        $elements = [];

        /** @var OrderItemInterface $item */
        foreach ($conversation->cart->getItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }

            $elements[] = $this->messageFactory->element(
                (string) $item->getProductName(),
                $this->trans('cart_item_subtitle', $conversation, [
                    '%price%' => $this->moneyFormatter->format($item->getTotal(), (string) $conversation->cart->getCurrencyCode(), $conversation->localeCode),
                    '%quantity%' => $item->getQuantity(),
                ]),
                $this->imageUrl($product),
                [
                    $this->messageFactory->postbackButton($this->trans('remove_from_cart', $conversation), ['type' => IncomingMessage::ACTION_REMOVE_FROM_CART, 'item_id' => (int) $item->getId()]),
                    $this->messageFactory->urlButton($this->trans('view_on_website', $conversation), $this->urlGenerator->product($conversation->channel, $conversation->localeCode, $product)),
                ],
            );
        }

        return $elements;
    }

    /**
     * The same products as plain text with links, for clients that show no
     * templates (Instagram on the web). Kept under Instagram's 1000 characters.
     *
     * @param ProductInterface[] $products
     */
    public function productsAsText(Conversation $conversation, array $products, ?int $nextPage = null): string
    {
        $lines = [$this->trans('products_heading', $conversation)];

        foreach ($products as $product) {
            $variants = $this->catalog->getSellableVariants($product, $conversation->channel);
            if ($variants === []) {
                continue;
            }

            $line = sprintf(
                "- %s: %s\n  %s",
                $product->getTranslation($conversation->localeCode)->getName() ?? $product->getName(),
                $this->price($variants[0]->getChannelPricingForChannel($conversation->channel), $conversation),
                $this->urlGenerator->product($conversation->channel, $conversation->localeCode, $product),
            );

            if (mb_strlen(implode("\n", $lines) . $line) > 900) {
                break;
            }

            $lines[] = $line;
        }

        if ($nextPage !== null) {
            $lines[] = $this->trans('products_more', $conversation, ['%page%' => $nextPage]);
        }

        return implode("\n", $lines);
    }

    private function price(?ChannelPricingInterface $pricing, Conversation $conversation): string
    {
        if ($pricing === null || $pricing->getPrice() === null) {
            return '';
        }

        return $this->moneyFormatter->format($pricing->getPrice(), (string) $conversation->channel->getBaseCurrency()?->getCode(), $conversation->localeCode);
    }

    private function imageUrl(ProductInterface $product): ?string
    {
        $images = $product->getImagesByType('thumbnail');
        /** @var ProductImageInterface|false $image */
        $image = $images->isEmpty() ? $product->getImages()->first() : $images->first();

        if ($image === false || $image->getPath() === null) {
            return null;
        }

        return $this->imagineCacheManager->getBrowserPath($image->getPath(), self::IMAGE_FILTER);
    }

    /** @param array<string, string|int> $parameters */
    private function trans(string $key, Conversation $conversation, array $parameters = []): string
    {
        return $this->translator->trans('sylius_bot.chat.' . $key, $parameters, 'messages', $conversation->localeCode);
    }
}
