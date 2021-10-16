<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Ahmedkhd\SyliusBotPlugin\Entity\BotSubscriber;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use Psr\Http\Message\ResponseInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface BotServiceInterface
{
    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface;

    /**
     * @return ChannelInterface
     */
    public function getDefaultChannel(): ChannelInterface;

    /**
     * @return BotSubscriber
     */
    public function getUser(): BotSubscriber;

    /**
     * @return Request
     */
    public function getRequest(): Request;

    /**
     * @param Request $request
     */
    public function setRequest(Request $request): void;

    /**
     * @return bool|mixed
     */
    public function getPayload();

    /**
     * @param iterable $products
     * @param string $localeCode
     * @param ChannelInterface $channel
     * @param int $pageNo
     * @return array
     */
    public function wrapProductsForListing(iterable $products, string $localeCode, ChannelInterface $channel, $pageNo = 1): array;

    /**
     * @param iterable $orderItems
     * @param int $pageNo
     * @return array
     */
    public function wrapProductsForCart(iterable $orderItems, $pageNo = 1): array;

    /**
     * @param array $menuItems
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updatePresistentMenu($menuItems = []): Response;

    /**
     * @param string|array|OutgoingMessage|Question $message
     * @return ResponseInterface|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendMessage($message);

    /**
     * @param string $url
     * @param array|null $body
     * @param string|null $method
     * @return ResponseInterface|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendFacebookRequest(string $url, ?array $body = [], ?string $method = Request::METHOD_GET);

    /**
     * @param array $body
     * @return mixed
     */
    public function getRequestOption(array $body);

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setSubscriber();

    /**
     * Set Current Active Bot Order
     */
    public function setCurrentActiveOrder();
}
