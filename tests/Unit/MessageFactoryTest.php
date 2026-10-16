<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Unit;

use PHPUnit\Framework\TestCase;
use SyliusBotPlugin\Bot\ConversationSender;
use SyliusBotPlugin\Bot\MessageFactory;
use SyliusBotPlugin\Bot\SendApi;

final class MessageFactoryTest extends TestCase
{
    private MessageFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MessageFactory();
    }

    public function testButtonTemplateKeepsAtMostThreeButtons(): void
    {
        $button = $this->factory->postbackButton('A', ['type' => 'my_cart']);
        $message = $this->factory->buttons('Pick one', [$button, $button, $button, $button]);

        self::assertSame('button', $message['attachment']['payload']['template_type']);
        self::assertCount(3, $message['attachment']['payload']['buttons']);
    }

    public function testTitlesAreClippedToMetaLimits(): void
    {
        $button = $this->factory->postbackButton(str_repeat('x', 40), ['type' => 'checkout']);
        $element = $this->factory->element(str_repeat('é', 120), 'sub');

        self::assertSame(20, mb_strlen($button['title']));
        self::assertSame(80, mb_strlen($element['title']));
        self::assertStringEndsWith('…', $element['title']);
        self::assertSame('{"type":"checkout"}', $button['payload']);
    }

    public function testCarouselKeepsAtMostTenCards(): void
    {
        $elements = array_fill(0, 12, $this->factory->element('Card'));
        $message = $this->factory->carousel($elements, MessageFactory::RATIO_HORIZONTAL);

        self::assertCount(10, $message['attachment']['payload']['elements']);
        self::assertSame('horizontal', $message['attachment']['payload']['image_aspect_ratio']);
    }

    public function testInstagramSanitizingDropsUnsupportedParameters(): void
    {
        $message = $this->factory->carousel([
            $this->factory->element('Card', '', null, [$this->factory->urlButton('Open', 'https://example.com')]),
        ]);

        $sanitized = SendApi::sanitizeForInstagram($message);

        self::assertArrayNotHasKey('image_aspect_ratio', $sanitized['attachment']['payload']);
        self::assertArrayNotHasKey('webview_height_ratio', $sanitized['attachment']['payload']['elements'][0]['buttons'][0]);
        self::assertSame('https://example.com', $sanitized['attachment']['payload']['elements'][0]['buttons'][0]['url']);
    }

    public function testMessagesAreDescribedForTheConversationLog(): void
    {
        self::assertSame('Hi', ConversationSender::describe($this->factory->text('Hi')));
        self::assertSame("Pick\n[Cart · Shop]", ConversationSender::describe($this->factory->buttons('Pick', [
            $this->factory->postbackButton('Cart', ['type' => 'my_cart']),
            $this->factory->urlButton('Shop', 'https://example.com'),
        ])));
        self::assertSame('🛍 Mug · Cap', ConversationSender::describe($this->factory->carousel([
            $this->factory->element('Mug'),
            $this->factory->element('Cap'),
        ])));
    }
}
