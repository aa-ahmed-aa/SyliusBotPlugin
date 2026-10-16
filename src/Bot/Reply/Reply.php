<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot\Reply;

/**
 * What a reply generator wants the bot to answer.
 */
final class Reply
{
    public const ACTION_VIEW_CART = 'view_cart';

    public const ACTION_CHECKOUT = 'checkout';

    /**
     * @param int[] $productIds products to show as cards after the text, most relevant first
     * @param string|null $action one of the ACTION_* constants, run after the text
     */
    public function __construct(
        public readonly ?string $text = null,
        public readonly array $productIds = [],
        public readonly ?string $action = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return ($this->text === null || trim($this->text) === '') && $this->productIds === [] && $this->action === null;
    }
}
