<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Graph;

interface GraphClientInterface
{
    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     *
     * @throws GraphApiException
     */
    public function get(string $path, array $query = [], ?string $accessToken = null): array;

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     *
     * @throws GraphApiException
     */
    public function post(string $path, array $body = [], ?string $accessToken = null): array;

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     *
     * @throws GraphApiException
     */
    public function delete(string $path, array $query = [], ?string $accessToken = null): array;

    public function getAppId(): string;

    public function getGraphVersion(): string;

    public function isConfigured(): bool;
}
