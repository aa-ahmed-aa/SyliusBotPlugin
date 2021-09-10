<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


abstract class BotService extends AbstractService implements BotServiceInterface
{
    public function __construct($container)
    {
        parent::__construct($container);
    }

    /**
     * @inheritDoc
     */
    public function wrapProducts($items, $forCart = false)
    {
        return $items;
    }
}
