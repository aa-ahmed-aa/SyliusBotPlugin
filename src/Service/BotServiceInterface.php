<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Sylius\Component\Resource\Model\ResourceInterface;

interface BotServiceInterface
{
    /**
     * @param ResourceInterface $items
     * @param bool $forCart
     * @return mixed
     */
    public function wrapProducts(ResourceInterface $items, bool $forCart = false);
}
