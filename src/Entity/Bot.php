<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_bot_plugin_bot")
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
     * @ORM\Column(name="user_id", type="string", length=255, nullable=true)
     */
    protected $user_id;

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
     * @ORM\Column(name="page_image_url", type="text", nullable=true)
     */
    protected $page_image_url;

    /**
     * @var bool | null
     * @ORM\Column(name="disabled", type="boolean", nullable=true, options={"default": false})
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
     * @var string | null
     * @ORM\Column(name="persistent_menu", type="text", nullable=true)
     */
    protected $persistent_menu;

    /** @var array $PERSISTENT_MENU_FALLBACK */
    public const PERSISTENT_MENU_FALLBACK = [
        "list_products" => "List products",
        "order_summery" => "Order summery",
        "my_cart" => "My cart",
        "empty_cart" => "Empty cart",
        "checkout" => "Checkout",
    ];

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int)$this->id;
    }

    /**
     * @param string $page_name
     */
    public function setPageName(string $page_name): void
    {
        $this->page_name = $page_name;
    }

    /**
     * @return string
     */
    public function getPageName(): string
    {
        return (string)$this->page_name;
    }

    /**
     * @param string $page_id
     */
    public function setPageId(string $page_id): void
    {
        $this->page_id = $page_id;
    }

    /**
     * @return string
     */
    public function getPageId(): string
    {
        return (string)$this->page_id;
    }

    /**
     * @param string $page_access_token
     */
    public function setPageAccessToken(string $page_access_token): void
    {
        $this->page_access_token = $page_access_token;
    }

    /**
     * @return string
     */
    public function getPageAccessToken(): string
    {
        return (string)$this->page_access_token;
    }

    /**
     * @param string $page_image_url
     */
    public function setPageImageUrl(string $page_image_url): void
    {
        $this->page_image_url = $page_image_url;
    }

    /**
     * @return string
     */
    public function getPageImageUrl(): string
    {
        return (string)$this->page_image_url;
    }

    /**
     * @param bool $disabled
     */
    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    /**
     * @return bool
     */
    public function getDisabled(): bool
    {
        return (bool)$this->disabled;
    }

    /**
     * @param string $channel_type
     */
    public function setChannelType(string $channel_type): void
    {
        $this->channel_type = $channel_type;
    }

    /**
     * @return string
     */
    public function getChannelType(): string
    {
        return (string)$this->channel_type;
    }

    /**
     * @param string $timezone
     */
    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return (string)$this->timezone;
    }

    /**
     * @param string $persistent_menu
     */
    public function setPersistentMenu(string $persistent_menu): void
    {
        $this->persistent_menu = $persistent_menu;
    }

    /**
     * @return string
     */
    public function getPersistentMenu(): string
    {
        return (string)$this->persistent_menu;
    }

    /**
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->user_id;
    }

    /**
     * @param string|null $user_id
     */
    public function setUserId(?string $user_id): void
    {
        $this->user_id = $user_id;
    }
}
