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
