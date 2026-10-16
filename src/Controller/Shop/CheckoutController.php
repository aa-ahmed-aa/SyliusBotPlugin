<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Controller\Shop;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The link behind a chat's "Checkout" button: makes the chat's cart the
 * browser's cart and opens the regular cart page.
 */
final class CheckoutController
{
    /**
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CartStorageInterface $cartStorage,
        private readonly ChannelContextInterface $channelContext,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(string $tokenValue): RedirectResponse
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        $cart = $this->orderRepository->findCartByTokenValueAndChannel($tokenValue, $channel);
        if (!$cart instanceof OrderInterface) {
            throw new NotFoundHttpException('This cart has already been checked out or does not exist.');
        }

        $this->cartStorage->setForChannel($channel, $cart);

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary', [
            '_locale' => $cart->getLocaleCode() ?? $channel->getDefaultLocale()?->getCode(),
        ]));
    }
}
