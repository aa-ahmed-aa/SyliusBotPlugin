<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Facebook;

use SyliusBotPlugin\Graph\GraphApiException;
use SyliusBotPlugin\Graph\GraphClientInterface;

/**
 * Lists the Pages a user manages, each with its Page token and linked
 * Instagram account, in as few Graph calls as paging allows.
 */
final class ManagedPageProvider
{
    private const FIELDS = 'id,name,access_token,picture.type(large){url},instagram_business_account{id,username,name,profile_picture_url}';

    private const MAX_PAGES = 20;

    public function __construct(private readonly GraphClientInterface $graphClient)
    {
    }

    /**
     * @return ManagedPage[]
     *
     * @throws GraphApiException
     */
    public function getPages(string $userToken): array
    {
        $pages = [];
        $after = null;

        for ($i = 0; $i < self::MAX_PAGES; ++$i) {
            $query = ['fields' => self::FIELDS, 'limit' => 100];
            if ($after !== null) {
                $query['after'] = $after;
            }

            $response = $this->graphClient->get('me/accounts', $query, $userToken);

            foreach ((array) ($response['data'] ?? []) as $page) {
                if (!is_array($page) || !isset($page['id'], $page['access_token'])) {
                    continue;
                }

                $instagram = is_array($page['instagram_business_account'] ?? null) ? $page['instagram_business_account'] : [];

                $pages[] = new ManagedPage(
                    (string) $page['id'],
                    (string) ($page['name'] ?? $page['id']),
                    (string) $page['access_token'],
                    isset($page['picture']['data']['url']) ? (string) $page['picture']['data']['url'] : null,
                    isset($instagram['id']) ? (string) $instagram['id'] : null,
                    isset($instagram['username']) ? (string) $instagram['username'] : null,
                    isset($instagram['name']) ? (string) $instagram['name'] : null,
                    isset($instagram['profile_picture_url']) ? (string) $instagram['profile_picture_url'] : null,
                );
            }

            $after = $response['paging']['cursors']['after'] ?? null;
            if (!isset($response['paging']['next']) || !is_string($after)) {
                break;
            }
        }

        return $pages;
    }

    /** @throws GraphApiException */
    public function findPage(string $userToken, string $pageId): ?ManagedPage
    {
        foreach ($this->getPages($userToken) as $page) {
            if ($page->id === $pageId) {
                return $page;
            }
        }

        return null;
    }

    /** @throws GraphApiException */
    public function findPageByInstagramId(string $userToken, string $instagramId): ?ManagedPage
    {
        foreach ($this->getPages($userToken) as $page) {
            if ($page->instagramId === $instagramId) {
                return $page;
            }
        }

        return null;
    }
}
