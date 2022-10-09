<?php

namespace SyliusBotPlugin\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractService
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * AbstractBotService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
}