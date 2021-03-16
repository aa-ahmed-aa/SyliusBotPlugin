<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusBotPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ahmedkhd_sylius_bot_plugin');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
