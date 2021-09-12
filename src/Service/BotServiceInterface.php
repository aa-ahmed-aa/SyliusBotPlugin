<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface BotServiceInterface
{
    /**
     * @param iterable $products
     * @param string $localeCode
     * @param ChannelInterface $channel
     * @return array
     */
    public function wrapProducts(iterable $products,string $localeCode, ChannelInterface $channel): array;
}
