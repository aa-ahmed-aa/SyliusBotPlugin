<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Bot;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Entity\BotSubscriberInterface;
use SyliusBotPlugin\Graph\GraphApiException;
use SyliusBotPlugin\Graph\GraphClientInterface;
use SyliusBotPlugin\Repository\BotRepositoryInterface;

/**
 * Resolves the page an event is for and the person who sent it, creating the
 * subscriber, its Sylius customer and a cart the first time they write.
 */
final class ConversationFactory
{
    /**
     * @param RepositoryInterface<BotSubscriberInterface> $subscriberRepository
     * @param FactoryInterface<BotSubscriberInterface> $subscriberFactory
     * @param FactoryInterface<CustomerInterface> $customerFactory
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     * @param FactoryInterface<OrderInterface> $orderFactory
     */
    public function __construct(
        private readonly BotRepositoryInterface $botRepository,
        private readonly RepositoryInterface $subscriberRepository,
        private readonly FactoryInterface $subscriberFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly FactoryInterface $customerFactory,
        private readonly BotChannelResolver $channelResolver,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FactoryInterface $orderFactory,
        private readonly OrderTokenAssignerInterface $orderTokenAssigner,
        private readonly GraphClientInterface $graphClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function findBot(IncomingMessage $message): ?BotInterface
    {
        $bot = $this->botRepository->findOneByChannelAndPageId($message->channel, $message->accountId);

        return $bot !== null && !$bot->isDisabled() ? $bot : null;
    }

    public function create(BotInterface $bot, IncomingMessage $message): Conversation
    {
        $channel = $this->channelResolver->resolve($bot);
        $localeCode = (string) ($channel->getDefaultLocale()?->getCode() ?? 'en_US');

        $subscriber = $this->findOrCreateSubscriber($bot, $message);
        if ($subscriber->getBot() === null) {
            $subscriber->setBot($bot);
        }

        /** @var CustomerInterface $customer */
        $customer = $subscriber->getCustomer();
        $cart = $this->findOrCreateCart($channel, $customer, $localeCode);

        $this->entityManager->flush();

        return new Conversation($bot, $subscriber, $channel, $localeCode, $cart);
    }

    private function findOrCreateSubscriber(BotInterface $bot, IncomingMessage $message): BotSubscriberInterface
    {
        /** @var BotSubscriberInterface|null $subscriber */
        $subscriber = $this->subscriberRepository->findOneBy(['botSubscriberId' => $message->senderId]);

        if ($subscriber !== null && $subscriber->getCustomer() !== null) {
            return $subscriber;
        }

        $profile = $subscriber === null ? $this->fetchProfile($bot, $message->senderId) : [];

        if ($subscriber === null) {
            $subscriber = $this->subscriberFactory->createNew();
            $subscriber->setBotSubscriberId($message->senderId);
            $subscriber->setChannel($message->channel);
            $subscriber->setBot($bot);
            $subscriber->setFirstName($profile['first_name'] ?? null);
            $subscriber->setLastName($profile['last_name'] ?? null);
            $subscriber->setName($profile['name'] ?? (trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: null));
            $subscriber->setProfilePicture($profile['profile_pic'] ?? null);
            $subscriber->setLocale($profile['locale'] ?? null);
            $subscriber->setTimezone(isset($profile['timezone']) ? (string) $profile['timezone'] : null);
            $subscriber->setGender($profile['gender'] ?? null);
            $this->entityManager->persist($subscriber);
        }

        $subscriber->setCustomer($this->findOrCreateCustomer($subscriber));

        return $subscriber;
    }

    /** @return array<string, string> */
    private function fetchProfile(BotInterface $bot, string $senderId): array
    {
        // Instagram exposes the username and display name; Messenger the first and last name.
        $fields = $bot->getChannelType() === BotInterface::CHANNEL_INSTAGRAM
            ? 'name,username,profile_pic'
            : 'first_name,last_name,profile_pic';

        try {
            $profile = $this->graphClient->get($senderId, ['fields' => $fields], $bot->getPageAccessToken());
        } catch (GraphApiException $exception) {
            $this->logger->info('Could not read the sender profile', ['sender' => $senderId, 'error' => $exception->getMessage()]);

            return [];
        }

        $profile = array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $profile);
        if (($profile['name'] ?? '') === '' && ($profile['username'] ?? '') !== '') {
            $profile['name'] = $profile['username'];
        }

        return array_filter($profile, static fn (string $value): bool => $value !== '');
    }

    private function findOrCreateCustomer(BotSubscriberInterface $subscriber): CustomerInterface
    {
        // A stable placeholder address, so the same person maps to one customer;
        // checkout asks them for their real one.
        $email = sprintf('%s@%s.bot', $subscriber->getBotSubscriberId(), $subscriber->getChannel());

        /** @var CustomerInterface|null $customer */
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => mb_strtolower($email)]);
        if ($customer !== null) {
            return $customer;
        }

        $customer = $this->customerFactory->createNew();
        $customer->setEmail($email);
        $customer->setFirstName($subscriber->getFirstName() ?? $subscriber->getName());
        $customer->setLastName($subscriber->getLastName());
        $this->entityManager->persist($customer);

        return $customer;
    }

    private function findOrCreateCart(ChannelInterface $channel, CustomerInterface $customer, string $localeCode): OrderInterface
    {
        if ($customer->getId() !== null) {
            $cart = $this->orderRepository->findLatestCartByChannelAndCustomer($channel, $customer);
            if ($cart instanceof OrderInterface) {
                return $cart;
            }
        }

        $cart = $this->orderFactory->createNew();
        $cart->setChannel($channel);
        $cart->setCustomer($customer);
        $cart->setLocaleCode($localeCode);
        $cart->setCurrencyCode((string) $channel->getBaseCurrency()?->getCode());
        $this->orderTokenAssigner->assignTokenValueIfNotSet($cart);
        $this->entityManager->persist($cart);

        return $cart;
    }
}
