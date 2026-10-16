<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot\Reply;

use SyliusBotPlugin\Bot\Conversation;

final class NullReplyGenerator implements ReplyGeneratorInterface
{
    public function generate(Conversation $conversation, string $text): ?Reply
    {
        return null;
    }
}
