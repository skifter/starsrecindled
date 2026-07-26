<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle;

use Bellcom\StarsTurnBundle\Domain\DemoTurnEngine;
use Bellcom\StarsTurnBundle\Domain\TurnEngineInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class StarsTurnBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('engine_service')
                    ->defaultValue(DemoTurnEngine::class)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('mailer_from')
                    ->defaultValue('stars@example.net')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('frontend_base_url')
                    ->defaultValue('http://localhost:5173')
                    ->cannotBeEmpty()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import('../config/services.php');

        $builder->setParameter('stars_turn.mailer_from', $config['mailer_from']);
        $builder->setParameter('stars_turn.frontend_base_url', rtrim((string) $config['frontend_base_url'], '/'));
        $builder->setAlias(TurnEngineInterface::class, (string) $config['engine_service']);
    }
}
