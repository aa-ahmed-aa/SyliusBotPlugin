<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
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

    /**
     * BotService constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, SerializerInterface $serializer)
    {
        parent::__construct($container);
        $this->logger = $logger;
        $this->serializer = $serializer;
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
        $imagineCacheManager = $this->container->get('liip_imagine.cache.manager');

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

            $imageUrl = "https://via.placeholder.com/200x200";

             if (!empty($product->getImagesByType('thumbnail')->first())) {
                 $imageUrl = $imagineCacheManager->getBrowserPath($product->getImagesByType('thumbnail')->first()->getPath(), 'sylius_shop_product_thumbnail');
             } else if($product->getImages()->first()) {
                 $imageUrl = $imagineCacheManager->getBrowserPath($product->getImages()->first()->getPath(), 'sylius_shop_product_thumbnail');
             }

            $elements[] = Element::create($product->getName())
                ->subtitle($product->getShortDescription())
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
}
