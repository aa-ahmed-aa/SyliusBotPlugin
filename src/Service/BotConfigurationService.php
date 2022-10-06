<?php

namespace SyliusBotPlugin\Service;

use Psr\Log\LoggerInterface;
use SyliusBotPlugin\Entity\Bot;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use SyliusBotPlugin\Entity\BotInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class BotConfigurationService extends AbstractFacebookMessengerBotService
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
     * @return mixed
     */
    public function getBotFactory()
    {
        return $this->container->get("sylius_bot_plugin.factory.bot");
    }

    /**
     * @return mixed
     */
    public function getBotRepository()
    {
        return $this->container->get("sylius_bot_plugin.repository.bot");
    }

    /**
     * @param array $data
     * @return string
     */
    public function getPersistentMenuForFacebook(Bot $bot): string
    {
        /** @var array $persistentMenu */
        $persistentMenu = \GuzzleHttp\json_decode($bot->getPersistentMenu());

        return \GuzzleHttp\json_encode([
            [
                "type" => "postback",
                "title" => $persistentMenu["list_products"],
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "list_items",
                    "page" => 1
                ])
            ],
            [
                "type" => "postback",
                "title" => $persistentMenu["order_summery"],
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "order_summery"
                ])
            ],
            [
                "type" => "postback",
                "title" => $persistentMenu["my_cart"],
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "mycart"
                ])
            ],
            [
                "type" => "postback",
                "title" => $persistentMenu["empty_cart"],
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "empty_cart"
                ])
            ],
            [
                "type" => "postback",
                "title" => $persistentMenu["checkout"],
                "payload" => \GuzzleHttp\json_encode([
                    "type" => "checkout"
                ])
            ]
        ]);
    }

    /**
     * @param int|null $botId
     * @return Bot
     */
    private function getBotConfiguration(int $botId = null)
    {
        /** @var FactoryInterface $botFactory */
        $botFactory = $this->getBotFactory();

        /** @var RepositoryInterface $botRepository */
        $botRepository = $this->getBotRepository();

        return $botId === null
            ? $botFactory->createNew()
            : $botRepository->findOneBy(['id' => $botId]);
    }

    /**
     * @param Bot | null $bot
     * @return string
     */
    public function getPersistentMenuWithDefaults(Bot $bot = null): string
    {
        if ($bot === null) {
            /** @var BotInterface $bot */
            $bot = $this->getBotRepository()->findOneBy([]);
            if ($bot === null) {
                return '';
            }
        }

        $persistentMenuJson = $bot->getPersistentMenu();

        /** @var array $botConfigPersistentMenu */
        $botConfigPersistentMenu = json_decode($persistentMenuJson, true);

        /** @var array $defaultPersistentMenu */
        $defaultPersistentMenu = Bot::PERSISTENT_MENU_FALLBACK;

        $persistentMenuJson = [
            "list_products" => $botConfigPersistentMenu['list_products'] ?? $defaultPersistentMenu['list_products'],
            "order_summery" => $botConfigPersistentMenu['order_summery'] ?? $defaultPersistentMenu['order_summery'],
            "my_cart" => $botConfigPersistentMenu['my_cart'] ?? $defaultPersistentMenu['my_cart'],
            "empty_cart" => $botConfigPersistentMenu['empty_cart'] ?? $defaultPersistentMenu['empty_cart'],
            "checkout" => $botConfigPersistentMenu['checkout'] ?? $defaultPersistentMenu['checkout'],
            "bot_id" => $bot->getId(),
            "facebook_page" => $bot->getPageId(),
        ];

        $bot->setPersistentMenu(\GuzzleHttp\json_encode($persistentMenuJson));

        return $bot->getPersistentMenu();
    }

    /**
     * @param Bot $bot
     * @return Bot
     */
    public function add(Bot $bot): Bot
    {
        $this->getBotRepository()->add($bot);
        return $bot;
    }

}