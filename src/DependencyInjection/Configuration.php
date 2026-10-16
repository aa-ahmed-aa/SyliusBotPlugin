<?php

declare(strict_types=1);

namespace SyliusBotPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_GRAPH_VERSION = 'v23.0';

    /** Permissions asked for when connecting Facebook Pages (Messenger). */
    public const DEFAULT_MESSENGER_SCOPES = [
        'pages_show_list',
        'pages_messaging',
        'pages_manage_metadata',
    ];

    /** Permissions asked for when connecting Instagram professional accounts. */
    public const DEFAULT_INSTAGRAM_SCOPES = [
        'pages_show_list',
        'pages_manage_metadata',
        'pages_read_engagement',
        'instagram_basic',
        'instagram_manage_messages',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_bot');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('facebook')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('app_id')->defaultValue('')->end()
                        ->scalarNode('app_secret')->defaultValue('')->end()
                        ->scalarNode('graph_version')->defaultValue(self::DEFAULT_GRAPH_VERSION)->end()
                        ->scalarNode('verify_token')->defaultValue('')->end()
                        ->booleanNode('verify_signature')
                            ->info('Reject webhook calls whose X-Hub-Signature-256 does not match the app secret.')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('messenger_scopes')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::DEFAULT_MESSENGER_SCOPES)
                        ->end()
                        ->arrayNode('instagram_scopes')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::DEFAULT_INSTAGRAM_SCOPES)
                        ->end()
                    ->end()
                ->end()
                ->integerNode('products_per_page')
                    ->info('Products per carousel. Meta caps a carousel at 10 cards; one is kept for "See more".')
                    ->defaultValue(9)
                    ->min(1)
                    ->max(9)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
