<?php


namespace Ahmedkhd\SyliusBotPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylisu_ahmedkhd_bot")
 */
class Bot implements BotInterface
{
    use TimestampableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="page_name", type="string", length=255, nullable=true)
     */
    protected $page_name;

    /**
     * @ORM\Column(name="page_access_token", type="string", length=255, nullable=true)
     */
    protected $paige_access_token;

    /**
     * @ORM\Column(name="page_id", type="string", length=255, nullable=true)
     */
    protected $page_id;

    /**
     * @ORM\Column(name="page_image_url", type="string", length=255, nullable=true)
     */
    protected $page_image_url;

    /**
     * @ORM\Column(name="disabled", type="boolean", options={"default": false})
     */
    protected $disabled = false;

    /**
     * @ORM\Column(name="channel_type", type="string", length=255, nullable=true)
     */
    protected $channel_type;

    /**
     * @ORM\Column(name="timezone", type="string", length=255, nullable=true)
     */
    protected $timezone;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $page_name
     */
    public function setPageName($page_name): void
    {
        $this->page_name = $page_name;
    }

    /**
     * @return mixed
     */
    public function getPageName()
    {
        return $this->page_name;
    }

    /**
     * @param mixed $page_id
     */
    public function setPageId($page_id): void
    {
        $this->page_id = $page_id;
    }

    /**
     * @return mixed
     */
    public function getPageId()
    {
        return $this->page_id;
    }

    /**
     * @param mixed $paige_access_token
     */
    public function setPaigeAccessToken($paige_access_token): void
    {
        $this->paige_access_token = $paige_access_token;
    }

    /**
     * @return mixed
     */
    public function getPaigeAccessToken()
    {
        return $this->paige_access_token;
    }

    /**
     * @param mixed $page_image_url
     */
    public function setPageImageUrl($page_image_url): void
    {
        $this->page_image_url = $page_image_url;
    }

    /**
     * @return mixed
     */
    public function getPageImageUrl()
    {
        return $this->page_image_url;
    }

    /**
     * @param mixed $disabled
     */
    public function setDisabled($disabled): void
    {
        $this->disabled = $disabled;
    }

    /**
     * @return mixed
     */
    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * @param mixed $channel_type
     */
    public function setChannelType($channel_type): void
    {
        $this->channel_type = $channel_type;
    }

    /**
     * @return mixed
     */
    public function getChannelType()
    {
        return $this->channel_type;
    }

    /**
     * @param mixed $timezone
     */
    public function setTimezone($timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return mixed
     */
    public function getTimezone()
    {
        return $this->timezone;
    }
}
