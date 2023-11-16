<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Tests;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dbp\Relay\CoreBundle\DbpRelayCoreBundle;
use Dbp\Relay\MonoBundle\DbpRelayMonoBundle;
use Dbp\Relay\MonoConnectorCampusonlineBundle\DbpRelayMonoConnectorCampusonlineBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Nelmio\CorsBundle\NelmioCorsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new NelmioCorsBundle();
        yield new MonologBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new ApiPlatformBundle();
        yield new DbpRelayMonoBundle();
        yield new DbpRelayMonoConnectorCampusonlineBundle();
        yield new DbpRelayCoreBundle();
    }

    protected function configureRoutes(RoutingConfigurator $routes)
    {
        $routes->import('@DbpRelayCoreBundle/Resources/config/routing.yaml');
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader)
    {
        $container->import('@DbpRelayCoreBundle/Resources/config/services_test.yaml');
        $container->extension('framework', [
            'test' => true,
            'secret' => '',
        ]);

        $container->extension('dbp_relay_mono', [
            'database_url' => 'mysql://dummy:dummy@dummy?serverVersion=mariadb-10.3.30',
            'cleanup' => [
                [
                    'payment_status' => 'ada',
                    'timeout_before' => '123',
                ],
            ],
            'payment_session_timeout' => 1234,
            'payment_types' => [
                [
                    'service' => 'bla',
                    'payment_contracts' => [
                        [
                            'service' => 'bla',
                            'payment_methods' => [
                                [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $container->extension('dbp_relay_mono_connector_campusonline', [
            'payment_types' => [
                'foobar' => [
                    'api_url' => '',
                    'client_id' => '',
                    'client_secret' => '',
                    'ldap_host' => '',
                    'ldap_base_dn' => '',
                    'ldap_username' => '',
                    'ldap_password' => '',
                    'ldap_identifier_attribute' => '',
                    'ldap_obfuscated_id_attribute' => '',
                ],
            ],
        ]);

        $container->extension('api_platform', [
            'metadata_backward_compatibility_layer' => false,
        ]);
    }
}
