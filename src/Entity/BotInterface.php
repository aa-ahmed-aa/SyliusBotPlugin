<?php


namespace Ahmedkhd\SyliusBotPlugin\Entity;


use Sylius\Component\Resource\Model\ResourceInterface;

interface BotInterface extends ResourceInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param $page_name
     */
    public function setPageName($page_name): void;

    /**
     * @return mixed
     */
    public function getPageName();

    /**
     * @param $page_id
     */
    public function setPageId($page_id): void;

    /**
     * @return mixed
     */
    public function getPageId();

    /**
     * @param $paige_access_token
     */
    public function setPaigeAccessToken($paige_access_token): void;

    /**
     * @return mixed
     */
    public function getPaigeAccessToken();

    /**
     * @param $page_image_url
     */
    public function setPageImageUrl($page_image_url): void;

    /**
     * @return mixed
     */
    public function getPageImageUrl();

    /**
     * @param $disabled
     */
    public function setDisabled($disabled): void;

    /**
     * @return mixed
     */
    public function getDisabled();

    /**
     * @param $channel_type
     */
    public function setChannelType($channel_type): void;

    /**
     * @return mixed
     */
    public function getChannelType();

    /**
     * @param $timezone
     */
    public function setTimezone($timezone): void;

    /**
     * @return mixed
     */
    public function getTimezone();
}
