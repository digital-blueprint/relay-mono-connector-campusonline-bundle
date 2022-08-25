<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use LdapRecord\Connection;
use LdapRecord\Models\OpenLDAP\User;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class LdapService extends AbstractPaymentTypesService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    private $attributes = [
        'ldap_obfuscated_id_attribute' => 'obfuscatedId',
        'ldap_given_name_attribute' => 'givenName',
        'ldap_family_name_attribute' => 'familyName',
    ];

    private function getLdapConfig(array $config): array
    {
        $providerConfig = [
            'hosts' => [$config['ldap_host'] ?? ''],
            'base_dn' => $config['ldap_base_dn'] ?? '',
            'username' => $config['ldap_username'] ?? '',
            'password' => $config['ldap_password'] ?? '',
        ];

        $encryption = $config['ldap_encryption'];
        assert(in_array($encryption, ['start_tls', 'simple_tls'], true));
        $providerConfig['use_tls'] = ($encryption === 'start_tls');
        $providerConfig['use_ssl'] = ($encryption === 'simple_tls');
        $providerConfig['port'] = ($encryption === 'start_tls') ? 389 : 636;

        return $providerConfig;
    }

    public function checkConnection(): void
    {
        foreach ($this->getTypes() as $type) {
            $typeConfig = $this->getConfigByType($type);
            $connection = new Connection($this->getLdapConfig($typeConfig));
            $connection->connect();
        }
    }

    public function getDataByIdentifier(string $type, string $identifier): LdapData
    {
        $typeConfig = $this->getConfigByType($type);
        $connection = new Connection($this->getLdapConfig($typeConfig));
        $connection->connect();

        $entry = $connection->query()->model(new User())->where('objectClass', '=', 'person')
            ->whereEquals($typeConfig['ldap_identifier_attribute'], $identifier)
            ->first();

        $data = new LdapData();
        foreach ($this->attributes as $attributeKey => $propertyName) {
            if (array_key_exists($attributeKey, $typeConfig)) {
                $attribute = $typeConfig[$attributeKey];
                $data->{$propertyName} = $entry->getFirstAttribute($attribute);
            }
        }

        return $data;
    }
}
