<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SyliusBotPlugin\Bot\IncomingMessage;
use SyliusBotPlugin\Bot\IncomingMessageParser;

final class IncomingMessageParserTest extends TestCase
{
    private IncomingMessageParser $parser;

    protected function setUp(): void
    {
        $this->parser = new IncomingMessageParser();
    }

    public function testMessengerTextMessage(): void
    {
        $messages = $this->parser->parse([
            'object' => 'page',
            'entry' => [['id' => 'PAGE', 'messaging' => [[
                'sender' => ['id' => 'USER'],
                'recipient' => ['id' => 'PAGE'],
                'message' => ['mid' => 'm1', 'text' => 'Do you have red shoes?'],
            ]]]],
        ]);

        self::assertCount(1, $messages);
        self::assertSame('messenger', $messages[0]->channel);
        self::assertSame('PAGE', $messages[0]->accountId);
        self::assertSame('USER', $messages[0]->senderId);
        self::assertSame('Do you have red shoes?', $messages[0]->text);
        self::assertNull($messages[0]->action);
    }

    public function testInstagramUsesTheRecipientAsAccount(): void
    {
        $messages = $this->parser->parse([
            'object' => 'instagram',
            'entry' => [['id' => 'IG_ENTRY', 'messaging' => [[
                'sender' => ['id' => 'IGSID'],
                'recipient' => ['id' => 'IG_ACCOUNT'],
                'message' => ['text' => 'cart'],
            ]]]],
        ]);

        self::assertSame('instagram', $messages[0]->channel);
        self::assertSame('IG_ACCOUNT', $messages[0]->accountId);
        self::assertSame(IncomingMessage::ACTION_MY_CART, $messages[0]->getActionType());
    }

    public function testPostbackPayloadBecomesTheAction(): void
    {
        $messages = $this->parser->parse([
            'object' => 'page',
            'entry' => [['id' => 'PAGE', 'messaging' => [[
                'sender' => ['id' => 'USER'],
                'postback' => ['payload' => '{"type":"add_to_cart","product_id":7}'],
            ]]]],
        ]);

        self::assertSame(['type' => 'add_to_cart', 'product_id' => 7], $messages[0]->action);
    }

    public function testUnknownPostbackStartsTheConversation(): void
    {
        $messages = $this->parser->parse([
            'object' => 'page',
            'entry' => [['id' => 'PAGE', 'messaging' => [['sender' => ['id' => 'USER'], 'postback' => ['payload' => 'GET_STARTED']]]]],
        ]);

        self::assertSame(IncomingMessage::ACTION_GET_STARTED, $messages[0]->getActionType());
    }

    public function testEchoesReceiptsAndOtherObjectsAreIgnored(): void
    {
        self::assertSame([], $this->parser->parse([
            'object' => 'page',
            'entry' => [['id' => 'PAGE', 'messaging' => [
                ['sender' => ['id' => 'PAGE'], 'recipient' => ['id' => 'USER'], 'message' => ['is_echo' => true, 'text' => 'hello']],
                ['sender' => ['id' => 'USER'], 'delivery' => ['mids' => ['m1']]],
                ['sender' => ['id' => 'USER'], 'read' => ['watermark' => 1]],
            ]]],
        ]));

        self::assertSame([], $this->parser->parse(['object' => 'whatsapp_business_account', 'entry' => []]));
        self::assertSame([], $this->parser->parse([]));
    }

    public function testEveryEventOfABatchIsReturned(): void
    {
        $messages = $this->parser->parse([
            'object' => 'page',
            'entry' => [
                ['id' => 'PAGE', 'messaging' => [['sender' => ['id' => 'A'], 'message' => ['text' => 'hi']]]],
                ['id' => 'PAGE', 'messaging' => [['sender' => ['id' => 'B'], 'message' => ['text' => 'checkout']]]],
            ],
        ]);

        self::assertCount(2, $messages);
        self::assertSame(['A', 'B'], [$messages[0]->senderId, $messages[1]->senderId]);
    }

    /** @return iterable<string, array{string, array<string, mixed>|null}> */
    public static function textCommands(): iterable
    {
        yield 'products' => ['Products', ['type' => 'list_items', 'page' => 1]];
        yield 'page 3' => ['products 3', ['type' => 'list_items', 'page' => 3]];
        yield 'cart' => ['my cart', ['type' => 'my_cart']];
        yield 'empty' => ['Empty my cart!', ['type' => 'empty_cart']];
        yield 'checkout' => ['checkout', ['type' => 'checkout']];
        yield 'greeting' => ['Hello', ['type' => 'get_started']];
        yield 'question' => ['hey, do you have red shirts?', null];
        yield 'arabic' => ['عندكم محافظ؟', null];
    }

    /** @param array<string, mixed>|null $expected */
    #[DataProvider('textCommands')]
    public function testTextCommands(string $text, ?array $expected): void
    {
        self::assertSame($expected, IncomingMessageParser::matchTextCommand($text));
    }
}
