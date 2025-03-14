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
                    ->arrayNode('tuition_fees')
                        ->info('Zero or more tuition fee connections. The "backend_type" can be referenced in the main "mono" config.')
                        ->defaultValue([])
                        ->useAttributeAsKey('backend_type')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('api_url')
                                    ->info('The base API URL for a CAMPUSonline instance')
                                    ->example('https://online.mycampus.org/campus_online')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('client_id')
                                    ->info('The OAuth2 client ID. The client needs to have access to the "tuinx" API.')
                                    ->example('my-client')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('client_secret')
                                    ->info('The OAuth2 client secret')
                                    ->example('my-secret')
                                    ->isRequired()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
