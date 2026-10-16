<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Graph;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin Graph API client shared by the Messenger and Instagram integrations
 * (Instagram messaging goes through the Messenger Platform with a Page token).
 *
 * Every call that carries an access token also carries an appsecret_proof, so
 * a leaked token cannot be replayed from outside the app.
 */
final class GraphClient implements GraphClientInterface
{
    private const BASE_URL = 'https://graph.facebook.com';

    private const TIMEOUT = 10;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $graphVersion,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function get(string $path, array $query = [], ?string $accessToken = null): array
    {
        return $this->request('GET', $path, ['query' => $this->authenticate($query, $accessToken)]);
    }

    public function post(string $path, array $body = [], ?string $accessToken = null): array
    {
        return $this->request('POST', $path, [
            'query' => $this->authenticate([], $accessToken),
            'json' => $body,
        ]);
    }

    public function delete(string $path, array $query = [], ?string $accessToken = null): array
    {
        return $this->request('DELETE', $path, ['query' => $this->authenticate($query, $accessToken)]);
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getGraphVersion(): string
    {
        return $this->graphVersion;
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->appSecret !== '';
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, scalar>
     */
    private function authenticate(array $query, ?string $accessToken): array
    {
        if ($accessToken === null || $accessToken === '') {
            return $query;
        }

        $query['access_token'] = $accessToken;
        if ($this->appSecret !== '') {
            $query['appsecret_proof'] = hash_hmac('sha256', $accessToken, $this->appSecret);
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options): array
    {
        $url = str_starts_with($path, 'https://')
            ? $path
            : sprintf('%s/%s/%s', self::BASE_URL, $this->graphVersion, ltrim($path, '/'));

        try {
            $response = $this->httpClient->request($method, $url, $options + ['timeout' => self::TIMEOUT]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Graph API call failed', ['method' => $method, 'path' => $this->redact($path), 'error' => $exception->getMessage()]);

            throw new GraphApiException($exception->getMessage(), null, null, $exception);
        }

        $data = json_decode($content, true);
        $data = is_array($data) ? $data : [];

        if ($status >= 400 || isset($data['error'])) {
            $error = is_array($data['error'] ?? null) ? $data['error'] : [];
            $message = (string) ($error['message'] ?? sprintf('Graph API answered HTTP %d', $status));

            $this->logger->warning('Graph API error', [
                'method' => $method,
                'path' => $this->redact($path),
                'status' => $status,
                'code' => $error['code'] ?? null,
                'error' => $message,
            ]);

            throw new GraphApiException($message, isset($error['code']) ? (int) $error['code'] : null, $status);
        }

        return $data;
    }

    private function redact(string $path): string
    {
        return (string) preg_replace('/(access_token|appsecret_proof|client_secret)=[^&]+/', '$1=REDACTED', $path);
    }
}
