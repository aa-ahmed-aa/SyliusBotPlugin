<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriber;
use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriberInterface;
use Ahmedkhd\SyliusBotPlugin\Traits\FacebookMessengerTrait;
use Ahmedkhd\SyliusBotPlugin\Traits\HelperTrait;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractFacebookMessengerBotService extends AbstractBotService implements BotServiceInterface
{
    use HelperTrait, FacebookMessengerTrait;

    /** @var LoggerInterface */
    protected $logger;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var BotSubscriber */
    protected $user;

    /** @var OrderInterface */
    protected $order;

    /** @var Request */
    private $request;

    /** @var string */
    protected $baseUrl;

    /** @var string */
    protected $defaultLocaleCode;

    /** @var ChannelInterface */
    protected $defaultChannel;

    public $channelName = "messenger";

    /**
     * BotService constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, SerializerInterface $serializer)
    {
        parent::__construct($container);
        /**
         * @psalm-suppress PossiblyFalsePropertyAssignmentValue
         */
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->baseUrl = getenv('APP_URL') === false ? "https://www.google.com" : getenv('APP_URL');
        $this->defaultLocaleCode = $this->container->get('sylius.context.locale')->getLocaleCode();
        $this->defaultChannel = $this->container->get('sylius.context.channel')->getChannel();
        $this->httpClient = new Client(['base_uri' => getenv('FACEBOOK_GRAPH_URL')]);
    }

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    /**
     * @return ChannelInterface
     */
    public function getDefaultChannel(): ChannelInterface
    {
        return $this->defaultChannel;
    }

    /**
     * @return BotSubscriber
     */
    public function getUser(): BotSubscriber
    {
        return $this->user;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return bool|mixed
     */
    public function getPayload()
    {
        $entry = $this->arrayFlatten($this->getRequest()->get('entry'));

        if(!key_exists('payload', $entry)) {
            return false;
        }
        $payload = $entry["payload"];

        if($payload && $this->isJson($payload)) {
            $payload = \GuzzleHttp\json_decode($payload, true);

            if(
                is_array($payload) &&
                isset($payload["type"])
            ) {
                return $payload;
            }
        }
        return false;
    }

    /**
     * @param iterable $products
     * @param string $localeCode
     * @param ChannelInterface $channel
     * @param int $pageNo
     * @return array
     */
    public function wrapProductsForListing(iterable $products, string $localeCode, ChannelInterface $channel, $pageNo = 1): array
    {
        $elements = [];

        /** @var Product $product */
        foreach ($products as $product) {
            $buttons = [];
            if($product->isSimple()) {
                $buttons[] = $this->createButton(
                    "Add to cart (" . $product->getVariants()->first()->getChannelPricingForChannel($channel)->getPrice() / 100 . " {$channel->getBaseCurrency()->getCode()})",
                    "postback",
                    \GuzzleHttp\json_encode([
                        "type" => "add_to_cart",
                        "product_id" => $product->getId()
                    ])
                );
            }

            $buttons[] = $this->createButton(
                "View On Website",
                "url",
                "",
                "{$this->baseUrl}/{$localeCode}/products/{$product->getSlug()}"
            );

            $elements[] = $this->createCaroselCard(
                $product->getName(),
                "Price: " . $product->getVariants()->first()->getChannelPricingForChannel($channel)->getPrice() / 100 . " " . $this->getDefaultChannel()->getBaseCurrency()->getCode() ."\n{$product->getShortDescription()}",
                $this->getProductImageUrl($product),
                $buttons
            );
        }

        if(!(count($products) < 9)) {
            $elements[] = $this->createCaroselCard(
                "See More",
                "",
                "http://www.first-cards.com/photo/see%20more.png",
                [
                    $this->createButton("See More", "postback", \GuzzleHttp\json_encode([
                        "type" => "list_items",
                        "page" => $pageNo + 1
                    ]))
                ]
            );
        }

        return $elements;
    }

    /**
     * @param iterable $orderItems
     * @param int $pageNo
     * @return array
     */
    public function wrapProductsForCart(iterable $orderItems, $pageNo = 1): array
    {
        $elements = [];

        /** @var OrderItemInterface $item */
        foreach ($orderItems as $item) {
            $buttons = [
                $this->createButton(
                    "Remove from cart",
                    "postback",
                    \GuzzleHttp\json_encode([
                        "type" => "remove_item_from_cart",
                        "item_id" => $item->getId()
                    ])
                ),
                $this->createButton(
                    "View On Website",
                    "url",
                    "",
                    "{$this->baseUrl}/{$this->defaultLocaleCode}/products/{$item->getProduct()->getSlug()}"
                )
            ];

            $elements[] = $this->createCaroselCard(
                $item->getProductName(),
                "Price: " . $item->getVariant()->getChannelPricingForChannel($this->defaultChannel)->getPrice() / 100 . " " . $this->getDefaultChannel()->getBaseCurrency()->getCode() ."\nQty: {$item->getQuantity()}",
                $this->getProductImageUrl($item->getProduct()),
                $buttons
            );
        }

        return $elements;
    }

    /**
     * @param array $menuItems
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updatePresistentMenu($menuItems = []): Response
    {
        $body = [
            "setting_type"  =>  "call_to_actions",
            "thread_state"  =>  "existing_thread",
            "call_to_actions" => $menuItems
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendMessage($message)
    {
        $body = [
            "messaging_type" => "RESPONSE",
            "recipient" => [
                "id" => $this->getRequest()->get('entry')[0]['messaging'][0]["sender"]["id"]
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setSubscriber()
    {
        $botSubscriberId = isset($this->getRequest()->get('entry')[0]['messaging'][0]['sender']['id']) ? $this->getRequest()->get('entry')[0]['messaging'][0]['sender']['id'] : null;

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

    /**
     * Set Current Active Bot Order
     */
    public function setCurrentActiveOrder()
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
