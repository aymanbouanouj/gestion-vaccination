# Project History

## Confirmed from repository contents

- The application is a local educational prototype built with procedural PHP and MariaDB.
- The current functional scope includes administrator authentication, forced password replacement, password change, citizen registration, assigned-centre lookup, and vaccinator activity reporting.
- The repository contains a permanent isolated PHPUnit suite and a GitHub Actions workflow.
- A schema hardening migration dated `20260720_01` is present under `database/migrations/`.

## Inferred from code and file layout

- Inferred from architecture: the project started as a small local web application with direct PHP entry points rather than a framework structure.
- Inferred from test support code: isolated database rebuilds and HTTP smoke coverage were added after the original application routes already existed.
- Inferred from branding assets and shared layout code: the interface was later consolidated around a shared header, footer, and visual identity pack.

## Confirmed release-preparation work for v0.1.0

- Public documentation was added in English for the July 22, 2026 release-preparation pass.
- A public schema-only SQL bootstrap was added.
- Optional synthetic demonstration data was added.
- A public administrator-creation helper was added.
- Safe screenshots were generated from isolated synthetic data.
- Secret and publication scanning rules were tightened in repository metadata files.
