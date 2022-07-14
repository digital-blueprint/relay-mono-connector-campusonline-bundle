# DbpRelayMonoConnectorCampusonlineBundle

[GitLab](https://gitlab.tugraz.at/dbp/relay/dbp-relay-mono-connector-campusonline-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-mono-connector-campusonline-bundle)

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-mono-connector-campusonline-bundle).

```bash
composer require dbp/relay-mono-connector-campusonline-bundle
```

## Integration into the API Server

* Add the necessary bundles to your `config/bundles.php`:

```php
...
Dbp\Relay\MonoBundle\DbpRelayMonoBundle::class => ['all' => true],
Dbp\Relay\MonoConnectorCampusonlineBundle\DbpRelayMonoConnectorCampusonlineBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

* Run `composer install` to clear caches

## Configuration

For this create `config/packages/dbp_relay_mono_connector_campusonline.yaml` in the app with the following
content:

```yaml
dbp_relay_mono_connector_campusonline:
  payment_types:
    tuition_fee:
      api_url: '%env(resolve:MONO_CONNECTOR_CAMPUSONLINE_API_URL)%'
      client_id: '%env(MONO_CONNECTOR_CAMPUSONLINE_CLIENT_ID)%'
      client_secret: '%env(MONO_CONNECTOR_CAMPUSONLINE_CLIENT_SECRET)%'
      ldap_host: '%env(MONO_CONNECTOR_CAMPUSONLINE_LDAP_HOST)%'
      ldap_base_dn: '%env(MONO_CONNECTOR_CAMPUSONLINE_LDAP_BASE_DN)%'
      ldap_username: '%env(MONO_CONNECTOR_CAMPUSONLINE_LDAP_USERNAME)%'
      ldap_password: '%env(MONO_CONNECTOR_CAMPUSONLINE_LDAP_PASSWORD)%'
      ldap_encryption: 'simple_tls'
      ldap_identifier_attribute: 'cn'
      ldap_obfuscated_id_attribute: 'CO-OBFUSCATED-C-IDENT'
      ldap_given_name_attribute: 'givenName'
      ldap_family_name_attribute: 'sn'
```

For more info on bundle configuration see [Symfony bundles configuration](https://symfony.com/doc/current/bundles/configuration.html).

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies from dbp/relay-mono-connector-campusonline-bundle
composer update dbp/relay-mono-connector-campusonline-bundle
```
