<?php

declare(strict_types=1);

namespace SyliusBotPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_bot_plugin');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
