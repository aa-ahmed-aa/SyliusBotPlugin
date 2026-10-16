# Answering free text with AI

Menu buttons and the text commands (`products`, `cart`, `checkout`, `hi`) are handled by the plugin. Everything else a customer types, like *"do you have this in red?"*, goes to a **reply generator**.

The plugin ships `NullReplyGenerator`, which answers nothing; the bot then replies with its menu. To answer questions, implement your own.

## The contract

```php
namespace SyliusBotPlugin\Bot\Reply;

interface ReplyGeneratorInterface
{
    public function generate(Conversation $conversation, string $text): ?Reply;
}
```

Return a `Reply` with any of:

- `text`: sent as a plain message;
- `productIds`: products shown as cards after the text. They are re-checked against live stock and the page's sales channel, so an index that is out of date never offers a product that cannot be bought;
- `action`: `Reply::ACTION_VIEW_CART` or `Reply::ACTION_CHECKOUT`, run after the text.

Return `null` (or throw) to fall back to the menu.

What you can use:

- `$conversation->bot->getSetting('ai_instructions')`: the instructions the merchant wrote on the page's *Bot settings*;
- `$conversation->channel`, `$conversation->localeCode`, `$conversation->cart`, `$conversation->subscriber`;
- `BotMessageRepositoryInterface::findLatestForSubscriber($conversation->subscriber, 20)`: the conversation so far, oldest first, already including the current message.

## Example with Symfony AI / any HTTP LLM

```php
namespace App\Bot;

use SyliusBotPlugin\Bot\Conversation;
use SyliusBotPlugin\Bot\Reply\Reply;
use SyliusBotPlugin\Bot\Reply\ReplyGeneratorInterface;
use SyliusBotPlugin\Repository\BotMessageRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LlmReplyGenerator implements ReplyGeneratorInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BotMessageRepositoryInterface $messages,
        private readonly string $apiKey,
    ) {
    }

    public function generate(Conversation $conversation, string $text): ?Reply
    {
        $history = array_map(
            static fn ($message) => ['role' => $message->isInbound() ? 'user' : 'assistant', 'content' => $message->getText()],
            $this->messages->findLatestForSubscriber($conversation->subscriber, 20),
        );

        $response = $this->httpClient->request('POST', 'https://api.example-llm.com/v1/chat', [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'system' => $conversation->bot->getSetting('ai_instructions'),
                'messages' => $history,
            ],
        ])->toArray();

        return new Reply(text: $response['reply'] ?? null);
    }
}
```

Register it as the generator:

```yaml
services:
    App\Bot\LlmReplyGenerator:
        arguments:
            $messages: '@sylius_bot.repository.bot_message'
            $apiKey: '%env(LLM_API_KEY)%'

    sylius_bot.reply_generator: '@App\Bot\LlmReplyGenerator'
```

Keep generation fast: Meta expects the webhook to answer within about 20 seconds, and the customer sees a typing indicator meanwhile.
