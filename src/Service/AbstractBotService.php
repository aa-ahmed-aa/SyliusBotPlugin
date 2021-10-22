<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriberInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\OrderItemRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\TokenAssigner\UniqueIdBasedOrderTokenAssigner;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractBotService
{
    /** @var ContainerInterface */
    protected $container;

    /** @var OrderInterface */
    protected $order;

    /** @var BotSubscriberInterface */
    protected $user;

    /** @var string */
    protected $defaultLocaleCode;

    /** @var ChannelInterface */
    protected $defaultChannel;

    /** @var string $channelName */
    public $channelName = "messenger";

    /**
     * AbstractBotService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        /** @var RouterInterface $router */
        $router = $this->container->get("router");
        return getenv("APP_URL") .
            $router->generate("ahmedkhd_sylius_bot_checkout", ['cartToken' => $this->order->getTokenValue()]);
    }

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    /**
     * @return BotSubscriberInterface
     */
    public function getUser(): BotSubscriberInterface
    {
        return $this->user;
    }

    /**
     * @return ChannelInterface
     */
    public function getDefaultChannel(): ChannelInterface
    {
        return $this->defaultChannel;
    }

    /**
     * @return string
     */
    public function getDefaultLocaleCode(): string
    {
        return $this->defaultLocaleCode;
    }

    /**
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channelName;
    }

    /**
     * @param ProductInterface $product
     * @return string
     */
    public function getProductImageUrl(ProductInterface $product)
    {
        /** @var CacheManager $imagineCacheManager */
        $imagineCacheManager = $this->container->get('liip_imagine.cache.manager');

        $imageUrl = "https://via.placeholder.com/200x200";

        /** @var ProductImageInterface $image */
        $image = $product->getImagesByType('thumbnail')->first();

        /** @var ProductImageInterface $image */
        $firstImage = $product->getImages()->first();

        if ($image != null) {
            $imageUrl = $imagineCacheManager->getBrowserPath($image->getPath() ?? "", 'sylius_shop_product_thumbnail');
        } else if($firstImage != null) {
            $imageUrl = $imagineCacheManager->getBrowserPath($firstImage->getPath() ?? "", 'sylius_shop_product_thumbnail');
        }

        return $imageUrl;
    }

    /**
     * @param CustomerInterface|null $customer
     * @param ChannelInterface|null $channel
     * @param null $localeCode
     * @return OrderInterface
     */
    protected function createCart(
        CustomerInterface $customer = null,
        ChannelInterface $channel = null,
        $localeCode = null
    ) {
        /** @var FactoryInterface $orderFactory */
        $orderFactory = $this->container->get("sylius.factory.order");

        /** @var OrderInterface $order */
        $order = $orderFactory->createNew();

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        /** @var UniqueIdBasedOrderTokenAssigner */
        $uniqueIdBasedOrderTokenAssigner = $this->container->get('sylius.unique_id_based_order_token_assigner');

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->container->get("sylius.repository.order");

        /** @var CurrencyInterface $baseCurrency */
        $baseCurrency = $this->defaultChannel->getBaseCurrency();

        $order->setCustomer($customer ?? $this->user->getCustomer());
        $order->setChannel($channel ?? $this->getDefaultChannel());
        $order->setLocaleCode($localeCode ?? $this->getDefaultLocaleCode());
        $order->setCurrencyCode($baseCurrency->getCode());

        $uniqueIdBasedOrderTokenAssigner->assignTokenValue($order);

        $orderRepository->add($order);

        return $order;
    }

    /**
     * @param array $subscriberData
     * @return Customer
     */
    public function createBotCustomerAndAssignSubscriber(array $subscriberData)
    {
        /** @var FactoryInterface $customerFactory */
        $customerFactory = $this->container->get("sylius.factory.customer");

        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->container->get("sylius.repository.customer");

        /** @var Customer $customer */
        $customer = $customerFactory->createNew();
        $customer->setFirstName($subscriberData["first_name"]);
        $customer->setLastName($subscriberData["last_name"]);
        $customer->setGender($subscriberData["gender"] === "male" ? CustomerInterface::MALE_GENDER : ($subscriberData["gender"] === "female" ? CustomerInterface::FEMALE_GENDER : CustomerInterface::UNKNOWN_GENDER));
        $customer->setEmail("{$subscriberData["id"]}@messenger.com");

        $customerRepository->add($customer);

        return $customer;
    }

    /**
     * @param array $subscriberData
     * @param CustomerInterface $customer
     * @return BotSubscriberInterface
     */
    public function createBotSubscriber(array $subscriberData, CustomerInterface $customer)
    {
        /** @var FactoryInterface $botSubscriberFactory */
        $botSubscriberFactory = $this->container->get('sylius_bot_plugin.factory.bot_subscriber');

        /** @var RepositoryInterface $botSubscriberRepository */
        $botSubscriberRepository = $this->container->get('sylius_bot_plugin.repository.bot_subscriber');

        /** @var BotSubscriberInterface $botSubscriber */
        $botSubscriber = $botSubscriberFactory->createNew();

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

        $botSubscriberRepository->add($botSubscriber);

        return $botSubscriber;
    }

    /**
     * @param ProductInterface $product
     * @return OrderItemInterface
     */
    public function createOrderItem(ProductInterface $product)
    {
        /** @var FactoryInterface $orderItemFactory */
        $orderItemFactory = $this->container->get("sylius.factory.order_item");

        /** @var OrderItemQuantityModifierInterface $orderItemQuantityModifier */
        $orderItemQuantityModifier = $this->container->get('sylius.order_item_quantity_modifier');

        /** @var OrderItemRepositoryInterface $orderItemRepository */
        $orderItemRepository = $this->container->get("sylius.repository.order_item");

        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->order->getItems()->filter(function(OrderItemInterface $item) use ($product): bool {
            /** @var ProductInterface $product */
            $product = $item->getProduct();
            return $product->getId() === $product->getId();
        })->first();

        if($orderItem === null || $orderItem === false) {
            /** @var OrderItemInterface $orderItem */
            $orderItem = $orderItemFactory->createNew();
        }

        /** @var ProductVariantInterface $variant */
        $variant = $product->getVariants()->first();

        $orderItem->setOrder($this->order);
        $orderItem->setVariant($variant);

        $orderItemQuantityModifier->modify($orderItem, $orderItem->getQuantity() + 1);

        $orderItemRepository->add($orderItem);

        return $orderItem;
    }
}
