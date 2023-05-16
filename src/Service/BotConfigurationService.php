<?php

namespace SyliusBotPlugin\Service;

use Psr\Log\LoggerInterface;
use SyliusBotPlugin\Entity\Bot;
use Sylius\Component\Resource\Factory\FactoryInterface;
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
            /** @var RepositoryInterface $botRepository */
            $botRepository = $this->getBotRepository();

            /** @var BotInterface | null $bot */
            $bot = $botRepository->findOneBy([]);
        }

        if ($bot === null) {
            return "";
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
            "get_started_text" => $botConfigPersistentMenu['get_started_text'] ?? $defaultPersistentMenu['get_started_text'],
        ];

        /** @var string $jsonString */
        $jsonString = json_encode($persistentMenuJson);

        $bot->setPersistentMenu($jsonString);

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

    /**
     * @param Bot $bot
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateBotConfiguration(Bot $bot)
    {
        /** @var Array<string, string> $botConfigurations */
        $botConfigurations = json_decode($bot->getPersistentMenu(), true);

        $success = $this->setBotConfigurations($botConfigurations, $bot->getPageAccessToken());

        if($success) {
            $this->logger->debug("Successfully updated getting started button text and payload");
        }
    }
}
