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
        $bots->addChild('messenger', ['route' => 'sylius_bot_plugin_admin_bot_index'])
            ->setLabel('Messenger')
            ->setLabelAttribute('icon', 'facebook')
        ;
    }
}
