# Privacy and Demo Data

## Confirmed repository privacy rules

The public repository must not contain:

- live SQL dumps
- real citizen records
- real CNIE values
- real vaccination records
- real administrator credentials
- working administrator passwords
- live administrator password hashes
- private database configuration
- session files, CSRF tokens, cookies, or browser profiles
- private backup paths
- temporary PHPUnit runtimes

## Confirmed demo-data policy

- Confirmed: demo data must be authored from scratch.
- Confirmed: `database/demo/seed-demo.sql` contains only synthetic demonstration records.
- Confirmed: no administrator account is seeded publicly.
- Confirmed: `scripts/create_admin.php` is the public path for creating the first admin locally.

## Confirmed screenshot policy

- Confirmed: public screenshots in `docs/screenshots/` were generated from an isolated synthetic environment.
- Confirmed: the screenshot workflow did not reuse the live application database.
- Confirmed: screenshots do not contain private Windows paths, private config, or live health records.

## Confirmed private release-audit material

- Confirmed: publication inventory, backup evidence, and live-database metadata snapshots were stored outside the repository during release preparation.
- Confirmed: those private audit files are not intended to be committed.

## Unresolved limitation

- Unresolved limitation: the repository itself cannot prove that every future screenshot contributor follows the same isolated process. Review new screenshots before publishing them.
