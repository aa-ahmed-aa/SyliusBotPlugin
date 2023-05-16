<?php
declare(strict_types=1);

namespace SyliusBotPlugin\Controller;


use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use SyliusBotPlugin\Entity\Bot;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Service\BotConfigurationService;
use SyliusBotPlugin\Service\BotServiceInterface;
use SyliusBotPlugin\Service\FacebookMessengerService;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FacebookController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FacebookMessengerService
     */
    protected $facebookMessengerService;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * FacebookController constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param FacebookMessengerService $facebookMessengerService
     * @param FormFactoryInterface $formFactory
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        FacebookMessengerService $facebookMessengerService,
        FormFactoryInterface $formFactory
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->facebookMessengerService = $facebookMessengerService;
        $this->formFactory = $formFactory;
    }

    public function updatePersistentMenu(Request $request): Response
    {
        /** @var Array<string, string> $options */
        $options = $request->attributes->get('_sylius');

        /** @var BotConfigurationService $botService */
        $botService = $this->container->get("sylius_bot_plugin.service.bot_configuration");

        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->container->get('sylius_bot_plugin.repository.bot');

        $botConfiguration = $botService->getPersistentMenuWithDefaults();

        /** @var array $persistentMenu */
        $persistentMenu = json_decode($botConfiguration, true);

        $form = $this->formFactory->createNamed('', $options["form"], $persistentMenu);

        if ($request->isMethod("POST") && $form->handleRequest($request)->isValid()) {
            /** @var array $persistentMenu */
            $persistentMenu = $form->getData();

            try {
                /** @var Bot $bot */
                $bot = $botRepository->findOneBy(['id' => $persistentMenu["bot_id"]]);

                /** @var string $jsonPersistentMenu */
                $jsonPersistentMenu = json_encode($persistentMenu);

                $bot->setPersistentMenu($jsonPersistentMenu);

                $botRepository->add($bot);

                $botService->updateBotConfiguration($bot);

                $this->addFlash("success", "Successfuly added Connected Page");
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
            return $this->redirectToRoute("sylius_bot_plugin_facebook_persistent_menu");
        }

        $connectedPage = $botRepository->findAll();

        return $this->render($options["template"], [
            'form' => $form->createView(),
            'connected_pages' => $connectedPage
        ]);
    }

    public function getPagePersistentMenu(Request $request): JsonResponse
    {
        /** @var string $pageId */
        $pageId = $request->get('page_id');

        /** @var BotConfigurationService $botService */
        $botService = $this->container->get("sylius_bot_plugin.service.bot_configuration");

        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->container->get('sylius_bot_plugin.repository.bot');

        /** @var Bot|null $bot */
        $bot = $botRepository->findOneBy(["id" => $pageId]);

        if ($bot === null) {
            return new JsonResponse([
                "success" => false,
                "reaspn" => "Page with Id $pageId Not Found",
            ], 404);
        }
        /** @var string $persistentMenu */
        $persistentMenu = $botService->getPersistentMenuWithDefaults($bot);

        return new JsonResponse(
            json_decode($persistentMenu, true)
        );
    }

    public function connectedPages(): JsonResponse
    {
        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->container->get('sylius_bot_plugin.repository.bot');

        $pages = [];

        $connectedPages = $botRepository->findAll();

        /** @var Bot $page */
        foreach ($connectedPages as $index => $page) {
            $pages[$index]["page_name"] = $page->getPageName();
            $pages[$index]["id"] = $page->getId();
            $pages[$index]["page_id"] = $page->getPageId();
        }

        return new JsonResponse([
            "pages" => $pages,
        ]);
    }

    public function connectPage(Request $request): JsonResponse
    {
        $envs = array_merge($_ENV, getenv());

        /** @var Array<string, string> $pageBody */
        $pageBody = $request->request->all();

        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->container->get('sylius_bot_plugin.repository.bot');

        /** @var BotServiceInterface $botService */
        $botService = $this->container->get("sylius_bot_plugin.service.bot_configuration");

        /** @var BotInterface|null $bot */
        $bot = $botRepository->findOneBy(["page_id" => $pageBody["id"]]);

        if($request->get('action') === 'disconnect') {
            /** @var EntityManagerInterface $botEntityManager */
            $botEntityManager = $this->container->get('sylius_bot_plugin.manager.bot');

            $botEntityManager->remove($bot);
            $botEntityManager->flush();

            return new JsonResponse([
                "deleted" => true,
            ]);
        }

        if ($bot === null) {
            /** @var Bot $bot */
            $bot = $this->container->get("sylius_bot_plugin.factory.bot")->createNew();
        }

        /** @var mixed $response */
        $response = $botService->sendFacebookRequest(
            "/" . $envs["FACEBOOK_GRAPH_VERSION"] . "/oauth/access_token".
                "?grant_type=fb_exchange_token".
                "&client_id=" . $envs['FACEBOOK_APP_ID'].
                "&client_secret=" . $envs["FACEBOOK_APP_SECRET"].
                "&fb_exchange_token=" . $pageBody["access_token"]
        );

        /** @var string $longLivedAccessToken */
        $longLivedAccessToken = json_decode($response->getBody()->getContents())->access_token;

        $bot->setPageAccessToken($longLivedAccessToken);
        $bot->setChannelType('messenger');
        $bot->setPageId($pageBody["id"]);
        $bot->setPageName($pageBody["name"]);
        $bot->setPageImageUrl($pageBody["page_image_url"]);
        $bot->setDisabled(false);

        $botRepository->add($bot);

        /** @var ResponseInterface $subscribeToPage */
        $subscribeToPage = $botService->sendFacebookRequest(
            "/" . $envs["FACEBOOK_GRAPH_VERSION"] . "/" . $bot->getPageId() . "/subscribed_apps",
            [
                "access_token" => $bot->getPageAccessToken(),
                "subscribed_fields" => "messages,messaging_postbacks"
            ],
            "POST"
        );

        if($subscribeToPage->getStatusCode() === 200) {
            $this->logger->info("Successfuly subscribe to page : {$bot->getPageId()}");
        }

        return new JsonResponse([
            "success" => true
        ]);
    }

}
