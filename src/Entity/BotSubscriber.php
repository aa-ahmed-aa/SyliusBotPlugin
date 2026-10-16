<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Model\TimestampableTrait;

class BotSubscriber implements BotSubscriberInterface
{
    use TimestampableTrait;

    protected ?int $id = null;

    protected ?BotInterface $bot = null;

    protected ?string $botSubscriberId = null;

    protected ?string $channel = null;

    protected ?string $name = null;

    protected ?string $firstName = null;

    protected ?string $lastName = null;

    protected ?string $profilePicture = null;

    protected ?string $locale = null;

    protected ?string $timezone = null;

    protected ?string $gender = null;

    protected ?CustomerInterface $customer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBot(): ?BotInterface
    {
        return $this->bot;
    }

    public function setBot(?BotInterface $bot): void
    {
        $this->bot = $bot;
    }

    public function getBotSubscriberId(): ?string
    {
        return $this->botSubscriberId;
    }

    public function setBotSubscriberId(?string $botSubscriberId): void
    {
        $this->botSubscriberId = $botSubscriberId;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): void
    {
        $this->channel = $channel;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getDisplayName(): string
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            return $name;
        }

        $name = trim($this->firstName . ' ' . $this->lastName);

        return $name !== '' ? $name : '#' . $this->id;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): void
    {
        $this->profilePicture = $profilePicture;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): void
    {
        $this->gender = $gender;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }
}
