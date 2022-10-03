<?php
declare(strict_types=1);

namespace SyliusBotPlugin\Controller;


use SyliusBotPlugin\Entity\Bot;
use SyliusBotPlugin\Service\FacebookMessengerService;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
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

    public function facebookConnect(Request $request): Response
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
}
