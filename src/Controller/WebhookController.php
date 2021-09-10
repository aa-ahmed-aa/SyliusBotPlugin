<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusBotPlugin\Controller;

use Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FacebookMessengerService
     */
    protected $facebookMessengerService;

    /**
     * WebhookController constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param FacebookMessengerService $facebookMessengerService
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        FacebookMessengerService $facebookMessengerService
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->facebookMessengerService = $facebookMessengerService;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function messengerWebhookVerification(Request $request): Response
    {
        $token = (string)$request->query->get("hub_verify_token");
        $challenge = (string)$request->query->get("hub_challenge");
        $mode = (string) $request->query->get("hub_mode");

        $verifyToken = getenv('FACEBOOK_VERIFICATION') === false ? "syliusgood" : getenv('FACEBOOK_VERIFICATION');
        if( $verifyToken === $token) {
            return new Response($challenge, 200);
        }

        return new Response("Token Didn't match ", 404);
    }

    /**
     * @return Response
     */
    public function messengerWebhook(): Response
    {
        $this->facebookMessengerService->flow();

        // Start listening
        return new Response("Done");
    }
}
