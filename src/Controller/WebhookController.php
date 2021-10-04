<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusBotPlugin\Controller;

use Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WebDriver\Session;

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
     * @param Request $request
     * @return Response
     */
    public function messengerWebhook(Request $request): Response
    {
        $this->facebookMessengerService->flow($request);

        // Start listening
        return new Response("Done");
    }

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param CartStorageInterface $cartStorage
     * @param ChannelContextInterface $channelContext
     * @param string $cartToken
     * @return RedirectResponse
     */
    public function botCheckout(
        OrderRepositoryInterface $orderRepository,
        CartStorageInterface $cartStorage,
        ChannelContextInterface $channelContext,
        string $cartToken
    ) {
        /** @var OrderInterface $order */
        $order = $orderRepository->findCartByTokenValue($cartToken);

        $cartStorage->setForChannel($channelContext->getChannel(), $order);

        return new RedirectResponse($this->generateUrl("sylius_shop_cart_summary"));
    }
}
