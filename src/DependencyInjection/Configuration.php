<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_mono_connector_campusonline');

        $treeBuilder
            ->getRootNode()
                ->children()
                    ->arrayNode('payment_types')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('api_url')
                                ->end()
                                ->scalarNode('client_id')
                                ->end()
                                ->scalarNode('client_secret')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
