# Contributing

## Scope status

- Confirmed: this repository contains a local educational PHP and MariaDB prototype.
- Confirmed: the public documentation language is English.
- Confirmed: the application interface language remains French.
- Confirmed: contributions must not add patient accounts, appointment booking, vaccine stock management, certificates, maps, or other features that are not already implemented.

## Before you open a change

- Confirmed: install Composer dependencies with `composer install`.
- Confirmed: run the permanent isolated test suite with `composer test`.
- Confirmed: keep all demo and fixture data synthetic.
- Confirmed: do not add live SQL dumps, real citizen data, real administrator data, session artifacts, or private configuration files.

## Coding expectations

- Confirmed: the project is procedural PHP, not a framework application.
- Confirmed: shared behavior lives under `config/`, page entry points live under `pages/`, and shared assets live under `assets/`.
- Confirmed: keep route paths and request methods stable unless the change is intentionally updating the application contract and tests together.
- Confirmed: logout must remain a POST form protected by CSRF.
- Confirmed: forced password change behavior must remain intact.

## Documentation expectations

- Confirmed: public repository documentation should stay in English.
- Confirmed: UI labels and user-facing application copy stay in French unless the application itself is intentionally translated.
- Confirmed: every operational command, path, and limitation in documentation should be traceable to source files or verified local execution.
- Optional recommendation: when a statement is partly inferred from code rather than directly asserted by a test or command, label it clearly.

## Test expectations

- Confirmed: use the existing grouped test runner commands:
  - `composer test`
  - `composer test:unit`
  - `composer test:integration`
  - `composer test:http`
  - `composer test:security`
- Confirmed: the test suite creates and destroys an isolated MariaDB database whose name must end with `_test`.
- Confirmed: do not point the test runner at the live `compagnevaccination` database.

## Data and privacy rules

- Confirmed: demo data must be authored from scratch.
- Confirmed: do not anonymize or reshape live records into repository fixtures.
- Confirmed: do not commit `vendor/`, private configs, browser artifacts, runtime caches, dumps, or exports.
- Unresolved limitation: this repository does not publish a dedicated maintainer contact channel in source-controlled files.
