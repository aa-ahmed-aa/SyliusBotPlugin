<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Facebook;

use SyliusBotPlugin\Entity\BotInterface;
use SyliusBotPlugin\Graph\GraphApiException;
use SyliusBotPlugin\Graph\GraphClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * "Continue with Facebook" for the admin, done server side.
 *
 * The admin's long-lived user token only lives in their session: it is used to
 * list the Pages they manage and is never stored. What is stored is each
 * connected Page's own token, which Facebook issues without an expiry when it
 * is derived from a long-lived user token.
 */
final class FacebookLoginManager
{
    private const SESSION_KEY = '_sylius_bot_facebook';

    private const STATE_KEY = '_sylius_bot_facebook_state';

    /**
     * @param string[] $messengerScopes
     * @param string[] $instagramScopes
     */
    public function __construct(
        private readonly GraphClientInterface $graphClient,
        private readonly RequestStack $requestStack,
        private readonly string $appSecret,
        private readonly array $messengerScopes,
        private readonly array $instagramScopes,
    ) {
    }

    public function getAuthorizationUrl(string $channel, string $redirectUri): string
    {
        $state = bin2hex(random_bytes(16));
        $this->getSession()->set(self::STATE_KEY, ['state' => $state, 'channel' => $channel]);

        $scopes = $channel === BotInterface::CHANNEL_INSTAGRAM ? $this->instagramScopes : $this->messengerScopes;

        return sprintf('https://www.facebook.com/%s/dialog/oauth?%s', $this->graphClient->getGraphVersion(), http_build_query([
            'client_id' => $this->graphClient->getAppId(),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
        ]));
    }

    /**
     * Finishes the login and answers the channel the admin started from.
     *
     * @throws FacebookLoginException
     */
    public function handleCallback(Request $request, string $redirectUri): string
    {
        $expected = $this->getSession()->get(self::STATE_KEY);
        $this->getSession()->remove(self::STATE_KEY);

        $channel = is_array($expected) ? (string) ($expected['channel'] ?? '') : '';
        $channel = in_array($channel, BotInterface::CHANNELS, true) ? $channel : BotInterface::CHANNEL_MESSENGER;

        if ($request->query->has('error')) {
            throw new FacebookLoginException((string) $request->query->get('error_description', 'Facebook login was cancelled.'), $channel);
        }

        $state = (string) $request->query->get('state');
        if (!is_array($expected) || !hash_equals((string) ($expected['state'] ?? ''), $state)) {
            throw new FacebookLoginException('The Facebook login could not be verified. Please try again.', $channel);
        }

        try {
            $shortLived = $this->graphClient->get('oauth/access_token', [
                'client_id' => $this->graphClient->getAppId(),
                'client_secret' => $this->appSecret,
                'redirect_uri' => $redirectUri,
                'code' => (string) $request->query->get('code'),
            ]);

            $longLived = $this->graphClient->get('oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->graphClient->getAppId(),
                'client_secret' => $this->appSecret,
                'fb_exchange_token' => (string) ($shortLived['access_token'] ?? ''),
            ]);

            $token = (string) ($longLived['access_token'] ?? '');
            $me = $this->graphClient->get('me', ['fields' => 'id,name'], $token);
        } catch (GraphApiException $exception) {
            throw new FacebookLoginException($exception->getMessage(), $channel, $exception);
        }

        $this->getSession()->set(self::SESSION_KEY, [
            'token' => $token,
            'user_id' => (string) ($me['id'] ?? ''),
            'user_name' => (string) ($me['name'] ?? ''),
        ]);

        return $channel;
    }

    public function getUserToken(): ?string
    {
        $data = $this->getSession()->get(self::SESSION_KEY);

        return is_array($data) && ($data['token'] ?? '') !== '' ? (string) $data['token'] : null;
    }

    /** @return array{id: string, name: string}|null */
    public function getAccount(): ?array
    {
        $data = $this->getSession()->get(self::SESSION_KEY);
        if (!is_array($data) || ($data['token'] ?? '') === '') {
            return null;
        }

        return ['id' => (string) ($data['user_id'] ?? ''), 'name' => (string) ($data['user_name'] ?? '')];
    }

    public function forget(): void
    {
        $this->getSession()->remove(self::SESSION_KEY);
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
