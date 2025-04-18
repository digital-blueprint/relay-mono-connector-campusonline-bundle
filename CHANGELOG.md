# Changelog

## Unreleased

## v0.3.1

* Drop support for PHP 8.1
* Remove dependency on api-platform

## v0.3.0

* breaking config changes: Renamed `payment_types` to `tuition_fees`

## v0.2.6

* Port to mono-bundle v0.5
* Drop support for Symfony 5
* Drop support for Psalm

## v0.2.5

* Remove reference to UserSession to get the current user identifier and use
PersonProvider::getCurrentPerson directly instead

## v0.2.4

* Fix compatibility with the latest CAMPUSonline release, resulting in authentication
  errors when trying to fetch tuition fees. Caused by the default Keycloak realm naming
  being changed in the latest CAMPUSonline release. This release works with the old
  and new realm naming.

## v0.2.3

* Add CLI command "dbp:relay:mono-connector-campusonline:list-tuition-fee" to list the tuition
  fees for a person in CAMPUSonline.
* Make sure to fail if the payment is larger than the tuition fee, even if such
  a case should never happen.

## v0.2.2

* Port to PHPUnit 10
* Remove unused ldap dependencies
* docs: Remove outdated LDAP information

## v0.2.1

* Add support for api-platform 3.2

## v0.2.0

* Replace LDAP Api by PersonProviderInterface (requires dbp/relay-base-person-bundle and a base-person-connector-bundle
the respective person subsystem to be installed (e.g. dbp/relay-base-person-connector-ldap-bundle) and the local
data attribute 'title' to be configured/provided
* Add unit tests for the TuitionFeeService

## v0.1.20

* Update to directorytree/ldaprecord v3.6

## v0.1.19

* Add support for Symfony 6

## v0.1.18

* dev: replace abandoned composer-git-hooks with captainhook.
  Run `vendor/bin/captainhook install -f` to replace the old hooks with the new ones
  on an existing checkout.

## v0.1.17

* Drop support for PHP 7.4/8.0

## v0.1.16

* Drop support for PHP 7.3

## v0.1.15

* Minor test cleanups

## v0.1.14

* Compatibility with mono-bundle v0.4

## v0.1.13

* Improve the health check to also fail in case the CO tuition fee API is working, but the CO backend connection is broken.

## v0.1.12

* Add a new "ldap_honorific_suffix_attribute" config option for optionally fetching a honorific suffix from LDAP
  and include it in the payment details.

## v0.1.11

* Update to api-platform 2.7

## v0.1.10

* Minor cleanups

## v0.1.9

* Better error handling in case CO is down

## v0.1.8

* Compatibility with mono-bundle v0.3

## v0.1.7

* Compatibility with mono-bundle v0.2

## v0.1.6

* Cleanup
* composer: add a pre-commit hook for linting

## v0.1.5

* logs: More and more detailed audit logs
* logs: Always add a relay-mono-payment-id to the audit logs

## v0.1.4

* improved test coverage

## v0.1.3

* Don't allow creating payments with an amount less then 1€ since the CO API
  doesn't allow registering a payment for <1€. This works around an issue where
  a payment would never be registered with CO after the payment went through.

## v0.1.0

* Initial release
