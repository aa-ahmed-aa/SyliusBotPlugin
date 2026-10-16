<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Connection;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Facebook\ManagedPage;
use SyliusBotPlugin\Graph\GraphApiException;
use SyliusBotPlugin\Graph\GraphClientInterface;
use SyliusBotPlugin\Repository\BotRepositoryInterface;

/**
 * Connects a Facebook Page (Messenger) or its linked Instagram account to the
 * store: keeps the Page token, subscribes the Page to the app's webhooks and
 * pushes the menu. Also reports whether a connected page still receives
 * messages, since a token or subscription can die on Meta's side unnoticed.
 */
final class ChannelConnector
{
    public const DELIVERY_OK = 'ok';

    public const DELIVERY_TOKEN_INVALID = 'token_invalid';

    public const DELIVERY_NOT_SUBSCRIBED = 'not_subscribed';

    public const DELIVERY_UNKNOWN = 'unknown';

    private const SUBSCRIBED_FIELDS = 'messages,messaging_postbacks';

    /**
     * @param FactoryInterface<BotInterface> $botFactory
     */
    public function __construct(
        private readonly BotRepositoryInterface $botRepository,
        private readonly FactoryInterface $botFactory,
        private readonly GraphClientInterface $graphClient,
        private readonly ProfileConfigurator $profileConfigurator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return string[] warnings: the page is connected, but something on Meta's side needs attention
     *
     * @throws \InvalidArgumentException when the page has no Instagram account to connect
     */
    public function connect(ManagedPage $page, string $channel): array
    {
        if ($channel === BotInterface::CHANNEL_INSTAGRAM && !$page->hasInstagram()) {
            throw new \InvalidArgumentException(sprintf('The Page "%s" has no linked Instagram professional account.', $page->name));
        }

        $accountId = $channel === BotInterface::CHANNEL_INSTAGRAM ? (string) $page->instagramId : $page->id;

        $bot = $this->botRepository->findOneByChannelAndPageId($channel, $accountId) ?? $this->botFactory->createNew();
        $bot->setChannelType($channel);
        $bot->setPageId($accountId);
        $bot->setFacebookPageId($page->id);
        $bot->setPageAccessToken($page->accessToken);
        $bot->setDisabled(false);

        if ($channel === BotInterface::CHANNEL_INSTAGRAM) {
            $bot->setPageName($page->instagramUsername !== null ? '@' . $page->instagramUsername : ($page->instagramName ?? $page->name));
            $bot->setPageImageUrl($page->instagramPictureUrl);
        } else {
            $bot->setPageName($page->name);
            $bot->setPageImageUrl($page->pictureUrl);
        }

        $this->entityManager->persist($bot);
        $this->entityManager->flush();

        return $this->repair($bot);
    }

    /**
     * Subscribes the Page to the app and pushes the menu again.
     *
     * @return string[] warnings
     */
    public function repair(BotInterface $bot): array
    {
        $warnings = [];

        try {
            $this->graphClient->post(sprintf('%s/subscribed_apps', $this->facebookPageId($bot)), [
                'subscribed_fields' => self::SUBSCRIBED_FIELDS,
            ], $bot->getPageAccessToken());
        } catch (GraphApiException $exception) {
            $warnings[] = $exception->getMessage();
        }

        try {
            $this->profileConfigurator->apply($bot);
        } catch (GraphApiException|\RuntimeException $exception) {
            $warnings[] = $exception->getMessage();
        }

        if ($warnings !== []) {
            $this->logger->warning('Page connected with warnings', ['page' => $bot->getPageId(), 'warnings' => $warnings]);
        }

        return $warnings;
    }

    public function disconnect(BotInterface $bot): void
    {
        $facebookPageId = $this->facebookPageId($bot);

        // Messenger and Instagram can share one Page: keep its subscription
        // while the other channel still uses it.
        $sharedWith = array_filter(
            $this->botRepository->findBy(['facebookPageId' => $facebookPageId]),
            static fn (object $other): bool => $other instanceof BotInterface && $other->getId() !== $bot->getId(),
        );

        if ($sharedWith === []) {
            try {
                $this->graphClient->delete(sprintf('%s/subscribed_apps', $facebookPageId), [], $bot->getPageAccessToken());
            } catch (GraphApiException $exception) {
                $this->logger->info('Could not unsubscribe the Page', ['page' => $facebookPageId, 'error' => $exception->getMessage()]);
            }
        }

        $this->entityManager->remove($bot);
        $this->entityManager->flush();
    }

    public function getDeliveryState(BotInterface $bot): string
    {
        try {
            $response = $this->graphClient->get(sprintf('%s/subscribed_apps', $this->facebookPageId($bot)), [], $bot->getPageAccessToken());
        } catch (GraphApiException $exception) {
            return $exception->isInvalidToken() ? self::DELIVERY_TOKEN_INVALID : self::DELIVERY_UNKNOWN;
        }

        foreach ((array) ($response['data'] ?? []) as $app) {
            if (is_array($app) &&
                (string) ($app['id'] ?? '') === $this->graphClient->getAppId() &&
                in_array('messages', (array) ($app['subscribed_fields'] ?? []), true)) {
                return self::DELIVERY_OK;
            }
        }

        return self::DELIVERY_NOT_SUBSCRIBED;
    }

    private function facebookPageId(BotInterface $bot): string
    {
        return (string) ($bot->getFacebookPageId() ?: $bot->getPageId());
    }
}
