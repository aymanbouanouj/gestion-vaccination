# Database integrity hardening 20260720_01

Target: MariaDB 10.4, `compagnevaccination`.

This migration contains only schema changes. It does not insert, update, or
delete data. Run it with a privileged private connection only after a fresh
backup, aggregate preflight, isolated restore rehearsal, and application
compatibility tests pass.

## Enforced rules

- Existing required citizen text remains non-null and also rejects empty or
  whitespace-only values.
- Citizen activity, birth date, COVID status, and centre become non-null.
- The existing citizen-to-centre foreign key is recreated with the same
  columns, target, and actions in the same table ALTER because MariaDB does not
  permit changing the participating column in place. Its forward name becomes
  `fk_citoyens_centre`; rollback restores the original generated name.
- Birth dates earlier than the application's documented 1900 minimum fail.
- COVID status is limited to `0` or `1`.
- Vaccination dose and every relationship become non-null.
- The four vaccination foreign keys are recreated with the same columns,
  targets, and actions under explicit names in the same atomic table ALTER;
  rollback restores their original generated names.
- The existing dose rule is retained as an explicit named
  `CHECK (dose IN (1,2))`; MariaDB drops the original column-level CHECK when
  the dose column is modified, so the forward ALTER recreates it deliberately.
- A citizen can have at most one vaccination event for each supported dose.

## Deliberately deferred rules

- No closed activity enumeration is added. The application currently uses a
  six-value allow-list, but the project contains no authoritative evidence
  that this is the permanent complete business domain.
- No equality constraint links an event centre to the citizen or vaccinator
  centre. Existing conflicts and absent semantic documentation require human
  review; cross-centre vaccination may be legitimate.
- No database check compares birth date with the current date. The application
  performs that time-dependent check; the migration avoids a non-static CHECK.
- Vaccine expiration and dose chronology remain analysis/reporting rules.
- The two-dose model is retained, not redesigned. A future booster model needs
  a separate migration and business decision.

## Required preflight

All candidate NULL/empty/invalid counts, orphan counts, and duplicate
`(cnieCitoyen, dose)` group counts must be zero. Existing row counts and
deterministic data digests must be captured immediately before and after.
Centre discrepancies do not block this migration because no centre-equality
rule is added and no value is changed.

## Rollback

The rollback removes only constraints introduced here and restores the prior
nullability/default declarations. It does not undo or alter row values. Because
MariaDB DDL commits implicitly, execute forward and rollback scripts only under
the documented operational gate and verify each statement.
