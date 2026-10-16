<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Psr\Log\LoggerInterface;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Graph\GraphClientInterface;

/**
 * Sends messages through the Messenger Platform Send API. Instagram Direct uses
 * the same endpoint with the linked Page's token, minus a few parameters
 * Instagram rejects.
 */
final class SendApi
{
    /** @see https://developers.facebook.com/docs/messenger-platform/instagram/features/send-message */
    private const INSTAGRAM_UNSUPPORTED = [
        'image_aspect_ratio',
        'webview_height_ratio',
        'webview_share_button',
        'messenger_extensions',
        'fallback_url',
    ];

    public function __construct(
        private readonly GraphClientInterface $graphClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    public function send(BotInterface $bot, string $recipientId, array $message, string $messagingType = 'RESPONSE', ?string $tag = null): array
    {
        $body = [
            'recipient' => ['id' => $recipientId],
            'message' => $message,
        ];

        if ($bot->getChannelType() === BotInterface::CHANNEL_INSTAGRAM) {
            $body['message'] = self::sanitizeForInstagram($message);
        } else {
            $body['messaging_type'] = $messagingType;
            if ($tag !== null) {
                $body['tag'] = $tag;
            }
        }

        return $this->graphClient->post('me/messages', $body, $bot->getPageAccessToken());
    }

    /**
     * Seen receipt and the typing bubble, sent before slow work so the
     * customer sees a reply is coming. Never throws: it is a courtesy.
     *
     * @param string[] $actions
     */
    public function senderActions(BotInterface $bot, string $recipientId, array $actions = ['mark_seen', 'typing_on']): void
    {
        foreach ($actions as $action) {
            try {
                $this->graphClient->post('me/messages', [
                    'recipient' => ['id' => $recipientId],
                    'sender_action' => $action,
                ], $bot->getPageAccessToken());
            } catch (\Throwable $exception) {
                $this->logger->info('Sender action failed', ['action' => $action, 'error' => $exception->getMessage()]);

                return;
            }
        }
    }

    /**
     * @param array<mixed> $message
     *
     * @return array<mixed>
     */
    public static function sanitizeForInstagram(array $message): array
    {
        foreach ($message as $key => $value) {
            if (is_string($key) && in_array($key, self::INSTAGRAM_UNSUPPORTED, true)) {
                unset($message[$key]);

                continue;
            }

            if (is_array($value)) {
                $message[$key] = self::sanitizeForInstagram($value);
            }
        }

        return $message;
    }
}
