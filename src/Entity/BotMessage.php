<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Entity;

class BotMessage implements BotMessageInterface
{
    protected ?int $id = null;

    protected ?BotSubscriberInterface $subscriber = null;

    protected string $direction = self::DIRECTION_INBOUND;

    protected string $text = '';

    protected \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscriber(): ?BotSubscriberInterface
    {
        return $this->subscriber;
    }

    public function setSubscriber(?BotSubscriberInterface $subscriber): void
    {
        $this->subscriber = $subscriber;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
