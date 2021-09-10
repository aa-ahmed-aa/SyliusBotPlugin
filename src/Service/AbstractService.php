<?php


namespace Ahmedkhd\SyliusBotPlugin\Service;


use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractService
{
    /** @var ContainerInterface */
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }
}
