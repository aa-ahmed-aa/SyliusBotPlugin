<?php


namespace Ahmedkhd\SyliusBotPlugin\Controller;


use Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * WebhookController constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param FacebookMessengerService $facebookMessengerService
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        FacebookMessengerService $facebookMessengerService
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->facebookMessengerService = $facebookMessengerService;
    }

    public function facebookConnect(Request $request): Response
    {
        $options = $request->attributes->get('_sylius');

        $menuJson = [
            [
                "type" => "postback",
                "title" => "List Products",
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "list_items",
                    "page" => 1
                ])
            ],
            [
                "type" => "postback",
                "title" => "Order Summary",
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "order_summery"
                ])
            ],
            [
                "type" => "postback",
                "title" => "My Cart",
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "mycart"
                ])
            ],
            [
                "type" => "postback",
                "title" => "Empty Cart",
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "empty_cart"
                ])
            ],
            [
                "type" => "postback",
                "title" => "Checkout",
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "checkout"
                ])
            ],
//                [
//                    "type" => "web_url",
//                    "title" => "Visit my Website",
//                    "url" => getenv("APP_URL")
//                ]
        ];

        //create menu
        $this->facebookMessengerService->updatePresistentMenu($menuJson);

        $template = $options['template'];
        return $this->render($template);
    }
}
