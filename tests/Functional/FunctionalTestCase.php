<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use SyliusBotPlugin\Entity\Bot;
use SyliusBotPlugin\Entity\BotInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\SyliusBotPlugin\Functional\Stub\FakeGraphHttpClient;

/**
 * Runs against the test application with Sylius' default fixtures loaded and
 * graph.facebook.com replaced by FakeGraphHttpClient. Each test runs in a
 * transaction that is rolled back.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected const APP_SECRET = 'test-app-secret';

    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->getConnection()->beginTransaction();

        FakeGraphHttpClient::reset();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }

        parent::tearDown();
    }

    protected function createBot(string $channel = BotInterface::CHANNEL_MESSENGER): BotInterface
    {
        $bot = new Bot();
        $bot->setChannelType($channel);
        $bot->setPageId($channel === BotInterface::CHANNEL_INSTAGRAM ? FakeGraphHttpClient::INSTAGRAM_ID : FakeGraphHttpClient::PAGE_ID);
        $bot->setFacebookPageId(FakeGraphHttpClient::PAGE_ID);
        $bot->setPageName('Test Page');
        $bot->setPageAccessToken('page-token');

        $this->entityManager->persist($bot);
        $this->entityManager->flush();

        return $bot;
    }

    /** @param array<string, mixed> $event */
    protected function postWebhook(array $event, string $object = 'page', ?string $signature = null): void
    {
        $accountId = $object === 'instagram' ? FakeGraphHttpClient::INSTAGRAM_ID : FakeGraphHttpClient::PAGE_ID;
        $payload = json_encode([
            'object' => $object,
            'entry' => [[
                'id' => $accountId,
                'time' => time(),
                'messaging' => [$event + ['sender' => ['id' => '5550001'], 'recipient' => ['id' => $accountId], 'timestamp' => time()]],
            ]],
        ], \JSON_THROW_ON_ERROR);

        $this->client->request('POST', $object === 'instagram' ? '/webhook/instagram' : '/webhook/messenger', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256=' . hash_hmac('sha256', $payload, self::APP_SECRET),
        ], content: $payload);
    }

    protected function defaultChannel(): ChannelInterface
    {
        /** @var ChannelInterface $channel */
        $channel = static::getContainer()->get('sylius.repository.channel')->findOneBy(['enabled' => true], ['id' => 'ASC']);

        return $channel;
    }

    /** A product of the default channel with exactly one sellable variant. */
    protected function simpleProduct(): ProductInterface
    {
        $channel = $this->defaultChannel();

        /** @var ProductInterface $product */
        foreach (static::getContainer()->get('sylius.repository.product')->findBy(['enabled' => true]) as $product) {
            $variants = $product->getEnabledVariants()->filter(
                static fn (ProductVariantInterface $variant): bool => $variant->getChannelPricingForChannel($channel) !== null && (!$variant->isTracked() || $variant->isInStock()),
            );

            if ($product->hasChannel($channel) && $variants->count() === 1) {
                return $product;
            }
        }

        self::fail('The fixtures have no product with a single variant.');
    }

    /** @return array<string, mixed> */
    protected function lastSentMessage(): array
    {
        $sent = FakeGraphHttpClient::sentMessages();
        self::assertNotEmpty($sent, 'No message was sent.');

        return end($sent)['body'];
    }
}
