<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

use Sylius\Resource\Model\TimestampableTrait;

class Bot implements BotInterface
{
    use TimestampableTrait;

    /**
     * Labels and texts a merchant can change per page. Missing keys fall back
     * to these, so a bot saved by an older version still renders a full menu.
     */
    public const DEFAULT_SETTINGS = [
        'list_products' => 'List products 📦',
        'my_cart' => 'My cart 🛒',
        'empty_cart' => 'Empty cart 🗑️',
        'checkout' => 'Checkout 💳',
        'greeting' => 'Hello {{user_first_name}}! 👋',
        'channel_code' => '',
        'ai_instructions' => '',
    ];

    protected ?int $id = null;

    protected ?string $pageName = null;

    protected ?string $pageId = null;

    protected ?string $pageAccessToken = null;

    protected ?string $pageImageUrl = null;

    protected ?string $channelType = null;

    protected ?string $facebookPageId = null;

    protected bool $disabled = false;

    /** JSON, kept in the column 1.x called `persistent_menu`. */
    protected ?string $settings = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPageName(): ?string
    {
        return $this->pageName;
    }

    public function setPageName(?string $pageName): void
    {
        $this->pageName = $pageName;
    }

    public function getPageId(): ?string
    {
        return $this->pageId;
    }

    public function setPageId(?string $pageId): void
    {
        $this->pageId = $pageId;
    }

    public function getPageAccessToken(): ?string
    {
        return $this->pageAccessToken;
    }

    public function setPageAccessToken(?string $pageAccessToken): void
    {
        $this->pageAccessToken = $pageAccessToken;
    }

    public function getPageImageUrl(): ?string
    {
        return $this->pageImageUrl;
    }

    public function setPageImageUrl(?string $pageImageUrl): void
    {
        $this->pageImageUrl = $pageImageUrl;
    }

    public function getChannelType(): ?string
    {
        return $this->channelType;
    }

    public function setChannelType(?string $channelType): void
    {
        $this->channelType = $channelType;
    }

    public function getFacebookPageId(): ?string
    {
        return $this->facebookPageId;
    }

    public function setFacebookPageId(?string $facebookPageId): void
    {
        $this->facebookPageId = $facebookPageId;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    public function getSettings(): array
    {
        $stored = json_decode((string) $this->settings, true);
        $stored = is_array($stored) ? $stored : [];

        // 1.x stored the greeting as `get_started_text` and the sales channel as `channel`.
        $stored['greeting'] ??= $stored['get_started_text'] ?? null;
        $stored['channel_code'] ??= $stored['channel'] ?? null;
        $stored['ai_instructions'] ??= $stored['custom_prompt'] ?? null;

        $settings = [];
        foreach (self::DEFAULT_SETTINGS as $key => $default) {
            $value = $stored[$key] ?? null;
            $settings[$key] = is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
        }

        return $settings;
    }

    public function setSettings(array $settings): void
    {
        $clean = [];
        foreach (array_keys(self::DEFAULT_SETTINGS) as $key) {
            $clean[$key] = trim((string) ($settings[$key] ?? ''));
        }

        // Unicode stays escaped, so emoji labels survive a connection that is
        // not utf8mb4 (Sylius' default `charset: UTF8` is 3-byte utf8).
        $this->settings = json_encode($clean, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    public function getSetting(string $key): string
    {
        return $this->getSettings()[$key] ?? '';
    }
}
