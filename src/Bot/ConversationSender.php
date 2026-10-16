<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use SyliusBotPlugin\Entity\BotMessageInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;

/**
 * Sends to a conversation and keeps a readable copy of every message, in both
 * directions, for the subscriber page in the admin.
 */
final class ConversationSender
{
    /**
     * @param FactoryInterface<BotMessageInterface> $messageFactory
     */
    public function __construct(
        private readonly SendApi $sendApi,
        private readonly FactoryInterface $messageFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, mixed> $message */
    public function send(Conversation $conversation, array $message): void
    {
        $this->sendApi->send($conversation->bot, $conversation->getRecipientId(), $message);
        $this->record($conversation->subscriber, BotMessageInterface::DIRECTION_OUTBOUND, self::describe($message));
    }

    public function typing(Conversation $conversation): void
    {
        $this->sendApi->senderActions($conversation->bot, $conversation->getRecipientId());
    }

    public function record(BotSubscriberInterface $subscriber, string $direction, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $message = $this->messageFactory->createNew();
        $message->setSubscriber($subscriber);
        $message->setDirection($direction);
        $message->setText($this->storable($text));

        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }

    /**
     * Customers send emoji. A connection that is not utf8mb4 (Sylius' default
     * `charset: UTF8` is 3-byte utf8) cannot store them, and a failed insert
     * would close the entity manager mid-reply, so they are dropped there.
     */
    private function storable(string $text): string
    {
        $charset = strtolower((string) ($this->entityManager->getConnection()->getParams()['charset'] ?? 'utf8mb4'));

        return $charset === 'utf8mb4' ? $text : (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
    }

    /**
     * A text rendering of a Send API message: the text itself, a button
     * template's text and buttons, or a carousel's card titles.
     *
     * @param array<string, mixed> $message
     */
    public static function describe(array $message): string
    {
        if (isset($message['text']) && is_string($message['text'])) {
            return $message['text'];
        }

        $payload = $message['attachment']['payload'] ?? [];
        if (!is_array($payload)) {
            return '';
        }

        $titles = static fn (array $items): string => implode(' · ', array_filter(array_map(
            static fn ($item): string => is_array($item) ? (string) ($item['title'] ?? '') : '',
            $items,
        )));

        return match ($payload['template_type'] ?? null) {
            'button' => trim((string) ($payload['text'] ?? '') . "\n[" . $titles((array) ($payload['buttons'] ?? [])) . ']'),
            'generic' => '🛍 ' . $titles((array) ($payload['elements'] ?? [])),
            default => '',
        };
    }
}
