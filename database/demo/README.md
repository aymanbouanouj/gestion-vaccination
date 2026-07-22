# Demo Seed

## Confirmed contents

`seed-demo.sql` inserts only synthetic data for:

- `centres`
- `vaccins`
- `vaccinateurs`
- `citoyens`
- `vaccination`

## Confirmed exclusions

- No administrator account is created.
- No password hash is stored.
- No live or anonymized production data is included.

## Confirmed usage order

1. Apply `database/schema.sql`
2. Optionally import `database/demo/seed-demo.sql`
3. Run `php scripts/create_admin.php`
