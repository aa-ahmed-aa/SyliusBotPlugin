<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Controller\Admin;

use SyliusBotPlugin\Connection\ChannelConnector;
use SyliusBotPlugin\Connection\ProfileConfigurator;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Facebook\FacebookLoginException;
use SyliusBotPlugin\Facebook\FacebookLoginManager;
use SyliusBotPlugin\Facebook\ManagedPage;
use SyliusBotPlugin\Facebook\ManagedPageProvider;
use SyliusBotPlugin\Form\BotSettingsType;
use SyliusBotPlugin\Graph\GraphApiException;
use SyliusBotPlugin\Graph\GraphClientInterface;
use SyliusBotPlugin\Repository\BotRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Admin screens for both channels: log in with Facebook, connect or disconnect
 * Pages / Instagram accounts, and edit each page's menu and greeting.
 */
final class BotController extends AbstractController
{
    public function __construct(
        private readonly BotRepositoryInterface $botRepository,
        private readonly GraphClientInterface $graphClient,
        private readonly FacebookLoginManager $loginManager,
        private readonly ManagedPageProvider $pageProvider,
        private readonly ChannelConnector $connector,
        private readonly ProfileConfigurator $profileConfigurator,
    ) {
    }

    public function index(string $channel): Response
    {
        $bots = $this->botRepository->findByChannel($channel);

        $delivery = [];
        foreach ($bots as $bot) {
            $delivery[(int) $bot->getId()] = $this->connector->getDeliveryState($bot);
        }

        $pages = [];
        $facebookError = null;
        $token = $this->loginManager->getUserToken();

        if ($token !== null) {
            try {
                $pages = $this->pageProvider->getPages($token);
            } catch (GraphApiException $exception) {
                $facebookError = $exception->getMessage();
                if ($exception->isInvalidToken()) {
                    $this->loginManager->forget();
                }
            }
        }

        $connectedIds = array_map(static fn (BotInterface $bot): string => (string) $bot->getPageId(), $bots);

        return $this->render('@SyliusBotPlugin/admin/bot/index.html.twig', [
            'channel' => $channel,
            'configured' => $this->graphClient->isConfigured(),
            'facebook_account' => $this->loginManager->getAccount(),
            'facebook_error' => $facebookError,
            'bots' => $bots,
            'delivery' => $delivery,
            'pages' => $pages,
            'connected_ids' => $connectedIds,
            'callback_url' => $this->generateUrl('sylius_bot_admin_facebook_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'webhook_url' => $this->generateUrl('sylius_bot_shop_webhook_' . $channel, [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    public function login(string $channel): RedirectResponse
    {
        if (!$this->graphClient->isConfigured()) {
            $this->addFlash('error', 'sylius_bot.ui.flash.not_configured');

            return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
        }

        return $this->redirect($this->loginManager->getAuthorizationUrl($channel, $this->callbackUrl()));
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $channel = $this->loginManager->handleCallback($request, $this->callbackUrl());
            $this->addFlash('success', 'sylius_bot.ui.flash.logged_in');
        } catch (FacebookLoginException $exception) {
            $channel = $exception->getChannel();
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
    }

    public function logout(Request $request, string $channel): RedirectResponse
    {
        $this->assertCsrf($request, 'sylius_bot_facebook_logout');
        $this->loginManager->forget();

        return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
    }

    public function connect(Request $request, string $channel): RedirectResponse
    {
        $this->assertCsrf($request, 'sylius_bot_connect');

        $token = $this->loginManager->getUserToken();
        if ($token === null) {
            $this->addFlash('error', 'sylius_bot.ui.flash.login_first');

            return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
        }

        try {
            // The Page token is looked up again here rather than posted by the
            // browser, so tokens never appear in the admin's HTML.
            $page = $this->pageProvider->findPage($token, (string) $request->request->get('page_id'));
            if (!$page instanceof ManagedPage) {
                $this->addFlash('error', 'sylius_bot.ui.flash.page_not_found');

                return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
            }

            $warnings = $this->connector->connect($page, $channel);
        } catch (GraphApiException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
        }

        $this->addFlash('success', 'sylius_bot.ui.flash.connected');
        foreach ($warnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
    }

    public function disconnect(Request $request, string $channel, int $id): RedirectResponse
    {
        $this->assertCsrf($request, 'sylius_bot_disconnect');
        $this->connector->disconnect($this->findBot($channel, $id));
        $this->addFlash('success', 'sylius_bot.ui.flash.disconnected');

        return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
    }

    public function repair(Request $request, string $channel, int $id): RedirectResponse
    {
        $this->assertCsrf($request, 'sylius_bot_repair');
        $warnings = $this->connector->repair($this->findBot($channel, $id));

        if ($warnings === []) {
            $this->addFlash('success', 'sylius_bot.ui.flash.repaired');
        }
        foreach ($warnings as $warning) {
            $this->addFlash('error', $warning);
        }

        return $this->redirectToRoute('sylius_bot_admin_bot_index', ['channel' => $channel]);
    }

    public function settings(Request $request, string $channel, int $id): Response
    {
        $bot = $this->findBot($channel, $id);

        $form = $this->createForm(BotSettingsType::class, $bot->getSettings());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, string|null> $settings */
            $settings = $form->getData();
            $bot->setSettings($settings);
            $this->botRepository->add($bot);

            try {
                $this->profileConfigurator->apply($bot);
                $this->addFlash('success', 'sylius_bot.ui.flash.settings_saved');
            } catch (GraphApiException|\RuntimeException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }

            return $this->redirectToRoute('sylius_bot_admin_bot_settings', ['channel' => $channel, 'id' => $id]);
        }

        return $this->render('@SyliusBotPlugin/admin/bot/settings.html.twig', [
            'channel' => $channel,
            'bot' => $bot,
            'form' => $form->createView(),
        ]);
    }

    private function findBot(string $channel, int $id): BotInterface
    {
        $bot = $this->botRepository->find($id);
        if (!$bot instanceof BotInterface || $bot->getChannelType() !== $channel) {
            throw new NotFoundHttpException();
        }

        return $bot;
    }

    private function callbackUrl(): string
    {
        return $this->generateUrl('sylius_bot_admin_facebook_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
