<?php


namespace Ahmedkhd\SyliusBotPlugin\Controller;


use Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class FacebookController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * WebhookController constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param FacebookMessengerService $facebookMessengerService
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger
    ) {
        $this->container = $container;
        $this->logger = $logger;
    }

    public function facebookConnect(Request $request): Response
    {
        $options = $request->attributes->get('_sylius');
        $template = $options['template'];
        return $this->render($template);
    }
}
