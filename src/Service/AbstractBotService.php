<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Service;


use SyliusBotPlugin\Entity\BotSubscriber;
use SyliusBotPlugin\Entity\BotSubscriberInterface;
use SyliusBotPlugin\Traits\HelperTrait;
use Exception;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Order\Model\Order;
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

abstract class AbstractBotService extends AbstractService
{
    use HelperTrait;

    /** @var OrderInterface */
    protected $order;

    /** @var BotSubscriberInterface */
    protected $botSubscriber;

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
        parent::__construct($container);
        $this->defaultLocaleCode = "en_US";
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getCheckoutUrl()
    {
        /** @var RouterInterface $router */
        $router = $this->container->get("router");
        return $this->getEnvironment("APP_URL") .
            $router->generate("sylius_bot_plugin_sylius_bot_checkout", ['cartToken' => $this->order->getTokenValue()]);
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
        return $this->botSubscriber;
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

        /** @var ProductImageInterface|false $image */
        $image = $product->getImagesByType('thumbnail')->first();

        /** @var ProductImageInterface|false $image */
        $firstImage = $product->getImages()->first();

        if ($image !== false) {
            $imageUrl = $imagineCacheManager->getBrowserPath($image->getPath() ?? "", 'sylius_shop_product_thumbnail');
        } else if($firstImage !== false) {
            $imageUrl = $imagineCacheManager->getBrowserPath($firstImage->getPath() ?? "", 'sylius_shop_product_thumbnail');
        }

        return $imageUrl;
    }

    /**
     * @param CustomerInterface|null $customer
     * @return OrderInterface
     */
    protected function createCart(CustomerInterface $customer = null) {
        /** @var FactoryInterface $orderFactory */
        $orderFactory = $this->container->get("sylius.factory.order");

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->container->get("sylius.repository.order");
        $order = $orderRepository->findOneBy(['customer' => $customer->getId(), 'state' => 'cart']);

        if(!empty($order)) {
            return $order;
        }

        $orderFactory = $this->container->get("sylius.factory.order");

        /** @var OrderInterface $order */
        $order = $orderFactory->createNew();

        /** @var UniqueIdBasedOrderTokenAssigner */
        $uniqueIdBasedOrderTokenAssigner = $this->container->get('sylius.unique_id_based_order_token_assigner');

        /** @var CurrencyInterface $baseCurrency */
        $baseCurrency = $this->defaultChannel->getBaseCurrency();

        $order->setCustomer($customer ?? $this->botSubscriber->getCustomer());
        $order->setChannel($this->getDefaultChannel());
        $order->setLocaleCode($this->getDefaultLocaleCode());
        $order->setCurrencyCode($baseCurrency->getCode());

        $uniqueIdBasedOrderTokenAssigner->assignTokenValue($order);

        $orderRepository->add($order);

        return $order;
    }

    /**
     * @param array<array-key, string> $subscriberData
     * @return CustomerInterface
     */
    public function createBotCustomerAndAssignSubscriber(array $subscriberData): CustomerInterface
    {
        /** @var FactoryInterface $customerFactory */
        $customerFactory = $this->container->get("sylius.factory.customer");

        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->container->get("sylius.repository.customer");

        /** @var CustomerInterface|null $customer */
        $customer = $customerRepository->findOneBy(["email" => "{$subscriberData["id"]}@messenger.com"]);

        if($customer === null) {
            /** @var CustomerInterface $customer */
            $customer = $customerFactory->createNew();
        }

        $customer->setFirstName($subscriberData["first_name"]);
        $customer->setLastName($subscriberData["last_name"]);
        $customer->setGender($subscriberData["gender"] === "male" ? CustomerInterface::MALE_GENDER : ($subscriberData["gender"] === "female" ? CustomerInterface::FEMALE_GENDER : CustomerInterface::UNKNOWN_GENDER));
        $customer->setEmail("{$subscriberData["id"]}@messenger.com");

        $customerRepository->add($customer);

        return $customer;
    }

    /**
     * @param array<array-key, string> $subscriberData
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

        /** @var OrderItemInterface|false $orderItem */
        $orderItem = $this->order->getItems()->filter(function(OrderItemInterface $item) use ($product): bool {
            /** @var ProductVariantInterface $itemVariant */
            $itemVariant = $item->getVariant();

            /** @var ProductVariantInterface $productVariant */
            $productVariant = $product->getVariants()->first();

            return $itemVariant->getId() === $productVariant->getId();
        })->first();

        if($orderItem === false) {
            /** @var OrderItemInterface $orderItem */
            $orderItem = $orderItemFactory->createNew();
        }

        /** @var ProductVariantInterface $variant */
        $variant = $product->getVariants()->first();

        $orderItem->setOrder($this->order);
        $orderItem->setVariant($variant);

        $orderItemQuantityModifier->modify($orderItem, $orderItem->getQuantity() + 1);
        $this->container->get('sylius.order_processing.order_processor')->process($this->order);


        $orderItemRepository->add($orderItem);

        return $orderItem;
    }
}
