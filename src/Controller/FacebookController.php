<?php
declare(strict_types=1);

namespace SyliusBotPlugin\Controller;


use Sylius\Component\Resource\Repository\RepositoryInterface;
use SyliusBotPlugin\Entity\Bot;
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

    public function facebookHome(Request $request): Response
    {
        /** @var array $options */
        $options = $request->attributes->get('_sylius');

        /** @var string $template */
        $template = $options["template"];

        /** @var string $formType */
        $formType = $options["form"];

        $botService = $this->container->get("sylius_bot_plugin.service.bot_configuration");

        /** @var Bot $botConfiguration */
        $botConfiguration = $botService->getPersistentMenuWithDefaults();

        /** @var array $persistentMenu */
        $persistentMenu = json_decode($botConfiguration->getPersistentMenu());

        $form = $this->formFactory->createNamed('', $formType, $persistentMenu);

        if ($request->isMethod("POST") && $form->handleRequest($request)->isValid()) {
            /** @var array $persistentMenu */
            $persistentMenu = $form->getData();

            try {
                $botConfiguration->setPersistentMenu(json_encode($persistentMenu));
//                dd($botConfiguration);
                $bot = $botService->add($botConfiguration);
//                $this->facebookMessengerService->setGetStartedButtonPayload();
//                $this->facebookMessengerService->updatePersistentMenu($menuJson);
            } catch (GuzzleException $e) {
                $this->logger->critical($e->getMessage());
            }
            return $this->redirectToRoute("sylius_bot_plugin_facebook_persistent_menu");
        }

        return $this->render($template, ['form' => $form->createView()]);
    }

    public function facebookConnectPage(Request $request): JsonResponse
    {
        $pageBody = $request->request->all();

        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->container->get('sylius_bot_plugin.repository.bot');

        $bot = $botRepository->findOneBy(["page_id" => $pageBody["id"]]);

        if($bot === null) {
            $botService = $this->container->get("sylius_bot_plugin.service.bot_configuration");
            $response = $botService->sendFacebookRequest(
                "/" . getenv("FACEBOOK_GRAPH_VERSION") . "/oauth/access_token".
                    "?grant_type=fb_exchange_token".
                    "&client_id=" . getenv('FACEBOOK_APP_ID').
                    "&client_secret=" . getenv("FACEBOOK_APP_SECRET").
                    "&fb_exchange_token=" . $pageBody["access_token"]
                );

            /** @var Bot $newBot */
            $newBot = $this->container->get("sylius_bot_plugin.factory.bot")->createNew();

            /** @var string $longLivedAccessToken */
            $longLivedAccessToken = json_decode($response->getBody()->getContents())->access_token;

            $newBot->setPageAccessToken($longLivedAccessToken);
            $newBot->setChannelType('messenger');
            $newBot->setPageId($pageBody["id"]);
            $newBot->setPageName($pageBody["name"]);
            $newBot->setPageImageUrl($pageBody["page_image_url"]);
            $newBot->setDisabled('false');

            $botRepository->add($newBot);
        } else {
            dd("FOUND", $bot);
        }

        return new JsonResponse([
            "success" => true
        ]);
    }

}
