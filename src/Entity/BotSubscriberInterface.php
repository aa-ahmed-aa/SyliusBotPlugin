<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;


use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface BotSubscriberInterface extends ResourceInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string|null $firstName
     */
    public function setFirstName(?string $firstName): void;

    /**
     * @return string|null
     */
    public function getFirstName(): ?string;

    /**
     * @param string|null $lastName
     */
    public function setLastName(?string $lastName): void;

    /**
     * @return string|null
     */
    public function getLastName(): ?string;

    /**
     * @param mixed|null $timezone
     */
    public function setTimezone($timezone): void;

    /**
     * @return mixed|null
     */
    public function getTimezone();

    /**
     * @param string|null $profilePicture
     */
    public function setProfilePicture(?string $profilePicture): void;

    /**
     * @return string|null
     */
    public function getProfilePicture(): ?string;

    /**
     * @param string|null $locale
     */
    public function setLocale(?string $locale): void;

    /**
     * @return string|null
     */
    public function getLocale(): ?string;

    /**
     * @param string|null $gender
     */
    public function setGender(?string $gender): void;

    /**
     * @return string|null
     */
    public function getGender(): ?string;

    /**
     * @return string|null
     */
    public function getBotSubscriberId(): ?string;

    /**
     * @param string|null $botSubscriberId
     */
    public function setBotSubscriberId(?string $botSubscriberId): void;

    /**
     * @param string|null $channel
     */
    public function setChannel(?string $channel): void;

    /**
     * @return string|null
     */
    public function getChannel(): ?string;

    /**
     * @param CustomerInterface $customer
     */
    public function setCustomer(CustomerInterface $customer): void;

    /**
     * @return CustomerInterface
     */
    public function getCustomer(): CustomerInterface;
}
