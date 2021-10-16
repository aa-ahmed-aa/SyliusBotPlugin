<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriberInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\TokenAssigner\UniqueIdBasedOrderTokenAssigner;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractBotService
{
    /** @var ContainerInterface */
    protected $container;

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
        return getenv("APP_URL") .
            $this->container->get("router")->generate("ahmedkhd_sylius_bot_checkout", ['cartToken' => $this->order->getTokenValue()]);
    }

    /**
     * @param ProductInterface $product
     * @return string
     */
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
     * @param ProductInterface $product
     * @return OrderItemInterface
     */
    public function createOrderItem(ProductInterface $product)
    {
        $orderItem = $this->order->getItems()->filter(function(OrderItemInterface $item) use ($product){
            return $item->getProduct()->getId() === $product->getId();
        })->first();


        if(empty($orderItem)) {
            /** @var OrderItemInterface $orderItem */
            $orderItem = $this->container->get("sylius.factory.order_item")->createNew();
        }

        $orderItem->setOrder($this->order);
        $orderItem->setVariant($product->getVariants()->first());

        $this->container->get('sylius.order_item_quantity_modifier')->modify($orderItem, $orderItem->getQuantity() + 1);

        $this->container->get("sylius.repository.order_item")->add($orderItem);

        return $orderItem;
    }
}
