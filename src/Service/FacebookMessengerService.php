<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\FacebookDriver;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Pagerfanta\Pagerfanta;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

/**
 * Class FacebookMessengerService
 * @package Ahmedkhd\SyliusBotPlugin\Service
 */
class FacebookMessengerService extends BotService
{
    private $request;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $defaultLocaleCode;

    /**
     * @var ChannelInterface
     */
    protected $defaultChannel;

    protected $httpClient;

    /**
     * FacebookMessengerService constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(ContainerInterface $container,LoggerInterface $logger, SerializerInterface $serializer)
    {
        parent::__construct($container, $logger, $serializer);
        /**
         * @psalm-suppress PossiblyFalsePropertyAssignmentValue
         */
        $this->baseUrl = getenv('APP_URL') === false ? "https://www.google.com" : getenv('APP_URL');
        $this->defaultLocaleCode = $this->container->get('sylius.context.locale')->getLocaleCode();
        $this->defaultChannel = $this->container->get('sylius.context.channel')->getChannel();
        $this->httpClient = new Client(['base_uri' => getenv('FACEBOOK_GRAPH_URL')]);
    }

    /**
     * @param null $request
     */
    public function flow($request = null): void
    {
        $this->request = $request;

        //create menu
        $this->updatePresistentMenu();

        DriverManager::loadDriver(FacebookDriver::class);
        $botman = BotManFactory::create($this->getConfiguration());

//        $botman = $this->fallbackMessage($botman);
        $botman = $this->listProducts($botman);
        $botman = $this->removeFromCart($botman);
        $botman = $this->addToCart($botman);
        $botman = $this->listItemsInCart($botman);

        $botman->listen();
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function fallbackMessage(Botman $botman): BotMan
    {
        $botman->fallback(function (BotMan $botman): void {
            /**
             * @phpstan-ignore-next-line
             * @psalm-suppress InvalidArgument
             */
            $botman->reply(ButtonTemplate::create("Sorry {$botman->getUser()->getFirstName()} i can't understand you💅")
                ->addButton(ElementButton::create('List Products')
                    ->type('postback')
                    ->payload('list_items')
                )
                ->addButton(ElementButton::create('Go to the website')
                    ->url($this->baseUrl)
                )
            );
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function listProducts(Botman $botman): BotMan
    {
        /** @var Pagerfanta $products */
        $products = $this->container->get('sylius.repository.product')->createPaginator();

        $botman->hears("list_items", function (BotMan $botman) use ($products): void {
            $elements = $this->wrapProducts($products->getCurrentPageResults(), $this->defaultLocaleCode, $this->defaultChannel);
            $botman->reply(GenericTemplate::create()
                ->addImageAspectRatio(GenericTemplate::RATIO_SQUARE)
                ->addElements($elements ?? []));
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function removeFromCart(Botman $botman): BotMan
    {
        $botman->hears('remove_item_from_cart', function(BotMan $botman, string $id): void {
            $botman->reply("i will removed item with id {$id} from your Cart");
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function addToCart(Botman $botman): BotMan
    {
        $payload = $this->getPayload("add_to_cart");
        if(!empty($payload)) {
            $product_id = $payload["product_id"];
            $this->sendNormalMessage("product with id {$product_id} will be added to your cart");
        }

        return $botman;
    }

    /**
     * @param string $type
     * @return bool|mixed
     */
    public function getPayload(string $type)
    {
        $entry = $this->request->get('entry');

        if(!empty($entry) &&
            isset($entry[0]) &&
            isset($entry[0]['messaging']) &&
            isset($entry[0]['messaging'][0]) &&
            isset($entry[0]['messaging'][0]["postback"]) &&
            isset($entry[0]['messaging'][0]["postback"]["payload"])  &&
            $this->isJson($entry[0]['messaging'][0]["postback"]["payload"])
        ) {
            $payload = \GuzzleHttp\json_decode($entry[0]['messaging'][0]["postback"]["payload"], true);

            if(
                is_array($payload) &&
                isset($payload["type"]) &&
                $payload["type"] === $type
            ) {
             return $payload;
            }
        }
        return false;
    }

    /**
     * @param $string
     * @return bool
     */
    public function  isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function listItemsInCart(Botman $botman): BotMan
    {
        $botman->hears('mycart', function(BotMan $botman): void {
            $botman->reply("i will list items in your cart: {$botman->getUser()->getFirstName()}");
        });
        return $botman;
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return [
            'facebook' => [
                'token' => getenv('FACEBOOK_PAGE_ACCESS_TOKEN'),
                'app_secret' => getenv('FACEBOOK_APP_SECRET'),
                'verification'=> getenv('FACEBOOK_VERIFICATION'),
            ]
        ];
    }

    /**
     * @return Response
     */
    public function updatePresistentMenu(): Response
    {
        $body = [
            "setting_type"  =>  "call_to_actions",
            "thread_state"  =>  "existing_thread",
            "call_to_actions" => [
                [
                    "type" => "postback",
                    "title" => "List Products",
                    "payload" => "list_items"
                ],
                [
                    "type" => "postback",
                    "title" => "My Cart",
                    "payload" => "mycart"
                ],
                [
                    "type" => "web_url",
                    "title" => "Visit my Website",
                    "url" => "{$this->baseUrl}"
                ]
            ]
        ];
        $response = $this->sendFacebookRequest(
            "/v2.8/me/thread_settings?access_token=" . getenv('FACEBOOK_PAGE_ACCESS_TOKEN'),
            $body,
            Request::METHOD_POST
        );

        return new Response($response->getBody());
    }

    public function sendNormalMessage(string $text)
    {
        $body = [
            "messaging_type" => "RESPONSE",
            "recipient" => [
                "id" => $this->request->get('entry')[0]['messaging'][0]["sender"]["id"]
            ],
              "message" => [
                  "text" => $text
              ]
        ];

        $response = $this->sendFacebookRequest(
            "/" . getenv('FACEBOOK_GRAPH_VERSION') . "/me/messages?access_token=" . getenv('FACEBOOK_PAGE_ACCESS_TOKEN'),
            $body,
            Request::METHOD_POST
        );
    }

    /**
     * @param string $url
     * @param array|null $body
     * @param string|null $method
     * @return ResponseInterface|null
     */
    public function sendFacebookRequest(string $url, ?array $body = [], ?string $method = Request::METHOD_GET)
    {
        try {
            return $this->httpClient->request(
                $method,
                $url,
                $this->getRequestOption($body)
            );
        } catch (GuzzleException $e) {
            $this->logger->critical($e->getMessage());
        }
        return null;
    }

    /**
     * @param array $body
     * @return mixed
     */
    public function getRequestOption(array $body)
    {
        $options[RequestOptions::HEADERS] = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $options[RequestOptions::JSON] = $body;

        return $options;
    }
}
