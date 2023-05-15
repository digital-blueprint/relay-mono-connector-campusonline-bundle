# v0.1.11

* Update to api-platform 2.7

# v0.1.10

* Minor cleanups

# v0.1.9

* Better error handling in case CO is down

# v0.1.8

* Compatibility with mono-bundle v0.3

# v0.1.7

* Compatibility with mono-bundle v0.2

# v0.1.6

* Cleanup
* composer: add a pre-commit hook for linting

# v0.1.5

* logs: More and more detailed audit logs
* logs: Always add a relay-mono-payment-id to the audit logs

# v0.1.4

* improved test coverage

# v0.1.3

* Don't allow creating payments with an amount less then 1€ since the CO API
  doesn't allow registering a payment for <1€. This works around an issue where
  a payment would never be registered with CO after the payment went through.

# v0.1.0

* Initial release
