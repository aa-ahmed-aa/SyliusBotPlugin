<?php


namespace Ahmedkhd\SyliusBotPlugin\Menu;


use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class MenuBuilderListener
{
    /**
     * @param MenuBuilderEvent $event
     */
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $bots = $menu->addChild('Bots');
        $bots->addChild('messenger', ['route' => 'ahmedkhd_facebook_connect'])
            ->setLabel('Messenger')
            ->setLabelAttribute('icon', 'facebook')
        ;
        $bots->addChild('bot_users', ['route' => 'sylius_bot_plugin_admin_bot_subscriber_index'])
            ->setLabel('sylius_bot_plugin.bot_subscriber.users')
            ->setLabelAttribute('icon', 'podcast')
        ;
    }
}
