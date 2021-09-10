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

    public function messengerWebhookVerification(Request $request)
    {
        $token = $request->query->get("hub_verify_token");
        $challenge = $request->query->get("hub_challenge");
        $mode = $request->query->get("hub_mode");

        if(getenv('FACEBOOK_VERIFICATION') === $token) {
            return new Response($challenge, 200);
        }

        return new Response("Token Didn't match ", 404);
    }

    public function messengerWebhook()
    {
        $this->facebookMessengerService->flow();

        // Start listening
        return new Response("Done");
    }
}
