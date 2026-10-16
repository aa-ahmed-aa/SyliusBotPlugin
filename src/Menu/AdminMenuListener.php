<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $bots = $event->getMenu()
            ->addChild('sylius_bot')
            ->setLabel('sylius_bot.menu.bots')
            ->setLabelAttribute('icon', 'tabler:message-chatbot')
        ;

        $bots
            ->addChild('sylius_bot_messenger', ['route' => 'sylius_bot_admin_bot_index', 'routeParameters' => ['channel' => 'messenger']])
            ->setLabel('sylius_bot.menu.messenger')
            ->setLabelAttribute('icon', 'tabler:brand-messenger')
        ;

        $bots
            ->addChild('sylius_bot_instagram', ['route' => 'sylius_bot_admin_bot_index', 'routeParameters' => ['channel' => 'instagram']])
            ->setLabel('sylius_bot.menu.instagram')
            ->setLabelAttribute('icon', 'tabler:brand-instagram')
        ;

        $bots
            ->addChild('sylius_bot_subscribers', ['route' => 'sylius_bot_admin_bot_subscriber_index'])
            ->setLabel('sylius_bot.menu.subscribers')
            ->setLabelAttribute('icon', 'tabler:users')
        ;
    }
}
