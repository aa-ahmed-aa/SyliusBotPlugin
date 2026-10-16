<?php

declare(strict_types=1);

use SyliusBotPlugin\Bot\BotChannelResolver;
use SyliusBotPlugin\Bot\CartManager;
use SyliusBotPlugin\Bot\ConversationFactory;
use SyliusBotPlugin\Bot\ConversationHandler;
use SyliusBotPlugin\Bot\ConversationSender;
use SyliusBotPlugin\Bot\IncomingMessageParser;
use SyliusBotPlugin\Bot\MessageFactory;
use SyliusBotPlugin\Bot\ProductCardBuilder;
use SyliusBotPlugin\Bot\ProductCatalog;
use SyliusBotPlugin\Bot\Reply\NullReplyGenerator;
use SyliusBotPlugin\Bot\Reply\ReplyGeneratorInterface;
use SyliusBotPlugin\Bot\SendApi;
use SyliusBotPlugin\Bot\StoreUrlGenerator;
use SyliusBotPlugin\Bot\WebhookSignatureVerifier;
use SyliusBotPlugin\Connection\ChannelConnector;
use SyliusBotPlugin\Connection\ProfileConfigurator;
use SyliusBotPlugin\Controller\Admin\BotController;
use SyliusBotPlugin\Controller\Admin\BotSubscriberController;
use SyliusBotPlugin\Controller\Shop\CheckoutController;
use SyliusBotPlugin\Controller\Shop\WebhookController;
use SyliusBotPlugin\Facebook\FacebookLoginManager;
use SyliusBotPlugin\Facebook\ManagedPageProvider;
use SyliusBotPlugin\Form\BotSettingsType;
use SyliusBotPlugin\Graph\GraphClient;
use SyliusBotPlugin\Graph\GraphClientInterface;
use SyliusBotPlugin\Menu\AdminMenuListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()->private();

    // Graph API & Facebook login

    $services->set('sylius_bot.graph_client', GraphClient::class)
        ->args([
            service('http_client'),
            param('sylius_bot.facebook.app_id'),
            param('sylius_bot.facebook.app_secret'),
            param('sylius_bot.facebook.graph_version'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'sylius_bot']);
    $services->alias(GraphClientInterface::class, 'sylius_bot.graph_client');

    $services->set('sylius_bot.facebook.login_manager', FacebookLoginManager::class)
        ->args([
            service('sylius_bot.graph_client'),
            service('request_stack'),
            param('sylius_bot.facebook.app_secret'),
            param('sylius_bot.facebook.messenger_scopes'),
            param('sylius_bot.facebook.instagram_scopes'),
        ]);

    $services->set('sylius_bot.facebook.managed_page_provider', ManagedPageProvider::class)
        ->args([service('sylius_bot.graph_client')]);

    // Conversation

    $services->set('sylius_bot.message_factory', MessageFactory::class);

    $services->set('sylius_bot.incoming_message_parser', IncomingMessageParser::class);

    $services->set('sylius_bot.webhook_signature_verifier', WebhookSignatureVerifier::class)
        ->args([param('sylius_bot.facebook.app_secret'), param('sylius_bot.facebook.verify_signature')]);

    $services->set('sylius_bot.send_api', SendApi::class)
        ->args([service('sylius_bot.graph_client'), service('logger')])
        ->tag('monolog.logger', ['channel' => 'sylius_bot']);

    $services->set('sylius_bot.conversation_sender', ConversationSender::class)
        ->args([
            service('sylius_bot.send_api'),
            service('sylius_bot.factory.bot_message'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_bot.channel_resolver', BotChannelResolver::class)
        ->args([service('sylius.repository.channel')]);

    $services->set('sylius_bot.store_url_generator', StoreUrlGenerator::class)
        ->args([service('router'), service('request_stack')]);

    $services->set('sylius_bot.product_catalog', ProductCatalog::class)
        ->args([service('sylius.repository.product'), param('sylius_bot.products_per_page')]);

    $services->set('sylius_bot.product_card_builder', ProductCardBuilder::class)
        ->args([
            service('sylius_bot.message_factory'),
            service('sylius_bot.product_catalog'),
            service('sylius_bot.store_url_generator'),
            service('Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface'),
            service('liip_imagine.cache.manager'),
            service('translator'),
        ]);

    $services->set('sylius_bot.cart_manager', CartManager::class)
        ->args([
            service('sylius_bot.product_catalog'),
            service('sylius.factory.order_item'),
            service('Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface'),
            service('Sylius\Component\Order\Modifier\OrderModifierInterface'),
            service('sylius.order_processing.order_processor'),
            service('Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_bot.conversation_factory', ConversationFactory::class)
        ->args([
            service('sylius_bot.repository.bot'),
            service('sylius_bot.repository.bot_subscriber'),
            service('sylius_bot.factory.bot_subscriber'),
            service('sylius.repository.customer'),
            service('sylius.factory.customer'),
            service('sylius_bot.channel_resolver'),
            service('sylius.repository.order'),
            service('sylius.factory.order'),
            service('Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface'),
            service('sylius_bot.graph_client'),
            service('doctrine.orm.entity_manager'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'sylius_bot']);

    // Replace this service (or alias ReplyGeneratorInterface) to answer free text, e.g. with an LLM.
    $services->set('sylius_bot.reply_generator', NullReplyGenerator::class);
    $services->alias(ReplyGeneratorInterface::class, 'sylius_bot.reply_generator');

    $services->set('sylius_bot.conversation_handler', ConversationHandler::class)
        ->args([
            service('sylius_bot.conversation_factory'),
            service('sylius_bot.conversation_sender'),
            service('sylius_bot.message_factory'),
            service('sylius_bot.product_catalog'),
            service('sylius_bot.product_card_builder'),
            service('sylius_bot.cart_manager'),
            service('sylius_bot.store_url_generator'),
            service('sylius_bot.reply_generator'),
            service('Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface'),
            service('translator'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'sylius_bot']);

    // Connecting pages

    $services->set('sylius_bot.profile_configurator', ProfileConfigurator::class)
        ->args([
            service('sylius_bot.graph_client'),
            service('sylius_bot.channel_resolver'),
            service('sylius_bot.store_url_generator'),
            service('translator'),
        ]);

    $services->set('sylius_bot.channel_connector', ChannelConnector::class)
        ->args([
            service('sylius_bot.repository.bot'),
            service('sylius_bot.factory.bot'),
            service('sylius_bot.graph_client'),
            service('sylius_bot.profile_configurator'),
            service('doctrine.orm.entity_manager'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'sylius_bot']);

    // Controllers

    $services->set(WebhookController::class)
        ->public()
        ->args([
            service('sylius_bot.incoming_message_parser'),
            service('sylius_bot.conversation_handler'),
            service('sylius_bot.webhook_signature_verifier'),
            param('sylius_bot.facebook.verify_token'),
            service('logger'),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'sylius_bot']);

    $services->set(CheckoutController::class)
        ->public()
        ->args([
            service('sylius.repository.order'),
            service('Sylius\Component\Core\Storage\CartStorageInterface'),
            service('sylius.context.channel'),
            service('router'),
        ])
        ->tag('controller.service_arguments');

    $services->set(BotController::class)
        ->public()
        ->autowire()
        ->autoconfigure()
        ->args([
            service('sylius_bot.repository.bot'),
            service('sylius_bot.graph_client'),
            service('sylius_bot.facebook.login_manager'),
            service('sylius_bot.facebook.managed_page_provider'),
            service('sylius_bot.channel_connector'),
            service('sylius_bot.profile_configurator'),
        ]);

    $services->set(BotSubscriberController::class)
        ->public()
        ->autowire()
        ->autoconfigure()
        ->args([
            service('sylius_bot.repository.bot_subscriber'),
            service('sylius_bot.repository.bot_message'),
            service('sylius.repository.order'),
        ]);

    // Admin

    $services->set('sylius_bot.form.type.bot_settings', BotSettingsType::class)
        ->args([service('sylius.repository.channel')])
        ->tag('form.type');

    $services->set('sylius_bot.menu.admin_listener', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main']);
};
