<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use SyliusBotPlugin\Entity\BotInterface;

/**
 * Turns a Messenger or Instagram webhook body into the message events the bot
 * answers. Echoes of the store's own messages, delivery and read receipts are
 * dropped, so the bot never answers itself.
 */
final class IncomingMessageParser
{
    private const OBJECT_TO_CHANNEL = [
        'page' => BotInterface::CHANNEL_MESSENGER,
        'instagram' => BotInterface::CHANNEL_INSTAGRAM,
    ];

    /**
     * @param array<string, mixed> $body
     *
     * @return IncomingMessage[]
     */
    public function parse(array $body): array
    {
        $channel = self::OBJECT_TO_CHANNEL[$body['object'] ?? ''] ?? null;
        if ($channel === null) {
            return [];
        }

        $messages = [];
        foreach ((array) ($body['entry'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (is_array($event) && null !== $message = $this->parseEvent($channel, $entry, $event)) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * Only a message that is exactly one of these commands maps to an action;
     * anything longer is a question, and goes to the reply generator instead.
     *
     * @return array<string, mixed>|null
     */
    public static function matchTextCommand(string $text): ?array
    {
        $text = mb_strtolower(trim($text, " \t\n\r\0\x0B.!?"));

        if (preg_match('/^(?:list|products|shop|browse)(?:\s+(\d+))?$/', $text, $match) === 1) {
            return ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => max(1, (int) ($match[1] ?? 1))];
        }

        if (preg_match('/^(?:empty|clear)\s+(?:my\s+)?cart$/', $text) === 1) {
            return ['type' => IncomingMessage::ACTION_EMPTY_CART];
        }

        if (preg_match('/^(?:my\s+|view\s+)?cart$/', $text) === 1) {
            return ['type' => IncomingMessage::ACTION_MY_CART];
        }

        if (preg_match('/^(?:checkout|pay)$/', $text) === 1) {
            return ['type' => IncomingMessage::ACTION_CHECKOUT];
        }

        if (preg_match('/^(?:hi|hello|hey|start|get started|menu)$/', $text) === 1) {
            return ['type' => IncomingMessage::ACTION_GET_STARTED];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $event
     */
    private function parseEvent(string $channel, array $entry, array $event): ?IncomingMessage
    {
        $senderId = (string) ($event['sender']['id'] ?? '');
        $message = is_array($event['message'] ?? null) ? $event['message'] : null;
        $postback = is_array($event['postback'] ?? null) ? $event['postback'] : null;

        if ($senderId === '' || ($message === null && $postback === null) || ($message['is_echo'] ?? false) === true) {
            return null;
        }

        // Instagram addresses events to the account in `recipient`; Messenger to the Page in `entry.id`.
        $accountId = $channel === BotInterface::CHANNEL_INSTAGRAM
            ? (string) ($event['recipient']['id'] ?? $entry['id'] ?? '')
            : (string) ($entry['id'] ?? $event['recipient']['id'] ?? '');

        if ($accountId === '' || $accountId === $senderId) {
            return null;
        }

        if ($postback !== null) {
            $action = self::decodePayload((string) ($postback['payload'] ?? ''));

            return new IncomingMessage($channel, $accountId, $senderId, null, $action ?? ['type' => IncomingMessage::ACTION_GET_STARTED]);
        }

        $quickReply = $message['quick_reply']['payload'] ?? null;
        if (is_string($quickReply) && null !== $action = self::decodePayload($quickReply)) {
            return new IncomingMessage($channel, $accountId, $senderId, null, $action);
        }

        $text = isset($message['text']) && is_string($message['text']) ? $message['text'] : null;

        return new IncomingMessage($channel, $accountId, $senderId, $text, $text !== null ? self::matchTextCommand($text) : null);
    }

    /** @return array<string, mixed>|null */
    private static function decodePayload(string $payload): ?array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) && is_string($decoded['type'] ?? null) ? $decoded : null;
    }
}
