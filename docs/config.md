# Configuration

## Bundle Configuration

Created via `./bin/console config:dump-reference dbp_relay_mono_connector_campusonline | sed '/^$/d'`

```yaml
dbp_relay_mono_connector_campusonline:
  # Zero or more tuition fee connections. The "backend_type" can be referenced in the main "mono" config.
  tuition_fees:
    # Prototype
    backend_type:
      # The base API URL for a CAMPUSonline instance
      api_url:              ~ # Required, Example: 'https://online.mycampus.org/campus_online'
      # The OAuth2 client ID. The client needs to have access to the "tuinx" API.
      client_id:            ~ # Required, Example: my-client
      # The OAuth2 client secret
      client_secret:        ~ # Required, Example: my-secret
```

Example configuration:

```yaml
dbp_relay_mono_connector_campusonline:
  tuition_fees:
    tuition_fee_co:
      api_url: '%env(resolve:MONO_CONNECTOR_CAMPUSONLINE_API_URL)%'
      client_id: '%env(MONO_CONNECTOR_CAMPUSONLINE_CLIENT_ID)%'
      client_secret: '%env(MONO_CONNECTOR_CAMPUSONLINE_CLIENT_SECRET)%'
```

## CLI Commands

For debugging purposes there exists a command for inspecting the tuition fee status for students:

```console
$ ./bin/console dbp:relay:mono-connector-campusonline:list-tuition-fees --help
Description:
  List tuition fees for a student

Usage:
  dbp:relay:mono-connector-campusonline:list-tuition-fees <type> <obfuscated-id>

Arguments:
  type                  type
  obfuscated-id         obfuscated id
```

## Translations

For the tuition fee there exist two translatable strings for the payment title:

`./bin/console debug:translation de --domain dbp_relay_mono_connector_campusonline`

```
 ------- --------------------------------------- --------------------------------------- ------------------------------------------ ------------------------------------------ 
  State   Domain                                  Id                                      Message Preview (de)                       Fallback Message Preview (en)             
 ------- --------------------------------------- --------------------------------------- ------------------------------------------ ------------------------------------------ 
          dbp_relay_mono_connector_campusonline   tuition_fee.payment_title               Studienbeitrag ({semesterKey}) für {f...   Tuition fee ({semesterKey}) for {fami...  
          dbp_relay_mono_connector_campusonline   tuition_fee.payment_title_with_suffix   Studienbeitrag ({semesterKey}) für {f...   Tuition fee ({semesterKey}) for {fami...  
 ------- --------------------------------------- --------------------------------------- ------------------------------------------ ------------------------------------------ 
```

These can be overridden, or extended for other locales.
