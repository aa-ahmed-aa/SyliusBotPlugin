<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BotService extends AbstractService implements BotServiceInterface
{
    /**
     * BotService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    /**
     * @param iterable $products
     * @param string $localeCode
     * @param ChannelInterface $channel
     * @return array
     */
    public function wrapProducts(iterable $products,string $localeCode, ChannelInterface $channel): array
    {
        $elements = [];
        $imagineCacheManager = $this->container->get('liip_imagine.cache.manager');

        /** @var Product $product */
        foreach ($products as $product) {
            $buttons = [];
            if($product->isSimple()) {
                $buttons[] = ElementButton::create("Add to cart ({$product->getVariants()->first()->getChannelPricingForChannel($channel)->getOriginalPrice()})")
                    ->payload('add_to_cart')
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
//                ->image("{$this->baseUrl}/media/cache/sylius_shop_product_large_thumbnail/{$product->getImages()->first()->getPath()}")
                ->addButtons($buttons);
        }

        return $elements;
    }
}
