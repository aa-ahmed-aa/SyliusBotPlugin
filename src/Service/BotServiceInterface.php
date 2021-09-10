<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


interface BotServiceInterface
{
    /**
     * @param $items
     * @param bool $forCart
     * @return mixed
     */
    public function wrapProducts($items, $forCart = false);
}
