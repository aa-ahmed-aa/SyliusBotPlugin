<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Functional;

use Sylius\Component\Core\Model\AdminUserInterface;
use SyliusBotPlugin\Entity\BotInterface;
use Tests\SyliusBotPlugin\Functional\Stub\FakeGraphHttpClient;

final class AdminTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var AdminUserInterface $admin */
        $admin = static::getContainer()->get('sylius.repository.admin_user')->findOneBy([], ['id' => 'ASC']);
        $this->client->loginUser($admin, 'admin');
    }

    public function testConnectingAPageThroughFacebookLogin(): void
    {
        $crawler = $this->client->request('GET', '/admin/bots/messenger');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.page-title', 'Messenger');
        self::assertSame(1, $crawler->filter('a[href$="/admin/bots/messenger/login"]')->count() > 0 ? 1 : 0);

        // "Continue with Facebook" goes to the OAuth dialog with a state...
        $this->client->request('GET', '/admin/bots/messenger/login');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://www.facebook.com/v23.0/dialog/oauth?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        self::assertStringContainsString('pages_messaging', $query['scope']);

        // ...a forged state is refused...
        $this->client->request('GET', '/admin/bots/facebook/callback?code=abc&state=forged');
        self::assertResponseRedirects('/admin/bots/messenger');
        self::assertSame([], array_filter(FakeGraphHttpClient::$requests, static fn (array $r): bool => $r['path'] === 'oauth/access_token'));

        // ...and the real one logs in.
        $this->client->request('GET', '/admin/bots/messenger/login');
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $query);
        $this->client->request('GET', '/admin/bots/facebook/callback?code=abc&state=' . $query['state']);
        self::assertResponseRedirects('/admin/bots/messenger');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Test Admin');
        self::assertSelectorTextContains('body', 'Test Page');
        self::assertStringNotContainsString('page-token', (string) $this->client->getResponse()->getContent());

        $this->client->submit($crawler->filter('form[action$="/admin/bots/messenger/connect"]')->form());
        self::assertResponseRedirects('/admin/bots/messenger');

        $bot = static::getContainer()->get('sylius_bot.repository.bot')->findOneByChannelAndPageId('messenger', FakeGraphHttpClient::PAGE_ID);
        self::assertInstanceOf(BotInterface::class, $bot);
        self::assertSame('page-token', $bot->getPageAccessToken());

        $paths = array_map(static fn (array $r): string => $r['method'] . ' ' . $r['path'], FakeGraphHttpClient::$requests);
        self::assertContains('POST ' . FakeGraphHttpClient::PAGE_ID . '/subscribed_apps', $paths);
        self::assertContains('POST me/messenger_profile', $paths);

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Receiving messages');
    }

    public function testConnectingAnInstagramAccount(): void
    {
        $this->client->request('GET', '/admin/bots/instagram/login');
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $query);
        self::assertStringContainsString('instagram_manage_messages', $query['scope']);
        $this->client->request('GET', '/admin/bots/facebook/callback?code=abc&state=' . $query['state']);
        self::assertResponseRedirects('/admin/bots/instagram');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', '@teststore');

        $this->client->submit($crawler->filter('form[action$="/admin/bots/instagram/connect"]')->form());

        $bot = static::getContainer()->get('sylius_bot.repository.bot')->findOneByChannelAndPageId('instagram', FakeGraphHttpClient::INSTAGRAM_ID);
        self::assertInstanceOf(BotInterface::class, $bot);
        self::assertSame(FakeGraphHttpClient::PAGE_ID, $bot->getFacebookPageId());
        self::assertSame('@teststore', $bot->getPageName());

        $profile = array_values(array_filter(FakeGraphHttpClient::$requests, static fn (array $r): bool => $r['path'] === 'me/messenger_profile'));
        self::assertSame('instagram', $profile[0]['body']['platform']);
        self::assertArrayNotHasKey('greeting', $profile[0]['body']);
    }

    public function testEditingPageSettings(): void
    {
        $bot = $this->createBot();

        $crawler = $this->client->request('GET', sprintf('/admin/bots/messenger/%d/settings', $bot->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('#sylius-bot-settings')->form();
        $form['bot_settings[greeting]'] = 'Welcome {{user_first_name}}!';
        $form['bot_settings[list_products]'] = 'Shop';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $bot = static::getContainer()->get('sylius_bot.repository.bot')->find($bot->getId());
        self::assertSame('Shop', $bot->getSetting('list_products'));
        self::assertSame('Welcome {{user_first_name}}!', $bot->getSetting('greeting'));

        $profile = array_values(array_filter(FakeGraphHttpClient::$requests, static fn (array $r): bool => $r['path'] === 'me/messenger_profile'));
        self::assertSame('Shop', $profile[0]['body']['persistent_menu'][0]['call_to_actions'][0]['title']);
    }

    public function testDisconnectNeedsACsrfToken(): void
    {
        $bot = $this->createBot();

        $this->client->request('POST', sprintf('/admin/bots/messenger/%d/disconnect', $bot->getId()));
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(static::getContainer()->get('sylius_bot.repository.bot')->find($bot->getId()));
    }

    public function testSubscriberGridAndPage(): void
    {
        $this->createBot();
        $this->postWebhook(['message' => ['text' => 'products']]);

        $this->client->request('GET', '/admin/bot-subscribers/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Sara Ali');

        $subscriber = static::getContainer()->get('sylius_bot.repository.bot_subscriber')->findOneBy(['botSubscriberId' => '5550001']);
        $this->client->request('GET', '/admin/bot-subscribers/' . $subscriber->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.page-title', 'Sara Ali');
        self::assertSelectorExists('.sylius-bot-chat__row--in');
        self::assertSelectorExists('.sylius-bot-chat__row--out');
        self::assertSelectorTextContains('#conversation', 'products');
    }
}
