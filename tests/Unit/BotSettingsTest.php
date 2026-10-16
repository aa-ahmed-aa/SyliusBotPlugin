<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Unit;

use PHPUnit\Framework\TestCase;
use SyliusBotPlugin\Entity\Bot;

final class BotSettingsTest extends TestCase
{
    public function testDefaultsFillMissingKeys(): void
    {
        self::assertSame(Bot::DEFAULT_SETTINGS, (new Bot())->getSettings());
    }

    public function testReadsSettingsSavedBy1x(): void
    {
        $bot = new Bot();
        $reflection = new \ReflectionProperty(Bot::class, 'settings');
        $reflection->setValue($bot, json_encode([
            'list_products' => 'Shop now',
            'get_started_text' => 'Hi {{user_first_name}}',
            'channel' => 'FASHION_WEB',
            'custom_prompt' => 'Be nice',
            'bot_id' => 3,
        ]));

        $settings = $bot->getSettings();

        self::assertSame('Shop now', $settings['list_products']);
        self::assertSame('Hi {{user_first_name}}', $settings['greeting']);
        self::assertSame('FASHION_WEB', $settings['channel_code']);
        self::assertSame('Be nice', $settings['ai_instructions']);
        self::assertSame(Bot::DEFAULT_SETTINGS['my_cart'], $settings['my_cart']);
        self::assertArrayNotHasKey('bot_id', $settings);
    }

    public function testSavesOnlyKnownKeys(): void
    {
        $bot = new Bot();
        $bot->setSettings(['greeting' => '  مرحبا  ', 'unknown' => 'x', 'my_cart' => null]);

        self::assertSame('مرحبا', $bot->getSetting('greeting'));
        self::assertSame(Bot::DEFAULT_SETTINGS['my_cart'], $bot->getSetting('my_cart'));
    }
}
