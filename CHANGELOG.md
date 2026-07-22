# Changelog

All notable repository-level changes for the public release candidate are documented here.

## v0.1.0 - 2026-07-22

### Added
- Public release documentation in English for installation, architecture, database, security, privacy, testing, user workflow, and project history.
- A public, schema-only SQL bootstrap file at `database/schema.sql`.
- Optional synthetic demonstration data at `database/demo/seed-demo.sql`.
- A CLI helper at `scripts/create_admin.php` for creating the first administrator without storing credentials in the repository.
- Safe public screenshots generated from an isolated synthetic environment.

### Changed
- `.gitignore`, `.editorconfig`, and `.gitattributes` were aligned with public release and secret-handling requirements.
- `docs/testing.md` was updated for public release consistency and cross-links.
- Branding asset guidance was corrected to match the actual `assets/branding/` location.

### Reviewed
- Unused duplicate branding images under `assets/images/` were identified as obsolete publication candidates during the release audit.

### Verified
- The permanent PHPUnit suite passes locally with 66 tests and 381 assertions.
- A clean release rehearsal was executed against an isolated MariaDB database using `database/schema.sql`, `database/demo/seed-demo.sql`, and `scripts/create_admin.php`.
