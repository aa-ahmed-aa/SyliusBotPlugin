<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;

use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BotService extends AbstractService implements BotServiceInterface
{
    /**
     * BotService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    /**
     * @param ResourceInterface $items
     * @param bool $forCart
     * @return mixed|ResourceInterface
     */
    public function wrapProducts(ResourceInterface $items, bool $forCart = false)
    {
        return $items;
    }
}
