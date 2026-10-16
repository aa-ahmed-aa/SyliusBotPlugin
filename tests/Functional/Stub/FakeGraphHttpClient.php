<?php

declare(strict_types=1);

namespace Tests\SyliusBotPlugin\Functional\Stub;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Stands in for graph.facebook.com in the test environment: answers like the
 * Graph API would and records every call for assertions.
 */
final class FakeGraphHttpClient implements HttpClientInterface
{
    public const PAGE_ID = '1000000000001';

    public const INSTAGRAM_ID = '17800000000000001';

    /** @var array<int, array{method: string, path: string, query: array<string, mixed>, body: array<string, mixed>}> */
    public static array $requests = [];

    private MockHttpClient $client;

    public function __construct()
    {
        $this->client = new MockHttpClient(fn (string $method, string $url, array $options): MockResponse => $this->respond($method, $url, $options));
    }

    public static function reset(): void
    {
        self::$requests = [];
    }

    /** @return array<int, array{method: string, path: string, query: array<string, mixed>, body: array<string, mixed>}> */
    public static function sentMessages(): array
    {
        return array_values(array_filter(
            self::$requests,
            static fn (array $request): bool => $request['method'] === 'POST' && $request['path'] === 'me/messages' && isset($request['body']['message']),
        ));
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return $this;
    }

    /** @param array<string, mixed> $options */
    private function respond(string $method, string $url, array $options): MockResponse
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $path = (string) preg_replace('#^/v[0-9.]+/#', '', $parts['path'] ?? '');
        $body = isset($options['body']) && is_string($options['body']) ? (json_decode($options['body'], true) ?? []) : [];

        self::$requests[] = ['method' => $method, 'path' => $path, 'query' => $query, 'body' => $body];

        $data = match (true) {
            $path === 'oauth/access_token' => ['access_token' => 'user-token', 'token_type' => 'bearer'],
            $path === 'me' => ['id' => '42', 'name' => 'Test Admin'],
            $path === 'me/accounts' => ['data' => [[
                'id' => self::PAGE_ID,
                'name' => 'Test Page',
                'access_token' => 'page-token',
                'picture' => ['data' => ['url' => 'https://example.com/page.png']],
                'instagram_business_account' => ['id' => self::INSTAGRAM_ID, 'username' => 'teststore', 'name' => 'Test Store'],
            ]]],
            str_ends_with($path, '/subscribed_apps') && $method === 'GET' => ['data' => [['id' => 'test-app-id', 'subscribed_fields' => ['messages', 'messaging_postbacks']]]],
            $path === 'me/messages' => ['recipient_id' => (string) ($body['recipient']['id'] ?? ''), 'message_id' => 'm_' . count(self::$requests)],
            $method === 'GET' && preg_match('#^\d+$#', $path) === 1 => ['id' => $path, 'first_name' => 'Sara', 'last_name' => 'Ali', 'name' => 'Sara Ali'],
            default => ['success' => true],
        };

        return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }
}
