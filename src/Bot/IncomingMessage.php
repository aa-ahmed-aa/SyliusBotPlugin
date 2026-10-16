<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

/**
 * One message event from a webhook call, reduced to what the bot acts on.
 */
final class IncomingMessage
{
    public const ACTION_GET_STARTED = 'get_started';

    public const ACTION_LIST_ITEMS = 'list_items';

    public const ACTION_ADD_TO_CART = 'add_to_cart';

    public const ACTION_REMOVE_FROM_CART = 'remove_item_from_cart';

    public const ACTION_EMPTY_CART = 'empty_cart';

    public const ACTION_MY_CART = 'my_cart';

    public const ACTION_CHECKOUT = 'checkout';

    /**
     * @param array<string, mixed>|null $action postback / quick reply payload, or a recognised text command
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $accountId,
        public readonly string $senderId,
        public readonly ?string $text = null,
        public readonly ?array $action = null,
    ) {
    }

    public function getActionType(): ?string
    {
        $type = $this->action['type'] ?? null;

        return is_string($type) ? $type : null;
    }
}
