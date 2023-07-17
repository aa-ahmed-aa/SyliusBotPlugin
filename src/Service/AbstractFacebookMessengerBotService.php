<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Service;

use SyliusBotPlugin\Entity\Bot;
use SyliusBotPlugin\Entity\BotSubscriberInterface;
use SyliusBotPlugin\Traits\FacebookMessengerTrait;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ReceiptTemplate;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractFacebookMessengerBotService extends AbstractBotService implements BotServiceInterface
{
    use FacebookMessengerTrait;

    /** @var LoggerInterface */
    protected $logger;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var Request|null */
    private $request = null;

    /** @var string */
    protected $baseUrl;

    /** @var string|null */
    protected $pageAccessToken;

    /** @var Client */
    protected $httpClient;

    /**
     * AbstractFacebookMessengerBotService constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @throws Exception
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, SerializerInterface $serializer)
    {
        parent::__construct($container);

        /** @var LocaleContextInterface $localContext */
        $localContext = $this->container->get('sylius.context.locale');

        /** @var ChannelContextInterface $channelContext */
        $channelContext = $this->container->get('sylius.context.channel');

        /**
         * @psalm-suppress PossiblyFalsePropertyAssignmentValue
         */
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->baseUrl = 'https://'.$_SERVER['HTTP_HOST'];
        $this->defaultLocaleCode = $localContext->getLocaleCode();
        /**
         * @psalm-suppress PropertyTypeCoercion
         * @phpstan-ignore-next-line
         */
        $this->defaultChannel = $channelContext->getChannel();
        $this->httpClient = new Client(['base_uri' => $this->getEnvironment('FACEBOOK_GRAPH_URL')]);
        $this->pageAccessToken = NULL;
    }

    /**
     * @return Request|null
     */
    public function getRequest()
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
     * @return array
     */
    public function getPayload()
    {
        /** @var Request|null $request */
        $request = $this->getRequest();
        if($request === null) {
            return [];
        }

        /** @var array $entryString */
        $entryString = $request->get('entry');

        $entry = $this->arrayFlatten($entryString);

        if(!key_exists('payload', $entry)) {
            return [];
        }
        /** @var string $payload */
        $payload = $entry["payload"];

        if($payload != [] && $this->isJson($payload)) {
            /** @var array $payloadObject */
            $payloadObject = \GuzzleHttp\json_decode($payload, true);

            if(isset($payloadObject["type"])) {
                return $payloadObject;
            } else {
                throw new Exception("Please set the payload type");
            }
        }
        return [];
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

            /** @var ProductVariantInterface $productVariant */
            $productVariant = $product->getVariants()->first();

            /** @var ChannelPricingInterface $channelPricing */
            $channelPricing = $productVariant->getChannelPricingForChannel($channel);

            /** @var CurrencyInterface $baseCurrency */
            $baseCurrency = $channel->getBaseCurrency();

            if($product->isSimple()) {
                $buttons[] = $this->createButton(
                    "Add to cart (" . (integer)$channelPricing->getPrice() / 100 . " {$baseCurrency->getCode()})",
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
                "Price: " . (int)$channelPricing->getPrice() / 100 . " " . ($baseCurrency->getCode() ?? "") ."\n{$product->getShortDescription()}",
                $this->getProductImageUrl($product),
                $buttons
            );
        }

        /**
         * @psalm-suppress InvalidArgument
         * @phpstan-ignore-next-line
         */
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
            /** @var ProductInterface $product */
            $product = $item->getProduct();

            /** @var ProductVariantInterface $variant */
            $variant = $item->getVariant();

            /** @var ChannelPricingInterface $channelPricing */
            $channelPricing = $variant->getChannelPricingForChannel($this->defaultChannel);

            /** @var CurrencyInterface $baseCurrency */
            $baseCurrency = $this->getDefaultChannel()->getBaseCurrency();

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
                    "{$this->baseUrl}/{$this->defaultLocaleCode}/products/{$product->getSlug()}"
                )
            ];

            $elements[] = $this->createCaroselCard(
                $item->getProductName(),
                "Price: " . (int)$channelPricing->getPrice() / 100 . " " . ($baseCurrency->getCode() ?? "") ."\nQty: {$item->getQuantity()}",
                $this->getProductImageUrl($product),
                $buttons
            );
        }

        return $elements;
    }

    /**
     * @param array $persistentMenuItems
     * @return array
     */
    public function getPersistentMenuItems($persistentMenuItems = []): array
    {
        $menuItems = [];

        /**
         * @var string $payload
         * @var string $title
         */
        foreach ($persistentMenuItems as $payload => $title)
        {
            $menuItems[] = [
                "type" => "postback",
                "title" => $title,
                "payload" => json_encode(["type" => $payload])
            ];
        }
        $envs = array_merge($_ENV, getenv());

        $menuItems[] = [
            "type" => "web_url",
            "title" => "Go to website",
            "url" => $this->baseUrl,
            "webview_height_ratio" => "full",
        ];

        return $menuItems;
    }

    /**
     * @param array $menuItems
     * @param string $accessToken
     * @return bool
     * @throws GuzzleException
     */
    public function setBotConfigurations(array $menuItems, string $accessToken): bool
    {
        /** @var Array<string, string> $cleanPersistentMenuItems */
        $cleanPersistentMenuItems = $menuItems;

        unset($cleanPersistentMenuItems["bot_id"]);
        unset($cleanPersistentMenuItems["facebook_page"]);
        unset($cleanPersistentMenuItems["get_started_text"]);

        $persistentMenuItems = $this->getPersistentMenuItems($cleanPersistentMenuItems);

        $body = [
            "get_started"  =>  [
                "payload" => json_encode([
                    "type" => "get_started"
                ])
            ],
            "greeting" => [
                [
                    "locale" => "default",
                    "text" => $menuItems['get_started_text']
                ]
            ],
            "persistent_menu" => [
                [
                    "locale" => "default",
                    "composer_input_disabled" => false,
                    "call_to_actions" => $persistentMenuItems
                ]
            ]
        ];
        $envs = array_merge($_ENV, getenv());

        $response = $this->sendFacebookRequest(
            "/" . $envs["FACEBOOK_GRAPH_VERSION"] . "/me/messenger_profile?access_token=" . $accessToken,
            $body,
            Request::METHOD_POST
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * @param array|OutgoingMessage|ButtonTemplate|ReceiptTemplate|string $message
     * @return ResponseInterface|null
     * @throws GuzzleException
     */
    public function sendMessage($message)
    {
        /** @var Request|null $request */
        $request = $this->getRequest();
        if($request === null) {
            return null;
        }

        /**
         * @psalm-suppress MixedArrayAccess
         * @var array $body
         */
        $body = [
            "messaging_type" => "RESPONSE",
            "recipient" => [
                "id" => $request->get('entry')[0]['messaging'][0]["sender"]["id"]
            ],
            "message" => $message
        ];

        return $this->sendFacebookRequest(
            "/" . $this->getEnvironment('FACEBOOK_GRAPH_VERSION') . "/me/messages?access_token=" . $this->pageAccessToken,
            $body,
            Request::METHOD_POST
        );
    }

    /**
     * @param string $url
     * @param array $body
     * @param string $method
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function sendFacebookRequest(string $url, array $body = [], string $method = Request::METHOD_GET): ResponseInterface
    {
        try {
            return $this->httpClient->request(
                $method,
                $url,
                $this->getRequestOption($body)
            );
        } catch (GuzzleException $e) {
            $this->logger->critical($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array $body
     * @return array
     */
    public function getRequestOption(array $body)
    {
        $options = [
            RequestOptions::HEADERS => [
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ]
        ];

        $options[RequestOptions::JSON] = $body;

        return $options;
    }

    /**
     * @throws GuzzleException
     */
    public function setSubscriber(): void
    {
        /** @var Request|null $request */
        $request = $this->getRequest();
        if($request === null) {
            return;
        }

        /** @var array $entry */
        $entry = $request->get('entry');

        /**
         * @var string
         * @psalm-suppress MixedArrayAccess
         */
        $botSubscriberId =
            $entry[0]['messaging'][0]['sender']['id'] ?? null
        ;

        /** @var RepositoryInterface $botSubscriberRepository */
        $botSubscriberRepository = $this->container->get('sylius_bot_plugin.repository.bot_subscriber');

        /** @var BotSubscriberInterface|null $botSubscriber */
        $botSubscriber = $botSubscriberRepository->findOneBy([ 'botSubscriberId' => $botSubscriberId]);

        if($botSubscriber === null) {
            $fields = 'name,first_name,last_name,profile_pic,locale,timezone,gender';

            $response = $this->sendFacebookRequest("/{$botSubscriberId}?fields={$fields}&access_token=".$this->pageAccessToken);
            /** @var array<array-key, string> $subscriberData */
            $subscriberData = \GuzzleHttp\json_decode((string)$response->getBody(), true);

            /** @var CustomerInterface $customer */
            $customer = $this->createBotCustomerAndAssignSubscriber($subscriberData);

            /** @var BotSubscriberInterface $botSubscriber */
            $botSubscriber = $this->createBotSubscriber($subscriberData, $customer);
        }

        $this->setCurrentActiveOrder($botSubscriber);
        $this->botSubscriber = $botSubscriber;
    }

    /**
     * Set Current Active Bot Order
     */
    public function setCurrentActiveOrder(?BotSubscriberInterface $botSubscriber): void
    {
        $this->order = $this->createCart($botSubscriber->getCustomer());
    }

    /**
     * @return false|void
     * @throws GuzzleException
     */
    public function setAccessTokenAndPresistentMenu()
    {
        /** @var Request|null $request */
        $request = $this->getRequest();

        if($request === null)
        {
            throw new Exception("Empty Request");
        }

        /** @var string $pageId */
        $pageId = $request->get('entry')[0]["id"] ?? "";

        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->container->get('sylius_bot_plugin.repository.bot');

        /** @var Bot|null $page */
        $page = $botRepository->findOneBy(["page_id" => $pageId]);

        if($page === null || $page->getDisabled()) {
            $this->sendMessage("This Page is disabled or not connected please contact you bot provider");
            return false;
        }

        // set token
        $this->pageAccessToken = $page->getPageAccessToken();
    }
}
