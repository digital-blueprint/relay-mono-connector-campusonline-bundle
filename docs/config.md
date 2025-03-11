# Configuration

## Bundle Configuration

Created via `./bin/console config:dump-reference dbp_relay_mono_connector_campusonline | sed '/^$/d'`

```yaml
dbp_relay_mono_connector_campusonline:
    payment_types:        # Required
        # Prototype
        -
            api_url:              ~ # Required
            client_id:            ~ # Required
            client_secret:        ~ # Required
```

Example configuration:

```yaml
dbp_relay_mono_connector_campusonline:
  payment_types:
    tuition_fee:
      api_url: '%env(resolve:MONO_CONNECTOR_CAMPUSONLINE_API_URL)%'
      client_id: '%env(MONO_CONNECTOR_CAMPUSONLINE_CLIENT_ID)%'
      client_secret: '%env(MONO_CONNECTOR_CAMPUSONLINE_CLIENT_SECRET)%'
```

## Translations:

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
