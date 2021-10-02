<?php


namespace Ahmedkhd\SyliusBotPlugin\Entity;


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
     * @param string|null $timezone
     */
    public function setTimezone(?string $timezone): void;

    /**
     * @return string|null
     */
    public function getTimezone(): ?string;

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
}
