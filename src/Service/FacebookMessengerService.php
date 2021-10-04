<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriber;
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
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\OrderItemRepositoryInterface;
use Sylius\Component\Core\TokenAssigner\UniqueIdBasedOrderTokenAssigner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    const SUPPORTED_PAYLOAD = [
        "checkout",
        "empty_cart",
        "mycart",
        "list_items",
        "remove_item_from_cart",
        "add_to_cart"
    ];

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

    /** @var BotSubscriber */
    protected $user;

    /** @var OrderInterface */
    protected $order;

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
        $this->setSubscriber();

        //create menu
        $this->updatePresistentMenu();

        $this->listProducts();
        $this->removeFromCart();
        $this->addToCart();
        $this->checkout();
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
            $productsPaginator->setCurrentPage($payload['page'] ?? 1);
            $productsPaginator->setMaxPerPage(9);

            $elements = $this->wrapProducts($productsPaginator->getCurrentPageResults(), $this->defaultLocaleCode, $this->defaultChannel, $payload['page'] ?? 1);

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
            /** @var ProductInterface $product */
            $product = $this->container->get("sylius.repository.product")->findOneById($payload["product_id"]);

            /** @var OrderItemInterface $orderItem */
            $orderItem = $this->createOrderItem($product);

            $this->container->get('sylius.order_item_quantity_modifier')->modify($orderItem, 1);

            $this->order->addItem($orderItem);

            $this->container->get("sylius.order_processing.order_processor")->process($this->order);

            $this->container->get("sylius.repository.order")->add($this->order);

            $this->sendMessage(["text" => "*{$product->getName()}* add to your cart"]);
        }
    }

    /**
     * @param ProductInterface $product
     * @return OrderItemInterface
     */
    public function createOrderItem(ProductInterface $product)
    {
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->container->get("sylius.factory.order_item")->createNew();

        $orderItem->setOrder($this->order);
        $orderItem->setVariant($product->getVariants()->first());

        $this->container->get("sylius.repository.order_item")->add($orderItem);

        return $orderItem;
    }

    /**
     * @param string $type
     * @return bool|mixed
     */
    public function getPayload(string $type)
    {
        $entry = $this->arrayFlatten($this->request->get('entry'));

        $payload = $entry["payload"];
        if(!key_exists('payload', $entry)) {
            $this->fallbackMessage();
            exit;
        }

        if($payload && $this->isJson($payload)) {
            $payload = \GuzzleHttp\json_decode($payload, true);

            if(
                is_array($payload) &&
                isset($payload["type"]) &&
                $payload["type"] === $type
            ) {
             return $payload;
            } else if(!in_array($payload["type"], self::SUPPORTED_PAYLOAD)) {
                $this->fallbackMessage();
                exit;
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

    public function setSubscriber()
    {
        $botSubscriberId = isset($this->request->get('entry')[0]['messaging'][0]['sender']['id']) ? $this->request->get('entry')[0]['messaging'][0]['sender']['id'] : null;

        /** @var BotSubscriberInterface $botSubscriber */
        $botSubscriber = $this->container->get('sylius_bot_plugin.repository.bot_subscriber')->findOneBy([ 'botSubscriberId' => $botSubscriberId]);

        if(empty($botSubscriber)) {
            $fields = 'name,first_name,last_name,profile_pic,locale,timezone,gender';

            $response = $this->sendFacebookRequest("/{$botSubscriberId}?fields={$fields}&access_token=".getenv('FACEBOOK_PAGE_ACCESS_TOKEN'));
            $subscriberData = \GuzzleHttp\json_decode($response->getBody(), true);

            /** @var CustomerInterface $botCustomer */
            $customer = $this->createBotCustomerAndAssignSubscriber($subscriberData);

            /** @var BotSubscriberInterface $botSubscriber */
            $botSubscriber = $this->createBotSubscriber($subscriberData, $customer);
        }

        $this->user = $botSubscriber;
        $this->setCurrentActiveOrder();
    }

    public function checkout()
    {
        $payload = $this->getPayload("checkout");
        if(!empty($payload)) {
            $this->container->get("sylius.storage.cart_session")->setForChannel($this->defaultChannel, $this->order);

            $checkoutUrl = $this->container->get("router")->generate("ahmedkhd_sylius_bot_checkout", ['cartToken' => $this->order->getTokenValue()]);

            $this->sendMessage(
                ButtonTemplate::create("Are you sure you want to checkout 🛒?")
                    ->addButton(ElementButton::create("Checkout")
                        ->url(getenv("APP_URL") . $checkoutUrl)
                    )
                    ->addButton(ElementButton::create("Continue shopping")
                        ->type('postback')
                        ->payload(\GuzzleHttp\json_encode([
                            "type" => "list_items"
                        ]))
                    )
            );
        }
    }

    /**
     * @param array $subscriberData
     * @param CustomerInterface $customer
     * @return BotSubscriberInterface
     */
    public function createBotSubscriber(array $subscriberData, CustomerInterface $customer)
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
        $botSubscriber->setCustomer($customer);

        $this->container->get('sylius_bot_plugin.repository.bot_subscriber')->add($botSubscriber);

        return $botSubscriber;
    }

    /**
     * @param array $subscriberData
     * @return Customer
     */
    public function createBotCustomerAndAssignSubscriber(array $subscriberData)
    {
        /** @var Customer $customer */
        $customer = $this->container->get("sylius.factory.customer")->createNew();
        $customer->setFirstName($subscriberData["first_name"]);
        $customer->setLastName($subscriberData["last_name"]);
        $customer->setGender($subscriberData["gender"] === "male" ? CustomerInterface::MALE_GENDER : ($subscriberData["gender"] === "female" ? CustomerInterface::FEMALE_GENDER : CustomerInterface::UNKNOWN_GENDER));
        $customer->setEmail("{$subscriberData["id"]}@messenger.com");

        $this->container->get("sylius.repository.customer")->add($customer);

        return $customer;
    }

    private function createCart(
        CustomerInterface $customer = null,
        ChannelInterface $channel = null,
        $localeCode = null
    ) {
        /** @var OrderInterface $order */
        $order = $this->container->get("sylius.factory.order")->createNew();

        $order->setCustomer($customer ?? $this->user->getCustomer());
        $order->setChannel($channel ?? $this->defaultChannel);
        $order->setLocaleCode($localeCode ?? $this->defaultLocaleCode);
        $order->setCurrencyCode($order->getChannel()->getBaseCurrency()->getCode());

        /** @var UniqueIdBasedOrderTokenAssigner */
        $this->container->get('sylius.unique_id_based_order_token_assigner')->assignTokenValue($order);

        $this->container->get("sylius.repository.order")->add($order);
        return $order;
    }

    private function setCurrentActiveOrder()
    {
        $notCompletedOrder = $this->user->getCustomer()->getOrders()->filter(function (OrderInterface $order) {
            return !$order->isCheckoutCompleted();
        });

        if(
            $this->user->getCustomer()->getOrders()->isEmpty() ||
            $notCompletedOrder->isEmpty()
        ) {
            $this->order = $this->createCart($this->user->getCustomer());
        } else if(!$notCompletedOrder->isEmpty()) {
            $this->order = $notCompletedOrder->first();
        }
    }
}
