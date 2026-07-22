# Architecture

## Confirmed application style

- Confirmed: the project is a procedural PHP application.
- Confirmed: there is no framework router, ORM, or template engine in the repository.
- Confirmed: shared runtime code lives under `config/`.
- Confirmed: page entry points live under `pages/` plus `index.php`.
- Confirmed: shared frontend assets live under `assets/css/style.css` and `assets/js/app.js`.
- Confirmed: MariaDB access uses `mysqli`.

## Confirmed request entry points

| Path | Methods | Auth requirement | Purpose |
| --- | --- | --- | --- |
| `/gestion_vaccination/index.php` | `GET`, `HEAD` | Required | Dashboard |
| `/gestion_vaccination/pages/authentification.php` | `GET`, `HEAD`, `POST` | Public for login, authenticated for logout | Login and logout |
| `/gestion_vaccination/pages/changerMotPasse.php` | `GET`, `HEAD`, `POST` | Required, password-change flow allowed | Password replacement |
| `/gestion_vaccination/pages/formAjout.php` | `GET`, `HEAD` | Required | Citizen registration form |
| `/gestion_vaccination/pages/ajoutCitoyen.php` | `POST` | Required | Citizen creation handler |
| `/gestion_vaccination/pages/centreVaccination.php` | `GET`, `HEAD`, `POST` | Required | Assigned-centre lookup |
| `/gestion_vaccination/pages/performanceVaccinateur.php` | `GET`, `HEAD` | Required | Vaccinator activity report |

## Confirmed shared modules

- `config/security.php`
  - session startup
  - authentication state
  - CSRF tokens
  - password policy
  - login throttling helpers
  - authentication event logging
- `config/connexion.php`
  - private database config loading
  - `mysqli` connection bootstrap
  - strict SQL mode enforcement
- `config/application.php`
  - citizen validation helpers
  - flash-message helpers
- `config/reporting.php`
  - vaccinator activity query and summary helper
- `config/layout.php`
  - shared HTML shell
  - navigation
  - logout form
  - footer

## Confirmed frontend structure

- Confirmed: the project uses one shared stylesheet at `assets/css/style.css`.
- Confirmed: the project uses one shared JavaScript file at `assets/js/app.js`.
- Confirmed: JavaScript enhances the header and mobile drawer; it does not replace server-side validation.
- Confirmed: the HTML shell includes skip links, focus states, and responsive layout hooks.

## Confirmed data flow

1. Public login is submitted to `pages/authentification.php`.
2. Successful authentication stores the administrator identity in the session.
3. Protected pages call `requireAuthentication()`.
4. Forms send POST requests to dedicated handlers or self-posting routes.
5. Database mutations and lookups are executed directly with prepared `mysqli` statements.
6. User feedback is rendered after redirect or on the same page, depending on the route.

## Inferred design intent

- Inferred from code: the project favors a small number of explicit entry points over an abstract routing layer.
- Inferred from tests: business logic is intentionally narrow and centered on reproducible educational scenarios.
- Inferred from code comments: live authentication-event tables may be configured with narrower privileges than ordinary business tables.

## Unresolved limitations

- The public base path is hard-coded to `/gestion_vaccination`.
- The repository does not include a containerized deployment layout.
- The repository does not include a framework-style configuration loader or `.env` parser.
