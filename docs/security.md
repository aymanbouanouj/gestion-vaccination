# Security

## Confirmed controls

- Administrator authentication is required for operational routes.
- Session cookies are hardened with `HttpOnly`, `SameSite=Lax`, and conditional `Secure`.
- Idle and absolute session timeouts are enforced.
- Forced password change blocks business routes until completion.
- Login and logout use explicit request-method checks.
- CSRF tokens protect logout, password change, citizen creation, and centre lookup.
- Password changes require the current password and a confirmation match.
- Password policy requires:
  - at least 12 characters
  - at most 72 UTF-8 bytes
  - uppercase, lowercase, digit, and symbol
  - no leading or trailing whitespace
  - not equal to the current password
  - not equal to or containing the complete login
  - not matching a small placeholder-password pattern
- Failed login attempts are throttled through `authLoginAttempts`.
- Authentication events are recorded through `authSecurityEvents`.
- Pages avoid rendering raw SQL or exception messages directly.

## Confirmed route protection behavior

- Anonymous `GET` requests to protected pages redirect to the login page.
- Anonymous `POST` requests to protected write handlers return `403`.
- Logout remains POST-only.
- A forced-password-change administrator is redirected to `/pages/changerMotPasse.php` for business pages.

## Confirmed security test coverage

- Static checks for missing forbidden secret files
- HTTP checks for authentication, authorization, and CSRF handling
- Integration checks for throttling and event logging
- Unit checks for password policy and helper behavior

## Inferred from code

- Inferred from code comments: authentication events are designed to store keyed fingerprints rather than raw IP addresses.
- Inferred from release-preparation metadata capture: the live application user may have narrower privileges on security-event tables than on ordinary business tables.

## Unresolved limitations

- No dedicated private vulnerability intake channel is published in repository files.
- The repository does not claim production readiness or clinical suitability.
- The public release keeps the base path fixed at `/gestion_vaccination`.

## Optional recommendations

- Add a private vulnerability reporting path before accepting broad public contributions.
- Keep private config files outside the web root parent path already expected by the code.
- Use synthetic data only for demos, screenshots, and tests.
