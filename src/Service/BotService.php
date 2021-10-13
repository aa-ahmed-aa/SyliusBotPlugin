<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriber;
use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\ReceiptAdjustment;
use BotMan\Drivers\Facebook\Extensions\ReceiptElement;
use BotMan\Drivers\Facebook\Extensions\ReceiptSummary;
use BotMan\Drivers\Facebook\Extensions\ReceiptTemplate;
use GuzzleHttp\Client;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BotService extends AbstractService implements BotServiceInterface
{
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

    const SUPPORTED_PAYLOAD = [
        "checkout",
        "empty_cart",
        "mycart",
        "list_items",
        "remove_item_from_cart",
        "add_to_cart"
    ];

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
     * @param iterable $products
     * @param string $localeCode
     * @param ChannelInterface $channel
     * @param int $pageNo
     * @return array
     */
    public function wrapProducts(iterable $products,string $localeCode, ChannelInterface $channel, $pageNo = 1): array
    {
        $elements = [];

        /** @var Product $product */
        foreach ($products as $product) {
            $buttons = [];
            if($product->isSimple()) {
                $buttons[] = ElementButton::create("Add to cart (" . $product->getVariants()->first()->getChannelPricingForChannel($channel)->getPrice() / 100 . " {$channel->getBaseCurrency()->getCode()})")
                    ->payload(\GuzzleHttp\json_encode([
                        "type" => "add_to_cart",
                        "product_id" => $product->getId()
                    ]))
                    ->type('postback');
            }

            $buttons[] = ElementButton::create('View On Website')
                ->url("{$this->baseUrl}/{$localeCode}/products/{$product->getSlug()}");

            $imageUrl = $this->getProductImageUrl($product);

            $elements[] = Element::create($product->getName())
                ->subtitle("Price: " . $product->getVariants()->first()->getChannelPricingForChannel($channel)->getPrice() / 100 . " " . $this->getDefaultChannel()->getBaseCurrency()->getCode() ."\n{$product->getShortDescription()}" )
                ->image($imageUrl)
                ->addButtons($buttons);
        }

        if(!(count($products) < 9)) {
            $elements[] = Element::create('See More')
                ->image("http://www.first-cards.com/photo/see%20more.png")
                ->addButton(ElementButton::create('See More')
                    ->type('postback')
                    ->payload(\GuzzleHttp\json_encode([
                        "type" => "list_items",
                        "page" => $pageNo + 1
                    ])));
        }

        return $elements;
    }

    public function getReceiptTemplate(string $actionButtonText, string $actionButtonUrl, array $elements)
    {
        $items = $this->getReceiptElements($elements);
        return ReceiptTemplate::create()
            ->recipientName($actionButtonText)
            ->merchantName($this->getDefaultChannel()->getName())
            ->orderNumber($this->getOrder()->getId())
            ->timestamp($this->getOrder()->getCreatedAt()->getTimestamp())
            ->orderUrl($actionButtonUrl)
            ->paymentMethod('Visa | Bank Transfer | Cash on delivery')
            ->currency($this->getDefaultChannel()->getBaseCurrency()->getCode())
            ->addElements($items)
            ->addSummary(ReceiptSummary::create()
                ->subtotal($this->getOrder()->getItemsTotal() * 10)
                ->shippingCost($this->getOrder()->getShippingTotal() *10)
                ->totalTax($this->getOrder()->getTaxTotal() * 10)
                ->totalCost($this->getOrder()->getTotal() * 10)
            )
            ->addAdjustment(ReceiptAdjustment::create('Adjustment')
                ->amount($this->getOrder()->getAdjustmentsTotal())
            );
    }

    public function getReceiptElements(array $elements)
    {
        $items = [];
        foreach ($elements as $element)
        {
            $items[] = ReceiptElement::create($element['title'])
                ->price((double)$element['description'])
                ->quantity($element['quantity'])
                ->currency($this->getDefaultChannel()->getBaseCurrency()->getCode())
                ->image($element['image']);
        }

        return $items;
    }

    public function getProductImageUrl(ProductInterface $product)
    {
        $imagineCacheManager = $this->container->get('liip_imagine.cache.manager');

        $imageUrl = "https://via.placeholder.com/200x200";

        if (!empty($product->getImagesByType('thumbnail')->first())) {
            $imageUrl = $imagineCacheManager->getBrowserPath($product->getImagesByType('thumbnail')->first()->getPath(), 'sylius_shop_product_thumbnail');
        } else if($product->getImages()->first()) {
            $imageUrl = $imagineCacheManager->getBrowserPath($product->getImages()->first()->getPath(), 'sylius_shop_product_thumbnail');
        }

        return $imageUrl;
    }

    public function getCheckoutUrl()
    {
        return getenv("APP_URL") .
            $this->container->get("router")->generate("ahmedkhd_sylius_bot_checkout", ['cartToken' => $this->order->getTokenValue()]);
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
}
