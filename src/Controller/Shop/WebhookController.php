<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Controller\Shop;

use Psr\Log\LoggerInterface;
use SyliusBotPlugin\Bot\ConversationHandler;
use SyliusBotPlugin\Bot\IncomingMessageParser;
use SyliusBotPlugin\Bot\WebhookSignatureVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The callback URL of the Meta app, for both Messenger (`object: page`) and
 * Instagram (`object: instagram`).
 */
final class WebhookController
{
    public function __construct(
        private readonly IncomingMessageParser $parser,
        private readonly ConversationHandler $handler,
        private readonly WebhookSignatureVerifier $signatureVerifier,
        private readonly string $verifyToken,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Meta's subscription handshake. PHP turns the `hub.` dots in the query into underscores. */
    public function verify(Request $request): Response
    {
        $token = (string) $request->query->get('hub_verify_token');

        if ($request->query->get('hub_mode') === 'subscribe' && $this->verifyToken !== '' && hash_equals($this->verifyToken, $token)) {
            return new Response((string) $request->query->get('hub_challenge'), Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        $this->logger->warning('Webhook verification failed: the verify token does not match');

        return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    public function receive(Request $request): Response
    {
        $payload = $request->getContent();

        if (!$this->signatureVerifier->isValid($payload, $request->headers->get('X-Hub-Signature-256'))) {
            $this->logger->warning('Webhook call rejected: invalid signature');

            return new Response('Invalid signature', Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($payload, true);

        foreach ($this->parser->parse(is_array($body) ? $body : []) as $message) {
            try {
                $this->handler->handle($message);
            } catch (\Throwable $exception) {
                // Answer 200 regardless: Meta retries failed deliveries, and a
                // retry would repeat whatever part of the reply already went out.
                $this->logger->error('Could not answer a bot message', [
                    'channel' => $message->channel,
                    'account' => $message->accountId,
                    'exception' => $exception,
                ]);
            }
        }

        return new Response('EVENT_RECEIVED');
    }
}
