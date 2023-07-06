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
                                    ->isRequired()
                                ->end()
                                ->scalarNode('client_id')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('client_secret')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('ldap_host')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('ldap_base_dn')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('ldap_username')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('ldap_password')
                                    ->isRequired()
                                ->end()
                                ->enumNode('ldap_encryption')
                                    ->info('simple_tls uses port 636 and is sometimes referred to as "SSL", start_tls uses port 389 and is sometimes referred to as "TLS"')
                                    ->values(['start_tls', 'simple_tls'])
                                    ->defaultValue('start_tls')
                                ->end()
                                ->scalarNode('ldap_identifier_attribute')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('ldap_obfuscated_id_attribute')
                                    ->isRequired()
                                ->end()
                                ->scalarNode('ldap_given_name_attribute')
                                ->end()
                                ->scalarNode('ldap_family_name_attribute')
                                ->end()
                                ->scalarNode('ldap_honorific_suffix_attribute')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
