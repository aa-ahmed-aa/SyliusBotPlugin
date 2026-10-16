<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Psr\Log\LoggerInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use SyliusBotPlugin\Bot\Reply\Reply;
use SyliusBotPlugin\Bot\Reply\ReplyGeneratorInterface;
use SyliusBotPlugin\Entity\BotMessageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Answers one incoming message: the menu actions (browse, cart, checkout), and
 * anything else through the reply generator, falling back to the menu.
 *
 * Instagram on the web renders neither button templates nor carousels, so on
 * Instagram every menu and list also goes out as plain text with links.
 */
final class ConversationHandler
{
    public function __construct(
        private readonly ConversationFactory $conversationFactory,
        private readonly ConversationSender $sender,
        private readonly MessageFactory $messageFactory,
        private readonly ProductCatalog $catalog,
        private readonly ProductCardBuilder $cardBuilder,
        private readonly CartManager $cartManager,
        private readonly StoreUrlGenerator $urlGenerator,
        private readonly ReplyGeneratorInterface $replyGenerator,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(IncomingMessage $message): void
    {
        $bot = $this->conversationFactory->findBot($message);
        if ($bot === null) {
            $this->logger->notice('Message for a page that is not connected or is disabled', [
                'channel' => $message->channel,
                'account' => $message->accountId,
            ]);

            return;
        }

        $conversation = $this->conversationFactory->create($bot, $message);

        $this->sender->record(
            $conversation->subscriber,
            BotMessageInterface::DIRECTION_INBOUND,
            $message->text ?? '[' . ($message->getActionType() ?? 'attachment') . ']',
        );
        $this->sender->typing($conversation);

        $action = $message->action ?? [];

        match ($message->getActionType()) {
            IncomingMessage::ACTION_GET_STARTED => $this->welcome($conversation),
            IncomingMessage::ACTION_LIST_ITEMS => $this->listProducts($conversation, (int) ($action['page'] ?? 1)),
            IncomingMessage::ACTION_ADD_TO_CART => $this->addToCart($conversation, (int) ($action['product_id'] ?? 0)),
            IncomingMessage::ACTION_REMOVE_FROM_CART => $this->removeFromCart($conversation, (int) ($action['item_id'] ?? 0)),
            IncomingMessage::ACTION_EMPTY_CART => $this->emptyCart($conversation),
            IncomingMessage::ACTION_MY_CART => $this->showCart($conversation),
            IncomingMessage::ACTION_CHECKOUT => $this->checkout($conversation),
            default => $message->text !== null && trim($message->text) !== ''
                ? $this->answer($conversation, $message->text)
                : $this->fallback($conversation),
        };
    }

    private function welcome(Conversation $conversation): void
    {
        $greeting = str_replace(
            '{{user_first_name}}',
            (string) ($conversation->subscriber->getFirstName() ?? $conversation->subscriber->getName() ?? ''),
            $conversation->bot->getSetting('greeting'),
        );

        $this->menu($conversation, $greeting);
    }

    private function fallback(Conversation $conversation): void
    {
        $this->menu($conversation, $this->trans('fallback', $conversation));
    }

    private function menu(Conversation $conversation, string $text): void
    {
        $buttons = [
            $this->messageFactory->postbackButton($this->trans('browse_products', $conversation), ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => 1]),
            $this->messageFactory->postbackButton($this->trans('view_cart', $conversation), ['type' => IncomingMessage::ACTION_MY_CART]),
            $this->messageFactory->urlButton($this->trans('visit_website', $conversation), $this->urlGenerator->homepage($conversation->channel, $conversation->localeCode)),
        ];

        if ($conversation->isInstagram()) {
            $this->sender->send($conversation, $this->messageFactory->text($text . "\n" . $this->trans('text_commands', $conversation)));
            $this->sender->send($conversation, $this->messageFactory->buttons($this->trans('tap_an_option', $conversation), $buttons));

            return;
        }

        $this->sender->send($conversation, $this->messageFactory->buttons($text, $buttons));
    }

    private function listProducts(Conversation $conversation, int $page): void
    {
        $page = max(1, $page);
        [$products, $hasMore] = $this->catalog->getPage($conversation->channel, $page);

        if ($products === []) {
            $this->sender->send($conversation, $this->messageFactory->text($this->trans('no_products', $conversation)));

            return;
        }

        $nextPage = $hasMore ? $page + 1 : null;

        if ($conversation->isInstagram()) {
            $this->sender->send($conversation, $this->messageFactory->text($this->cardBuilder->productsAsText($conversation, $products, $nextPage)));
        }

        $this->sender->send($conversation, $this->messageFactory->carousel($this->cardBuilder->productCards($conversation, $products, $nextPage)));
    }

    private function addToCart(Conversation $conversation, int $productId): void
    {
        $product = $this->catalog->find($productId, $conversation->channel);
        if ($product === null) {
            $this->sender->send($conversation, $this->messageFactory->text($this->trans('product_not_found', $conversation)));

            return;
        }

        $name = (string) ($product->getTranslation($conversation->localeCode)->getName() ?? $product->getName());

        if (!$this->cartManager->add($conversation->cart, $product)) {
            $this->sender->send($conversation, $this->messageFactory->text($this->trans('cannot_add', $conversation, ['%product%' => $name])));

            return;
        }

        $this->sender->send($conversation, $this->messageFactory->buttons(
            $this->trans('added_to_cart', $conversation, ['%product%' => $name]),
            [
                $this->messageFactory->postbackButton($this->trans('checkout_button', $conversation), ['type' => IncomingMessage::ACTION_CHECKOUT]),
                $this->messageFactory->postbackButton($this->trans('continue_shopping', $conversation), ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => 1]),
            ],
        ));
    }

    private function removeFromCart(Conversation $conversation, int $itemId): void
    {
        $item = $this->cartManager->remove($conversation->cart, $itemId);

        $this->sender->send($conversation, $this->messageFactory->text($item === null
            ? $this->trans('item_not_found', $conversation)
            : $this->trans('removed_from_cart', $conversation, ['%product%' => (string) $item->getProductName()])));
    }

    private function emptyCart(Conversation $conversation): void
    {
        $this->cartManager->clear($conversation->cart);
        $this->sender->send($conversation, $this->messageFactory->text($this->trans('cart_cleared', $conversation)));
    }

    private function showCart(Conversation $conversation): void
    {
        if ($conversation->cart->getItems()->isEmpty()) {
            $this->cartIsEmpty($conversation);

            return;
        }

        $this->sender->send($conversation, $this->messageFactory->carousel($this->cardBuilder->cartCards($conversation), MessageFactory::RATIO_HORIZONTAL));
        $this->checkout($conversation);
    }

    private function checkout(Conversation $conversation): void
    {
        if ($conversation->cart->getItems()->isEmpty()) {
            $this->cartIsEmpty($conversation);

            return;
        }

        $url = $this->urlGenerator->checkout($conversation->channel, $conversation->cart);
        $text = $this->trans('checkout_prompt', $conversation, [
            '%total%' => $this->moneyFormatter->format($conversation->cart->getTotal(), (string) $conversation->cart->getCurrencyCode(), $conversation->localeCode),
        ]);

        if ($conversation->isInstagram()) {
            // A plain link works in the Instagram app and on the web alike.
            $this->sender->send($conversation, $this->messageFactory->text($text . "\n" . $url));
        }

        $this->sender->send($conversation, $this->messageFactory->buttons($text, [
            $this->messageFactory->urlButton($this->trans('checkout_button', $conversation), $url),
            $this->messageFactory->postbackButton($this->trans('continue_shopping', $conversation), ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => 1]),
        ]));
    }

    private function cartIsEmpty(Conversation $conversation): void
    {
        $this->sender->send($conversation, $this->messageFactory->buttons($this->trans('cart_empty', $conversation), [
            $this->messageFactory->postbackButton($this->trans('browse_products', $conversation), ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => 1]),
        ]));
    }

    private function answer(Conversation $conversation, string $text): void
    {
        try {
            $reply = $this->replyGenerator->generate($conversation, $text);
        } catch (\Throwable $exception) {
            $this->logger->error('Reply generator failed', ['error' => $exception->getMessage()]);
            $reply = null;
        }

        if ($reply === null || $reply->isEmpty()) {
            $this->fallback($conversation);

            return;
        }

        if ($reply->productIds !== []) {
            // Recommendations are checked against live stock: a generator's
            // own index can be behind.
            $products = $this->catalog->findAvailable($reply->productIds, $conversation->channel);

            if ($products === []) {
                $this->sender->send($conversation, $this->messageFactory->buttons($this->trans('nothing_in_stock', $conversation), [
                    $this->messageFactory->postbackButton($this->trans('browse_products', $conversation), ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => 1]),
                ]));

                return;
            }

            if ($reply->text !== null && trim($reply->text) !== '') {
                $this->sender->send($conversation, $this->messageFactory->text($reply->text));
            }

            $this->sender->send($conversation, $this->messageFactory->carousel($this->cardBuilder->productCards($conversation, $products)));

            return;
        }

        if ($reply->text !== null && trim($reply->text) !== '') {
            $this->sender->send($conversation, $this->messageFactory->text($reply->text));
        }

        match ($reply->action) {
            Reply::ACTION_VIEW_CART => $this->showCart($conversation),
            Reply::ACTION_CHECKOUT => $this->checkout($conversation),
            default => null,
        };
    }

    /** @param array<string, string|int> $parameters */
    private function trans(string $key, Conversation $conversation, array $parameters = []): string
    {
        return $this->translator->trans('sylius_bot.chat.' . $key, $parameters, 'messages', $conversation->localeCode);
    }
}
