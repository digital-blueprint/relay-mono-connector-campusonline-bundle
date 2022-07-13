<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Entry;
use LdapRecord\Models\OpenLDAP\User;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class LdapService implements LoggerAwareInterface, ServiceSubscriberInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    private $attributes = [
        'ldap_obfuscated_id_attribute' => 'obfuscatedId',
        'ldap_given_name_attribute' => 'givenName',
        'ldap_given_family_attribute' => 'familyName',
    ];

    /**
     * @var array
     */
    private $config;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $providerConfig;

    public function setConfig(array $config)
    {
        $this->config = $config;

        $this->providerConfig = [
            'hosts' => [$config['ldap_host'] ?? ''],
            'base_dn' => $config['ldap_base_dn'] ?? '',
            'username' => $config['ldap_username'] ?? '',
            'password' => $config['ldap_password'] ?? '',
        ];

        $encryption = $config['ldap_encryption'];
        assert(in_array($encryption, ['start_tls', 'simple_tls'], true));
        $this->providerConfig['use_tls'] = ($encryption === 'start_tls');
        $this->providerConfig['use_ssl'] = ($encryption === 'simple_tls');
        $this->providerConfig['port'] = ($encryption === 'start_tls') ? 389 : 636;

        $connection = new Connection($this->providerConfig);
        $connection->connect();
        Container::addConnection($connection);
    }

    public function getDataByIdentifier($identifier): LdapData
    {
        $data = new LdapData();

        $query = User::query();
        /** @var Entry $entry */
        $entry = $query->where('objectClass', '=', 'person')
            ->whereEquals($this->config['ldap_identifier_attribute'], $identifier)
            ->first();

        foreach ($this->attributes as $attributeKey => $propertyName) {
            if (array_key_exists($attributeKey, $this->config)) {
                $attribute = $this->config[$attributeKey];
                $data->{$propertyName} = $entry->getFirstAttribute($attribute);
            }
        }

        return $data;
    }

    public static function getSubscribedServices()
    {
        return [
            UserSessionInterface::class,
        ];
    }
}
