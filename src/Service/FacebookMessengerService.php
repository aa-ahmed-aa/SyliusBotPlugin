<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use GuzzleHttp\Exception\GuzzleException;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Pagerfanta\Pagerfanta;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

/**
 * Class FacebookMessengerService
 * @package Ahmedkhd\SyliusBotPlugin\Service
 */
class FacebookMessengerService extends AbstractFacebookMessengerBotService
{
    /** @var Client */
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
    }

    /**
     * @param null $request
     * @throws GuzzleException
     */
    public function flow($request = null): void
    {
        $this->setRequest($request);
        $this->setSubscriber();

        $payload = $this->getPayload();
        if(empty($payload))
        {
            $this->fallbackMessage();
            exit;
        }
        switch ($payload['type'])
        {
            case "list_items":
                $this->listProducts($payload);
                break;
            case "order_summery":
                $this->orderSummery();
                break;
            case "checkout":
                $this->checkout();
                break;
            case "add_to_cart":
                $this->addToCart($payload);
                break;
            case "remove_item_from_cart":
                $this->removeFromCart($payload);
                break;
            case "empty_cart":
                $this->emptyCart();
                break;
            case "mycart":
                $this->listItemsInCart();
                break;
            default:
                $this->fallbackMessage();
                break;
        }
    }

    /**
     * Send Fallback message
     * @throws GuzzleException
     */
    public function fallbackMessage()
    {
        $this->sendMessage($this->createButtonTemplate("Sorry i can't understand you💅", [
            $this->createButton("List Products", "postback", \GuzzleHttp\json_encode([
                "type" => "list_items",
                "page" => 1
            ])),
            $this->createButton("Go to the website", "url", "", $this->baseUrl)
        ]));
    }

    /**
     * List products
     * @param array $payload
     * @throws GuzzleException
     */
    public function listProducts($payload = [])
    {
        if(!empty($payload)) {
            /** @var Pagerfanta $productsPaginator */
            $productsPaginator = $this->container->get('sylius.repository.product')->createPaginator();
            $productsPaginator->setCurrentPage($payload['page'] ?? 1);
            $productsPaginator->setMaxPerPage(9);

            $elements = $this->wrapProductsForListing($productsPaginator->getCurrentPageResults(), $this->defaultLocaleCode, $this->defaultChannel, $payload['page'] ?? 1);

            $this->sendMessage($this->createCarosel(GenericTemplate::RATIO_SQUARE, $elements));
        }
    }

    /**
     * Remove item from cart
     * @param array $payload
     * @throws GuzzleException
     */
    public function removeFromCart($payload = [])
    {
        $item_id = $payload["item_id"];
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->container->get('sylius.repository.order_item')->findOneById($item_id);

        $this->container->get('sylius.order_modifier')->removeFromOrder($this->order, $orderItem);

        $this->container->get('sylius.repository.order')->add($this->order);

        $this->sendMessage(["text" => "*{$orderItem->getProduct()->getName()}* removed from your cart"]);
    }

    /**
     * Empty the cart in one click
     */
    public function emptyCart()
    {
        if(!empty($this->order)) {
            $this->container->get('sylius.repository.order')->remove($this->order);
        }
        $this->sendMessage(["text" => "I have removed all the items from your cart"]);
    }

    /**
     * Add item to cart
     * @param array $payload
     * @throws GuzzleException
     */
    public function addToCart($payload = [])
    {
        /** @var ProductInterface $product */
        $product = $this->container->get("sylius.repository.product")->findOneById($payload["product_id"]);

        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->createOrderItem($product);

        $this->container->get("sylius.order_processing.order_processor")->process($this->order);

        $this->container->get("sylius.repository.order")->add($this->order);

        $this->sendMessage(["text" => "*{$product->getName()}* add to your cart"]);
    }

    /**
     * List items in cart
     */
    public function listItemsInCart()
    {
        if($this->order->getItems()->isEmpty()) {
            $this->sendMessage(['text' => 'Your cart is empty']);
            return;
        }
        $this->sendMessage(
            $this->createCarosel(GenericTemplate::RATIO_HORIZONTAL, $this->wrapProductsForCart($this->order->getItems()))
        );
    }

    /**
     * List items in cart
     */
    public function orderSummery()
    {
        if($this->order->getItems()->isEmpty()) {
            $this->sendMessage(['text' => 'Your cart is empty']);
            return;
        }
        $this->sendMessage(
            $this->createReceiptTemplate(
                "Checkout",
                $this->getCheckoutUrl(),
                array_map(function(OrderItemInterface $item) {
                    return [
                        'item_id' => $item->getId(),
                        'title' => $item->getProductName(),
                        'description' => $item->getVariant()->getChannelPricingForChannel($this->defaultChannel)->getPrice() * 10,
                        'image' => $this->getProductImageUrl($item->getProduct()),
                        'quantity' => $item->getQuantity()
                    ];
                }, $this->order->getItems()->toArray())
            )
        );
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
     * Checkout in messenger
     */
    public function checkout()
    {
        if($this->order->getItems()->isEmpty()) {
            $this->sendMessage(['text' => 'Your cart is empty']);
            return;
        }

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
