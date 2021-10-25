<?php
declare(strict_types=1);

namespace Ahmedkhd\SyliusBotPlugin\Controller;


use Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService;
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

        /** @var array $data */
        $data = [
            "list_products" => "List products",
            "order_summery" => "Order summery",
            "my_cart" => "My cart",
            "empty_cart" => "Empty cart",
            "checkout" => "Checkout",
        ];
        $form = $this->formFactory->createNamed('', $formType, $data);

        if ($request->isMethod("POST") && $form->handleRequest($request)->isValid()) {
            /** @var array $persistentMenu */
            $persistentMenu = $form->getData();

            $menuJson = [
                [
                    "type" => "postback",
                    "title" => $persistentMenu["list_products"],
                    "payload" => \GuzzleHttp\json_encode([
                        "type" => "list_items",
                        "page" => 1
                    ])
                ],
                [
                    "type" => "postback",
                    "title" => $persistentMenu["order_summery"],
                    "payload" => \GuzzleHttp\json_encode([
                        "type" => "order_summery"
                    ])
                ],
                [
                    "type" => "postback",
                    "title" => $persistentMenu["my_cart"],
                    "payload" => \GuzzleHttp\json_encode([
                        "type" => "mycart"
                    ])
                ],
                [
                    "type" => "postback",
                    "title" => $persistentMenu["empty_cart"],
                    "payload" => \GuzzleHttp\json_encode([
                        "type" => "empty_cart"
                    ])
                ],
                [
                    "type" => "postback",
                    "title" => $persistentMenu["checkout"],
                    "payload" => \GuzzleHttp\json_encode([
                        "type" => "checkout"
                    ])
                ]
            ];

            try {
                $this->facebookMessengerService->setGetStartedButtonPayload();
                $this->facebookMessengerService->updatePersistentMenu($menuJson);
            } catch (GuzzleException $e) {
                $this->logger->critical($e->getMessage());
            }
            return $this->redirectToRoute("ahmedkhd_facebook_persistent_menu");
        }

        return $this->render($template, ['form' => $form->createView()]);
    }
}
