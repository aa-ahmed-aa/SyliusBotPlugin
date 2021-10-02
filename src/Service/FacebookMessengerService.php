<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriberInterface;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\Extensions\QuickReplyButton;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
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
    public $channelName = "messenger";

    /** @var Request */
    private $request;

    /** @var string */
    protected $baseUrl;

    /** @var string */
    protected $defaultLocaleCode;

    /** @var ChannelInterface */
    protected $defaultChannel;

    /** @var Client */
    protected $httpClient;


    /**
     * FacebookMessengerService constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param RedisAdapter $redisAdapter
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
        $user = $this->getSubscriber();

        //create menu
        $this->updatePresistentMenu();

        $this->listProducts();
        $this->removeFromCart();
        $this->addToCart();
        $this->listItemsInCart();
    }

    /**
     * Send Fallback message
     */
    public function fallbackMessage()
    {
        $this->sendMessage(ButtonTemplate::create("Sorry i can't understand you💅")
            ->addButton(ElementButton::create('List Products')
                ->type('postback')
                ->payload(\GuzzleHttp\json_encode([
                    "type" => "list_items",
                    "page" => 1
                ]))
            )
            ->addButton(ElementButton::create('Go to the website')
                ->url($this->baseUrl)
            ));
    }

    /**
     * List products
     */
    public function listProducts()
    {
        $payload = $this->getPayload("list_items");
        if(!empty($payload)) {
            /** @var Pagerfanta $productsPaginator */
            $productsPaginator = $this->container->get('sylius.repository.product')->createPaginator();
            $productsPaginator->setCurrentPage($payload['page']);
            $productsPaginator->setMaxPerPage(9);

            $elements = $this->wrapProducts($productsPaginator->getCurrentPageResults(), $this->defaultLocaleCode, $this->defaultChannel, $payload['page']);

            $this->sendMessage(
                GenericTemplate::create()
                    ->addImageAspectRatio(GenericTemplate::RATIO_SQUARE)
                    ->addElements($elements ?? [])
                    ->addQuickReply(
                        QuickReplyButton::create("My Cart")
                            ->type('text')
                            ->payload(\GuzzleHttp\json_encode(["type" => "mycart"]))
                            ->imageUrl('https://i.pinimg.com/originals/15/4f/df/154fdf2f2759676a96e9aed653082276.png')
                    )
            );
        }
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart()
    {
        $payload = $this->getPayload("remove_item_from_cart");
        if(!empty($payload)) {
            $product_id = $payload["product_id"];
            $this->sendMessage(["text" => "product with id {$product_id} will be remove from your cart"]);
        }
    }

    /**
     * Add item to cart
     */
    public function addToCart()
    {
        $payload = $this->getPayload("add_to_cart");
        if(!empty($payload)) {
            $product_id = $payload["product_id"];
            $this->sendMessage(["text" => "product with id {$product_id} will be added to your cart"]);
        }
    }

    /**
     * @param string $type
     * @return bool|mixed
     */
    public function getPayload(string $type)
    {
        $entry = $this->arrayFlatten($this->request->get('entry'));

        if(!key_exists('payload', $entry)) {
            $this->fallbackMessage();
            exit;
        }

        $payload = $entry["payload"];

        if($payload && $this->isJson($payload)) {
            $payload = \GuzzleHttp\json_decode($payload, true);

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
     * Convert array multidimensional array to flat array
     * @param $array
     * @return array|bool
     */
    function arrayFlatten($array) {
        if (!is_array($array)) {
            return false;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->arrayFlatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }
        return $result;
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
     * List items in cart
     */
    public function listItemsInCart()
    {
        if(!empty($this->getPayload("mycart"))) {
            $this->sendMessage(["text" => "i will list items in your cart"]);
        }
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
                    "payload" => \GuzzleHttp\json_encode([
                        "type" => "list_items",
                        "page" => 1
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

        return new Response("Done");
    }

    /**
     * @param string|array|OutgoingMessage|Question $message
     * @return ResponseInterface|null
     */
    public function sendMessage($message)
    {
        $body = [
            "messaging_type" => "RESPONSE",
            "recipient" => [
                "id" => $this->request->get('entry')[0]['messaging'][0]["sender"]["id"]
            ],
            "message" => $message
        ];

        return $this->sendFacebookRequest(
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

    public function getSubscriber()
    {
        $botSubscriberId = isset($this->request->get('entry')[0]['messaging'][0]['sender']['id']) ? $this->request->get('entry')[0]['messaging'][0]['sender']['id'] : null;

        /** @var BotSubscriberInterface $botSubscriber */
        $botSubscriber = $this->container->get('sylius_bot_plugin.repository.bot_subscriber')->findOneBy([ 'botSubscriberId' => $botSubscriberId]);

        if(empty($botSubscriber)) {
            $fields = 'name,first_name,last_name,profile_pic,locale,timezone,gender';

            $response = $this->sendFacebookRequest("/{$botSubscriberId}?fields={$fields}&access_token=".getenv('FACEBOOK_PAGE_ACCESS_TOKEN'));
            $subscriberData = \GuzzleHttp\json_decode($response->getBody(), true);

            /** @var BotSubscriberInterface $botSubscriber */
            $botSubscriber = $this->createBotSubscriber($subscriberData);
        }

        return $botSubscriber;
    }

    public function createBotSubscriber($subscriberData)
    {
        /** @var BotSubscriberInterface $botSubscriber */
        $botSubscriber = $this->container->get('sylius_bot_plugin.factory.bot_subscriber')->createNew();

        $botSubscriber->setChannel($this->channelName);
        $botSubscriber->setName($subscriberData["name"]);
        $botSubscriber->setFirstName($subscriberData["first_name"]);
        $botSubscriber->setLastName($subscriberData["last_name"]);
        $botSubscriber->setProfilePicture($subscriberData["profile_pic"]);
        $botSubscriber->setLocale($subscriberData["locale"]);
        $botSubscriber->setTimezone($subscriberData["timezone"]);
        $botSubscriber->setGender($subscriberData["gender"]);
        $botSubscriber->setBotSubscriberId($subscriberData["id"]);

        $this->container->get('sylius_bot_plugin.repository.bot_subscriber')->add($botSubscriber);

        return $botSubscriber;
    }
}
