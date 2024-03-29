<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;


use Sylius\Component\Resource\Model\ResourceInterface;

interface BotInterface extends ResourceInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param string $page_name
     */
    public function setPageName(string $page_name): void;

    /**
     * @return mixed
     */
    public function getPageName();

    /**
     * @param string $page_id
     */
    public function setPageId(string $page_id): void;

    /**
     * @return mixed
     */
    public function getPageId();

    /**
     * @param string $paige_access_token
     */
    public function setPageAccessToken(string $paige_access_token): void;

    /**
     * @return mixed
     */
    public function getPageAccessToken();

    /**
     * @param string $page_image_url
     */
    public function setPageImageUrl(string $page_image_url): void;

    /**
     * @return mixed
     */
    public function getPageImageUrl();

    /**
     * @param bool $disabled
     */
    public function setDisabled(bool $disabled): void;

    /**
     * @return bool
     */
    public function getDisabled(): bool;

    /**
     * @param string $channel_type
     */
    public function setChannelType(string $channel_type): void;

    /**
     * @return mixed
     */
    public function getChannelType();

    /**
     * @param string $timezone
     */
    public function setTimezone(string $timezone): void;

    /**
     * @return mixed
     */
    public function getTimezone();

    /**
     * @param string $persistent_menu
     */
    public function setPersistentMenu(string $persistent_menu): void;

    /**
     * @return string
     */
    public function getPersistentMenu(): string;
}
