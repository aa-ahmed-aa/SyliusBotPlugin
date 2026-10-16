<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot\Reply;

use SyliusBotPlugin\Bot\Conversation;

/**
 * Answers free-text messages that are not a menu command, e.g. with an LLM.
 *
 * The plugin ships a generator that answers nothing, which makes the bot reply
 * with its menu. Replace the `sylius_bot.reply_generator` service to plug in
 * your own; the page's "AI instructions" setting is available on
 * `$conversation->bot->getSetting('ai_instructions')`, and the conversation so
 * far on `BotMessageRepositoryInterface::findLatestForSubscriber()`.
 */
interface ReplyGeneratorInterface
{
    public function generate(Conversation $conversation, string $text): ?Reply;
}
