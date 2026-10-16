<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

/**
 * A person who messaged one of the store's pages or Instagram accounts.
 */
interface BotSubscriberInterface extends ResourceInterface, TimestampableInterface
{
    public function getBot(): ?BotInterface;

    public function setBot(?BotInterface $bot): void;

    /** Page-scoped id (PSID) on Messenger, Instagram-scoped id (IGSID) on Instagram. */
    public function getBotSubscriberId(): ?string;

    public function setBotSubscriberId(?string $botSubscriberId): void;

    public function getChannel(): ?string;

    public function setChannel(?string $channel): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getFirstName(): ?string;

    public function setFirstName(?string $firstName): void;

    public function getLastName(): ?string;

    public function setLastName(?string $lastName): void;

    public function getDisplayName(): string;

    public function getProfilePicture(): ?string;

    public function setProfilePicture(?string $profilePicture): void;

    public function getLocale(): ?string;

    public function setLocale(?string $locale): void;

    public function getTimezone(): ?string;

    public function setTimezone(?string $timezone): void;

    public function getGender(): ?string;

    public function setGender(?string $gender): void;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;
}
