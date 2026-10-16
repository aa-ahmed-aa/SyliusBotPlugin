<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Functional;

use Sylius\Component\Core\Model\OrderInterface;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Entity\BotMessageInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;
use Tests\SyliusBotPlugin\Functional\Stub\FakeGraphHttpClient;

final class WebhookTest extends FunctionalTestCase
{
    public function testVerificationHandshake(): void
    {
        $this->client->request('GET', '/webhook/messenger?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=1234');
        self::assertResponseIsSuccessful();
        self::assertSame('1234', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/webhook/instagram?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1234');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnsignedCallsAreRejected(): void
    {
        $this->createBot();
        $this->postWebhook(['message' => ['text' => 'products']], signature: 'sha256=forged');

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], FakeGraphHttpClient::sentMessages());
    }

    public function testMessagesForUnknownPagesAreIgnored(): void
    {
        $this->postWebhook(['message' => ['text' => 'products']]);

        self::assertResponseIsSuccessful();
        self::assertSame([], FakeGraphHttpClient::sentMessages());
    }

    public function testShoppingThroughMessenger(): void
    {
        $this->createBot();
        $product = $this->simpleProduct();

        // Browsing creates the subscriber, its customer and a cart.
        $this->postWebhook(['message' => ['text' => 'products']]);
        self::assertResponseIsSuccessful();

        $carousel = $this->lastSentMessage();
        self::assertSame('5550001', $carousel['recipient']['id']);
        self::assertSame('RESPONSE', $carousel['messaging_type']);
        self::assertSame('generic', $carousel['message']['attachment']['payload']['template_type']);
        self::assertNotEmpty($carousel['message']['attachment']['payload']['elements']);

        $subscriber = $this->subscriber();
        self::assertSame('Sara', $subscriber->getFirstName());
        self::assertSame('5550001@messenger.bot', $subscriber->getCustomer()?->getEmail());

        // Adding to the cart.
        $this->postWebhook(['postback' => ['payload' => json_encode(['type' => 'add_to_cart', 'product_id' => $product->getId()])]]);
        self::assertStringContainsString((string) $product->getName(), $this->lastSentMessage()['message']['attachment']['payload']['text']);

        $cart = $this->cart($subscriber);
        self::assertCount(1, $cart->getItems());
        self::assertSame(1, $cart->getItems()->first()->getQuantity());

        // Checking out links to the cart, and the link loads it in the browser.
        $this->postWebhook(['message' => ['text' => 'checkout']]);
        $buttons = $this->lastSentMessage()['message']['attachment']['payload']['buttons'];
        self::assertSame('web_url', $buttons[0]['type']);
        self::assertStringContainsString('/bot_checkout/' . $cart->getTokenValue(), $buttons[0]['url']);

        $this->client->request('GET', (string) parse_url($buttons[0]['url'], \PHP_URL_PATH));
        self::assertResponseRedirects();
        self::assertStringContainsString('/cart', (string) $this->client->getResponse()->headers->get('Location'));

        // Every message is kept for the admin, in both directions.
        $messages = static::getContainer()->get('sylius_bot.repository.bot_message')->findLatestForSubscriber($subscriber);
        self::assertGreaterThanOrEqual(6, count($messages));
        self::assertSame(BotMessageInterface::DIRECTION_INBOUND, $messages[0]->getDirection());
        self::assertSame('products', $messages[0]->getText());
    }

    public function testItemsOfAnotherCartCannotBeRemoved(): void
    {
        $this->createBot();
        $this->postWebhook(['postback' => ['payload' => json_encode(['type' => 'add_to_cart', 'product_id' => $this->simpleProduct()->getId()])]]);
        $item = $this->cart($this->subscriber())->getItems()->first();

        $this->postWebhook([
            'sender' => ['id' => '5550002'],
            'postback' => ['payload' => json_encode(['type' => 'remove_item_from_cart', 'item_id' => $item->getId()])],
        ]);

        self::assertSame('That item is no longer in your cart.', $this->lastSentMessage()['message']['text']);
        self::assertCount(1, $this->cart($this->subscriber())->getItems());
    }

    public function testFreeTextFallsBackToTheMenu(): void
    {
        $this->createBot();
        $this->postWebhook(['message' => ['text' => 'Do you ship to Cairo?']]);

        $payload = $this->lastSentMessage()['message']['attachment']['payload'];
        self::assertSame('button', $payload['template_type']);
        self::assertCount(3, $payload['buttons']);
    }

    public function testEchoesAreNotAnswered(): void
    {
        $this->createBot();
        $this->postWebhook(['message' => ['text' => 'hello', 'is_echo' => true]]);

        self::assertSame([], FakeGraphHttpClient::sentMessages());
    }

    public function testInstagramGetsTextAlongsideTemplates(): void
    {
        $this->createBot(BotInterface::CHANNEL_INSTAGRAM);
        $this->postWebhook(['message' => ['text' => 'products']], 'instagram');
        self::assertResponseIsSuccessful();

        $sent = FakeGraphHttpClient::sentMessages();
        self::assertCount(2, $sent);
        self::assertStringContainsString('/products/', $sent[0]['body']['message']['text']);
        self::assertArrayNotHasKey('messaging_type', $sent[1]['body']);
        self::assertArrayNotHasKey('image_aspect_ratio', $sent[1]['body']['message']['attachment']['payload']);

        self::assertSame('instagram', $this->subscriber()->getChannel());
    }

    private function subscriber(): BotSubscriberInterface
    {
        $this->entityManager->clear();

        /** @var BotSubscriberInterface|null $subscriber */
        $subscriber = static::getContainer()->get('sylius_bot.repository.bot_subscriber')->findOneBy(['botSubscriberId' => '5550001']);
        self::assertNotNull($subscriber);

        return $subscriber;
    }

    private function cart(BotSubscriberInterface $subscriber): OrderInterface
    {
        $cart = static::getContainer()->get('sylius.repository.order')->findLatestCartByChannelAndCustomer($this->defaultChannel(), $subscriber->getCustomer());
        self::assertInstanceOf(OrderInterface::class, $cart);

        return $cart;
    }
}
