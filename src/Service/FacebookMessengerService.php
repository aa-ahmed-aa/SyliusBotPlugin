<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\FacebookDriver;
use Pagerfanta\Pagerfanta;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FacebookMessengerService
 * @package Ahmedkhd\SyliusBotPlugin\Service
 */
class FacebookMessengerService extends BotService
{
    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $defaultLocaleCode;

    /**
     * @var ChannelInterface
     */
    protected $defaultChannel;

    /**
     * FacebookMessengerService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        /**
         * @psalm-suppress PossiblyFalsePropertyAssignmentValue
         */
        $this->baseUrl = getenv('APP_URL') === false ? "https://www.google.com" : getenv('APP_URL');
        $this->defaultLocaleCode = $this->container->get('sylius.context.locale')->getLocaleCode();
        $this->defaultChannel = $this->container->get('sylius.context.channel')->getChannel();
    }

    /**
     * The Bot flow conversation
     */
    public function flow(): void
    {
        //create menu
        $this->updatePresistentMenu();

        DriverManager::loadDriver(FacebookDriver::class);
        $botman = BotManFactory::create($this->getConfiguration());

        $botman = $this->fallbackMessage($botman);
        $botman = $this->listProducts($botman);
        $botman = $this->removeFromCart($botman);
        $botman = $this->addToCart($botman);
        $botman = $this->listItemsInCart($botman);

        $botman->listen();
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function fallbackMessage(Botman $botman): BotMan
    {
        $botman->fallback(function (BotMan $botman): void {
            /**
             * @phpstan-ignore-next-line
             * @psalm-suppress InvalidArgument
             */
            $botman->reply(ButtonTemplate::create("Sorry {$botman->getUser()->getFirstName()} i can't understand you💅")
                ->addButton(ElementButton::create('List Products')
                    ->type('postback')
                    ->payload('list_items')
                )
                ->addButton(ElementButton::create('Go to the website')
                    ->url($this->baseUrl)
                )
            );
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function listProducts(Botman $botman): BotMan
    {
        /** @var Pagerfanta $products */
        $products = $this->container->get('sylius.repository.product')->createPaginator();

        $botman->hears("list_items", function (BotMan $botman) use ($products): void {
            $elements = $this->wrapProducts($products->getCurrentPageResults(), $this->defaultLocaleCode, $this->defaultChannel);
            $botman->reply(GenericTemplate::create()
                ->addImageAspectRatio(GenericTemplate::RATIO_SQUARE)
                ->addElements($elements ?? []));
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function removeFromCart(Botman $botman): BotMan
    {
        $botman->hears('remove_item_from_cart', function(BotMan $botman, string $id): void {
            $botman->reply("i will removed item with id {$id} from your Cart");
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function addToCart(Botman $botman): BotMan
    {
        $botman->hears('add_to_cart', function(BotMan $botman): void {
            $botman->reply("i will add item with id to your Cart");
        });
        return $botman;
    }

    /**
     * @param BotMan $botman
     * @return BotMan
     */
    public function listItemsInCart(Botman $botman): BotMan
    {
        $botman->hears('mycart', function(BotMan $botman): void {
            $botman->reply("i will list items in your cart: {$botman->getUser()->getFirstName()}");
        });
        return $botman;
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return [
            'facebook' => [
                'token' => getenv('FACEBOOK_APP_TOKEN'),
                'app_secret' => getenv('FACEBOOK_APP_SECRET'),
                'verification'=> getenv('FACEBOOK_VERIFICATION'),
            ]
        ];
    }

    /**
     * @return Response
     */
    public function updatePresistentMenu(): Response
    {
        $access_token = getenv('FACEBOOK_APP_TOKEN');
        $curlRequest = <<<EOF
            curl -X POST -H "Content-Type: application/json" -d '{
              "setting_type" : "call_to_actions",
              "thread_state" : "existing_thread",
              "call_to_actions":[
                {
                  "type":"postback",
                  "title":"List Items",
                  "payload":"list_items"
                },
                {
                  "type":"postback",
                  "title":"My Cart",
                  "payload":"mycart"
                },
                {
                  "type":"web_url",
                  "title":"Visit my Website",
                  "url":"{$this->baseUrl}"
                }
              ]
            }' "https://graph.facebook.com/v2.6/me/thread_settings?access_token={$access_token}"
EOF;
        ;
        return new Response(exec($curlRequest));
    }
}
