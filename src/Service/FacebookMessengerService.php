<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use GuzzleHttp\Exception\GuzzleException;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\OrderItemRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderModifier;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
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
     * @param Request $request
     * @throws GuzzleException
     */
    public function flow(Request $request): void
    {
        $this->setRequest($request);
        $this->setSubscriber();

        $payload = $this->getPayload();
        if($payload === null || $payload === [])
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
    public function fallbackMessage(): void
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
    public function listProducts($payload): void
    {
        if($payload != null && $payload != []) {

            /**
             * @phpstan-ignore-next-line
             * @var Pagerfanta $productsPaginator
             */
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
    public function removeFromCart($payload = []): void
    {
        $item_id = $payload["item_id"];
        /** @var OrderModifierInterface $orderModifier */
        $orderModifier =$this->container->get('sylius.order_modifier');

        /** @var OrderItemRepositoryInterface $orderRepository */
        $orderRepository = $this->container->get('sylius.repository.order');

        /** @var RepositoryInterface $orderItemRepository */
        $orderItemRepository = $this->container->get('sylius.repository.order_item');

        /**
         * @phpstan-ignore-next-line
         * @var OrderItemInterface $orderItem
         */
        $orderItem = $orderItemRepository->findOneById($item_id);

        $orderModifier->removeFromOrder($this->order, $orderItem);

        $orderRepository->add($this->order);

        /** @var ProductInterface $product */
        $product = $orderItem->getProduct();

        $this->sendMessage(["text" => "*{$product->getName()}* removed from your cart"]);
    }

    /**
     * Empty the cart in one click
     * @throws GuzzleException
     */
    public function emptyCart(): void
    {
        if($this->order != null) {
            /** @var OrderRepositoryInterface $orderRepository */
            $orderRepository = $this->container->get('sylius.repository.order');
            $orderRepository->remove($this->order);
        }
        $this->sendMessage(["text" => "I have removed all the items from your cart"]);
    }

    /**
     * Add item to cart
     * @param array $payload
     * @throws GuzzleException
     */
    public function addToCart($payload = []): void
    {
        /** @var OrderRepositoryInterface $productRepository */
        $productRepository = $this->container->get("sylius.repository.product");

        /** @var OrderProcessorInterface $orderProcessor */
        $orderProcessor = $this->container->get("sylius.order_processing.order_processor");

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->container->get("sylius.repository.order");

        /**
         * @phpstan-ignore-next-line
         * @var ProductInterface $product
         */
        $product = $productRepository->findOneById($payload["product_id"]);

        $this->createOrderItem($product);

        $orderProcessor->process($this->order);

        $orderRepository->add($this->order);

        $this->sendMessage(["text" => "*{$product->getName()}* add to your cart"]);
    }

    /**
     * List items in cart
     */
    public function listItemsInCart(): void
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
    public function orderSummery(): void
    {
        if($this->order->getItems()->isEmpty()) {
            $this->sendMessage(['text' => 'Your cart is empty']);
            return;
        }
        $this->sendMessage(
            $this->createReceiptTemplate(
                "Checkout",
                $this->getCheckoutUrl(),
                array_map(function(OrderItemInterface $item): array {
                    /** @var ProductInterface $product */
                    $product = $item->getProduct();

                    /** @var ProductVariantInterface $variant */
                    $variant = $item->getVariant();

                    /** @var ChannelPricingInterface $variantChannelPricing */
                    $variantChannelPricing = $variant->getChannelPricingForChannel($this->defaultChannel);

                    /** @var integer $price */
                    $price = $variantChannelPricing->getPrice();

                    return [
                        'item_id' => $item->getId(),
                        'title' => $item->getProductName(),
                        'description' => $price * 10,
                        'image' => $this->getProductImageUrl($product),
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
    public function checkout(): void
    {
        if($this->order->getItems()->isEmpty()) {
            $this->sendMessage(['text' => 'Your cart is empty']);
            return;
        }

        /** @var RouterInterface $router */
        $router = $this->container->get("router");

        $checkoutUrl = $router->generate("ahmedkhd_sylius_bot_checkout", ['cartToken' => $this->order->getTokenValue()]);

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
