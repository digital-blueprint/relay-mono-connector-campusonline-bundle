# Configuration

## Bundle Configuration

```yaml
dbp_relay_mono_connector_campusonline:
    payment_types:        # Required
        # Prototype
        -
            api_url:              ~ # Required
            client_id:            ~ # Required
            client_secret:        ~ # Required
            ldap_host:            ~ # Required
            ldap_base_dn:         ~ # Required
            ldap_username:        ~ # Required
            ldap_password:        ~ # Required
            # simple_tls uses port 636 and is sometimes referred to as "SSL", start_tls uses port 389 and is sometimes referred to as "TLS"
            ldap_encryption:      start_tls # One of "start_tls"; "simple_tls"
            ldap_identifier_attribute: ~ # Required
            ldap_obfuscated_id_attribute: ~ # Required
            ldap_given_name_attribute: ~
            ldap_family_name_attribute: ~
```

Example configuration:

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
