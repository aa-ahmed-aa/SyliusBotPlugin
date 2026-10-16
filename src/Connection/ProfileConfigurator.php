<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Connection;

use SyliusBotPlugin\Bot\BotChannelResolver;
use SyliusBotPlugin\Bot\IncomingMessage;
use SyliusBotPlugin\Bot\StoreUrlGenerator;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Graph\GraphApiException;
use SyliusBotPlugin\Graph\GraphClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Pushes a page's greeting, Get Started button and persistent menu to Meta.
 *
 * Instagram takes only the persistent menu, through the same endpoint with
 * `platform=instagram`.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/reference/messenger-profile-api
 */
final class ProfileConfigurator
{
    private const MAX_GREETING = 160;

    public function __construct(
        private readonly GraphClientInterface $graphClient,
        private readonly BotChannelResolver $channelResolver,
        private readonly StoreUrlGenerator $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @throws GraphApiException */
    public function apply(BotInterface $bot): void
    {
        $channel = $this->channelResolver->resolve($bot);
        $localeCode = (string) ($channel->getDefaultLocale()?->getCode() ?? 'en_US');
        $settings = $bot->getSettings();
        $isInstagram = $bot->getChannelType() === BotInterface::CHANNEL_INSTAGRAM;

        $website = [
            'type' => 'web_url',
            'title' => $this->translator->trans('sylius_bot.chat.visit_website', [], 'messages', $localeCode),
            'url' => $this->urlGenerator->homepage($channel, $localeCode),
        ];
        if (!$isInstagram) {
            $website['webview_height_ratio'] = 'full';
        }

        $actions = [
            $this->postback($settings['list_products'], ['type' => IncomingMessage::ACTION_LIST_ITEMS, 'page' => 1]),
            $this->postback($settings['my_cart'], ['type' => IncomingMessage::ACTION_MY_CART]),
            $this->postback($settings['empty_cart'], ['type' => IncomingMessage::ACTION_EMPTY_CART]),
            $this->postback($settings['checkout'], ['type' => IncomingMessage::ACTION_CHECKOUT]),
            $website,
        ];

        if ($isInstagram) {
            $this->graphClient->post('me/messenger_profile', [
                'platform' => 'instagram',
                'persistent_menu' => [['locale' => 'default', 'call_to_actions' => $actions]],
            ], $bot->getPageAccessToken());

            return;
        }

        $this->graphClient->post('me/messenger_profile', [
            'get_started' => ['payload' => json_encode(['type' => IncomingMessage::ACTION_GET_STARTED], \JSON_THROW_ON_ERROR)],
            'greeting' => [['locale' => 'default', 'text' => mb_substr($settings['greeting'], 0, self::MAX_GREETING)]],
            'persistent_menu' => [['locale' => 'default', 'composer_input_disabled' => false, 'call_to_actions' => $actions]],
        ], $bot->getPageAccessToken());
    }

    /**
     * @param array<string, scalar> $payload
     *
     * @return array<string, string>
     */
    private function postback(string $title, array $payload): array
    {
        return ['type' => 'postback', 'title' => mb_substr($title, 0, 30), 'payload' => json_encode($payload, \JSON_THROW_ON_ERROR)];
    }
}
