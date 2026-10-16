<?php

declare(strict_types=1);

namespace SyliusBotPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SyliusBotExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sylius_bot.facebook.app_id', $config['facebook']['app_id']);
        $container->setParameter('sylius_bot.facebook.app_secret', $config['facebook']['app_secret']);
        $container->setParameter('sylius_bot.facebook.graph_version', $config['facebook']['graph_version']);
        $container->setParameter('sylius_bot.facebook.verify_token', $config['facebook']['verify_token']);
        $container->setParameter('sylius_bot.facebook.verify_signature', $config['facebook']['verify_signature']);
        $container->setParameter('sylius_bot.facebook.messenger_scopes', $config['facebook']['messenger_scopes']);
        $container->setParameter('sylius_bot.facebook.instagram_scopes', $config['facebook']['instagram_scopes']);
        $container->setParameter('sylius_bot.products_per_page', $config['products_per_page']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SyliusBotPlugin' => [
                        'type' => 'xml',
                        'dir' => \dirname(__DIR__, 2) . '/config/doctrine',
                        'prefix' => 'SyliusBotPlugin\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $this->prependDoctrineMigrations($container);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'SyliusBotPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@SyliusBotPlugin/src/Migrations';
    }

    /** @return string[] */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
