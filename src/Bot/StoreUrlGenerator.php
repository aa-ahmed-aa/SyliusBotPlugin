<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Absolute links into the storefront for chat buttons, on the channel's own
 * host when it has one.
 */
final class StoreUrlGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function homepage(ChannelInterface $channel, string $localeCode): string
    {
        return $this->absolute($channel, $this->urlGenerator->generate('sylius_shop_homepage', ['_locale' => $localeCode]));
    }

    public function product(ChannelInterface $channel, string $localeCode, ProductInterface $product): string
    {
        $slug = $product->getTranslation($localeCode)->getSlug() ?? $product->getSlug();

        return $this->absolute($channel, $this->urlGenerator->generate('sylius_shop_product_show', [
            '_locale' => $localeCode,
            'slug' => (string) $slug,
        ]));
    }

    public function checkout(ChannelInterface $channel, OrderInterface $cart): string
    {
        return $this->absolute($channel, $this->urlGenerator->generate('sylius_bot_shop_checkout', [
            'tokenValue' => (string) $cart->getTokenValue(),
        ]));
    }

    private function absolute(ChannelInterface $channel, string $path): string
    {
        $hostname = $channel->getHostname();
        if ($hostname !== null && $hostname !== '') {
            return 'https://' . $hostname . $path;
        }

        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            return $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $path;
        }

        $context = $this->urlGenerator->getContext();

        return sprintf('%s://%s%s', $context->getScheme(), $context->getHost(), $path);
    }
}
