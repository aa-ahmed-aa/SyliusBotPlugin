<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\FacebookDriver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FacebookMessengerService
 * @package Ahmedkhd\SyliusBotPlugin\Service
 */
class FacebookMessengerService extends BotService
{
    public function __construct($container)
    {
        parent::__construct($container);
    }

    public function flow()
    {
        //create menu
        $this->updatePresistentMenu();

        DriverManager::loadDriver(FacebookDriver::class);

        $botman = BotManFactory::create($this->getConfiguration());

        $botman->fallback("Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService@fallbackMessage");
        $botman->hears('list_items', "Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService@listProducts");
        $botman->hears('remove_from_cart {id}', "Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService@removeFromCart");
        $botman->hears('add_to_cart {id}', "Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService@addToCart");
        $botman->hears('mycart', "Ahmedkhd\SyliusBotPlugin\Service\FacebookMessengerService@listItemsInCart");

        $botman->listen();
    }

    /**
     * @param $items
     * @param bool $forCart
     * @return array
     */
    public function wrapProducts($items, $forCart = false)
    {
        return $items;
    }

    /**
     * @param BotMan $bot
     */
    public function fallbackMessage(BotMan $bot)
    {
        $bot->reply(ButtonTemplate::create('Sorry ' . $bot->getUser()->getFirstName() .' i can\'t understand you💅')
            ->addButton(ElementButton::create('List Products')
                ->type('postback')
                ->payload('list_items')
            )
            ->addButton(ElementButton::create('Go to the website')
                ->url(getenv('APP_URL'))
            )
        );
    }

    public function listProducts(BotMan $bot)
    {
        $bot->reply('i will list items for you my love');
    }

    public function removeFromCart(BotMan $bot)
    {
        $bot->reply("i will removed item with id {$id} from your Cart");
    }

    public function addToCart(BotMan $bot,string $id)
    {
        $bot->reply("i will add item with id {$id} to your Cart");
    }

    public function listItemsInCart(BotMan $bot)
    {
        $bot->reply("i will list items in your cart: {$bot->getUser()->getFirstName()}");
    }


    public function getConfiguration()
    {
        return [
            'facebook' => [
                'token' => getenv('FACEBOOK_APP_TOKEN'),
                'app_secret' => getenv('FACEBOOK_APP_SECRET'),
                'verification'=> getenv('FACEBOOK_VERIFICATION'),
            ]
        ];
    }

    public function updatePresistentMenu()
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
                  "url":"http://enigmatic-mesa-24739.herokuapp.com"
                }
              ]
            }' "https://graph.facebook.com/v2.6/me/thread_settings?access_token={$access_token}"
EOF;
        ;
        return new Response(exec($curlRequest));
    }
}
