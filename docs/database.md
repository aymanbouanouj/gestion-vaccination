# Database

## Confirmed public artifacts

- `database/schema.sql`: public schema-only bootstrap
- `database/demo/seed-demo.sql`: optional synthetic demonstration data
- `database/migrations/20260720_01_database_integrity_hardening.sql`: schema hardening migration history
- `database/migrations/20260720_01_database_integrity_hardening_verify.sql`: aggregate verification queries
- `database/migrations/20260720_01_database_integrity_hardening_rollback.sql`: rollback script for the hardening migration

## Confirmed tables

| Table | Purpose |
| --- | --- |
| `agentAdmins` | Administrator identities and password state |
| `centres` | Vaccination centres |
| `citoyens` | Registered citizens |
| `vaccinateurs` | Vaccinators |
| `vaccins` | Vaccine catalogue entries |
| `vaccination` | Vaccination events |
| `authLoginAttempts` | Short-lived failed-login throttling evidence |
| `authSecurityEvents` | Authentication security events |

## Confirmed business relationships

- `citoyens.idCentre` references `centres.idCentre`
- `vaccinateurs.idCentre` references `centres.idCentre`
- `vaccination.cnieCitoyen` references `citoyens.cnieCitoyen`
- `vaccination.cnieVaccinateur` references `vaccinateurs.cnieVaccinateur`
- `vaccination.idCentre` references `centres.idCentre`
- `vaccination.idVaccin` references `vaccins.idVaccin`

## Confirmed integrity rules in the public schema

- `citoyens` required text fields reject empty or whitespace-only content through `CHECK` constraints
- `citoyens.dateNaissanceCit` cannot be earlier than `1900-01-01`
- `citoyens.exCovid` is constrained to `0` or `1`
- `vaccination.dose` is constrained to `1` or `2`
- `vaccination` enforces a unique `(cnieCitoyen, dose)` pair

## Confirmed demo-data rules

- Confirmed: `database/demo/seed-demo.sql` contains only synthetic centres, vaccinators, vaccines, citizens, and vaccination events.
- Confirmed: it does not create administrator accounts.
- Confirmed: administrator creation is handled separately by `scripts/create_admin.php`.

## Live metadata capture status during release preparation

- Confirmed: live table metadata was captured through read-only `information_schema` queries.
- Confirmed: exact row counts and deterministic digests were captured for most tables through the application connection.
- Unresolved limitation: direct row reads for `authSecurityEvents` were denied by the live application database user during release preparation, so only metadata row estimates were available for that table in the private release audit.

## Inferred operational note

- Inferred from code comments and live privilege behavior: `authSecurityEvents` is intended to be append-only for the web application in normal operation.

## Public installation order

1. Apply `database/schema.sql`
2. Optionally apply `database/demo/seed-demo.sql`
3. Run `php scripts/create_admin.php`

## What this repository does not ship

- No live SQL dump
- No anonymized live records
- No public administrator password hash
- No automatic migration runner inside the web application
