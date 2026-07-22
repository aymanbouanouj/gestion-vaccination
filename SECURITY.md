# Security Policy

## Status labels used in this file

- Confirmed: directly verified from source files, tests, or local execution.
- Inferred from code: strongly suggested by source structure or comments, but not independently proven by a dedicated runtime check.
- Optional recommendation: useful hardening advice outside the current confirmed behavior.
- Unresolved limitation: a known gap that should not be described as solved.

## Supported release candidate

- Confirmed: this repository is being prepared for release candidate `v0.1.0`.
- Unresolved limitation: the project does not claim production readiness, clinical suitability, or government affiliation.

## Confirmed security controls

- Confirmed: administrator authentication is required for operational routes.
- Confirmed: a forced password replacement flow exists and blocks business routes until completion.
- Confirmed: logout is POST-only and protected by a CSRF token.
- Confirmed: password changes are CSRF-protected.
- Confirmed: session cookies are configured with `HttpOnly`, `SameSite=Lax`, and conditional `Secure`.
- Confirmed: idle and absolute session timeouts are enforced in `config/security.php`.
- Confirmed: login throttling is recorded and evaluated through `authLoginAttempts`.
- Confirmed: authentication events are written to `authSecurityEvents` using keyed fingerprints instead of raw IP addresses in application code.
- Confirmed: raw database error messages are not rendered directly in page responses.
- Confirmed: the public tree is expected to exclude live database dumps, private config, runtime artifacts, and protected credential-delivery files.

## Data handling rules for this public repository

- Confirmed: do not commit live SQL exports, private credentials, session files, cookies, CSRF tokens, or browser artifacts.
- Confirmed: do not commit real citizen or administrator records.
- Confirmed: public screenshots must be generated from isolated synthetic data only.
- Confirmed: the repository ships schema and optional synthetic demo data, not a live database snapshot.

## Reporting a vulnerability

- Unresolved limitation: no dedicated private security email address or published vulnerability intake workflow is declared in source-controlled files.
- Confirmed: do not report vulnerabilities by publishing secrets, live health data, or private configuration in public issues.
- Optional recommendation: enable a private reporting channel, such as GitHub private vulnerability reporting, before accepting external public traffic.

## Operational notes

- Confirmed: local testing uses a disposable `_test` database created by the repository test runner.
- Inferred from code: the live application database user may have narrower privileges than a schema-management user. During release preparation, live schema metadata was readable, but at least one security-event table denied direct row selection for digest capture.
