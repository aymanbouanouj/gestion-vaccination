# Testing

## Confirmed test stack

- PHPUnit is installed through Composer.
- The repository ships a dedicated runner at `tests/runner.php`.
- The default PHPUnit configuration is `phpunit.xml.dist`.
- Test code lives under:
  - `tests/Unit`
  - `tests/Integration`
  - `tests/Http`
  - `tests/Security`

## Confirmed safety guarantees

The permanent test suite:

- does not use the live `compagnevaccination` database when executed through the repository runner
- requires a disposable test database whose name ends with `_test`
- creates temporary runtime files outside the project
- creates synthetic private config files for the test runtime
- loads only synthetic SQL fixtures
- starts a temporary local PHP server only for HTTP tests
- removes the temporary runtime and temporary database artifacts after execution by default

## Confirmed commands

Install dependencies:

```powershell
composer install
```

Run the full suite:

```powershell
composer test
```

Run individual groups:

```powershell
composer test:unit
composer test:integration
composer test:http
composer test:security
```

## Confirmed environment variables used by the test runner

- `GV_TEST_DB_HOST`
- `GV_TEST_DB_ADMIN_HOST`
- `GV_TEST_DB_ADMIN_PORT`
- `GV_TEST_DB_ADMIN_USER`
- `GV_TEST_DB_ADMIN_PASSWORD`
- `GV_TEST_DB_NAME`
- `GV_TEST_KEEP_DATABASE`

## Confirmed default local assumptions

- test database host: `127.0.0.1`
- admin database host: `127.0.0.1`
- admin database port: `3306`
- admin database user: `root`
- admin database password: empty string
- default test database name: `compagnevaccination_test`

## Confirmed runner workflow

When `composer test` runs through `tests/runner.php`, it:

1. creates a temporary runtime outside the project
2. generates temporary database and security config files
3. validates the `_test` database-name guard
4. rebuilds the isolated database
5. applies `tests/fixtures/sql/001_base_schema.sql`
6. applies `database/migrations/20260720_01_database_integrity_hardening.sql`
7. applies `tests/fixtures/sql/010_seed_reference_data.sql`
8. seeds synthetic administrators in code
9. starts a temporary PHP server for HTTP groups
10. runs PHPUnit
11. stops the temporary server
12. drops temporary SQL users and the temporary database unless `GV_TEST_KEEP_DATABASE=1`
13. removes the temporary runtime directory

## Confirmed CI workflow

- The GitHub Actions workflow file is `.github/workflows/tests.yml`.
- It runs on `push`, `pull_request`, and `workflow_dispatch`.
- It targets PHP `8.2` and MariaDB `10.4`.
- It runs `composer validate`, `composer audit`, and `composer test`.

## Related documentation

- Installation: [installation.md](installation.md)
- Database: [database.md](database.md)
- Privacy and demo data: [privacy-and-demo-data.md](privacy-and-demo-data.md)
