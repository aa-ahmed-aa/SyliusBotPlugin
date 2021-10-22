<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusBotPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="ahmedkhd_sylius_bot")
 */
class Bot implements BotInterface
{
    use TimestampableEntity;

    /**
     * @var integer | null
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string | null
     * @ORM\Column(name="page_name", type="string", length=255, nullable=true)
     */
    protected $page_name;

    /**
     * @var string | null
     * @ORM\Column(name="page_access_token", type="string", length=255, nullable=true)
     */
    protected $page_access_token;

    /**
     * @var string | null
     * @ORM\Column(name="page_id", type="string", length=255, nullable=true)
     */
    protected $page_id;

    /**
     * @var string | null
     * @ORM\Column(name="page_image_url", type="string", length=255, nullable=true)
     */
    protected $page_image_url;

    /**
     * @var string | null
     * @ORM\Column(name="disabled", type="boolean", options={"default": false})
     */
    protected $disabled;

    /**
     * @var string | null
     * @ORM\Column(name="channel_type", type="string", length=255, nullable=true)
     */
    protected $channel_type;

    /**
     * @var string | null
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
     * @param string $page_name
     */
    public function setPageName(string $page_name): void
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
     * @param string $page_id
     */
    public function setPageId(string $page_id): void
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
     * @param string $page_access_token
     */
    public function setPaigeAccessToken(string $page_access_token): void
    {
        $this->page_access_token = $page_access_token;
    }

    /**
     * @return mixed
     */
    public function getPageAccessToken()
    {
        return $this->page_access_token;
    }

    /**
     * @param string $page_image_url
     */
    public function setPageImageUrl(string $page_image_url): void
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
     * @param string $disabled
     */
    public function setDisabled(string $disabled): void
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
     * @param string $channel_type
     */
    public function setChannelType(string $channel_type): void
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
     * @param string $timezone
     */
    public function setTimezone(string $timezone): void
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
