# Installation

## Status labels used in this file

- Confirmed: directly verified from source files or local execution.
- Inferred from code: deduced from code structure without a separate external system check.
- Optional recommendation: useful operational advice outside the current contract.
- Unresolved limitation: known behavior that remains unsolved in this release candidate.

## Confirmed prerequisites

- PHP CLI 8.2 or newer
- Composer
- A local MariaDB or MySQL server
- A local web server that exposes the project at `/gestion_vaccination`

## Confirmed path rules

- Confirmed: `VACCINATION_BASE_PATH` is defined as `/gestion_vaccination` in `config/security.php`.
- Confirmed: the default private database config lookup path is three directories above `config/connexion.php`, then `private_config/gestion_vaccination/database.php`.
- Confirmed: the default private security config lookup path is three directories above `config/security.php`, then `private_config/gestion_vaccination/security.php`.
- Unresolved limitation: the base path is hard-coded. The application will not generate correct links if it is served under another public route without a private adaptation.

## Step 1: Place the working copy under the expected web path

- Confirmed: the repository must be served at `/gestion_vaccination`.
- Optional recommendation: if your future GitHub clone directory is `gestion-vaccination`, rename the local working copy to `gestion_vaccination` before serving it.

## Step 2: Install PHP dependencies

```powershell
composer install
```

Confirmed: this command is required by the permanent test runner.

## Step 3: Create the private database configuration

Create a PHP file outside the repository with this exact shape:

```php
<?php

return [
    'host' => '<database_host>',
    'username' => '<database_user>',
    'password' => '<database_password>',
    'database' => '<database_name>',
];
```

Confirmed default lookup path on the documented XAMPP layout:

```text
<xampp_root>/private_config/gestion_vaccination/database.php
```

Confirmed optional override:

- `GESTION_VACCINATION_DB_CONFIG`

## Step 4: Create the private security configuration

Create a PHP file outside the repository with this exact shape:

```php
<?php

return [
    'fingerprint_key' => '<64 hexadecimal characters>',
];
```

Confirmed default lookup path on the documented XAMPP layout:

```text
<xampp_root>/private_config/gestion_vaccination/security.php
```

Confirmed optional override:

- `GESTION_VACCINATION_SECURITY_CONFIG`

## Step 5: Apply the public schema

- Confirmed: the public schema file is `database/schema.sql`.
- Confirmed: the repository does not ship a live SQL dump.
- Confirmed: `database/schema.sql` contains structure only.
- Optional recommendation: import the schema with your usual local MariaDB administration tool.

## Step 6: Optionally load the synthetic demo seed

- Confirmed: the optional synthetic demo seed is `database/demo/seed-demo.sql`.
- Confirmed: it does not create administrator accounts.
- Confirmed: it is safe to skip this step for an empty installation.

## Step 7: Create the first administrator

Run the included CLI helper:

```powershell
php scripts/create_admin.php
```

Confirmed behavior:

- the script inserts a record into `agentAdmins`
- the script hashes the password with `PASSWORD_DEFAULT`
- the script validates basic field lengths
- the script applies the existing password policy helper
- the script defaults `mustChangePassword` to `1`

## Step 8: Open the login page

Confirmed public login route:

```text
/gestion_vaccination/pages/authentification.php
```

## Step 9: Verify the installation

Run the permanent isolated test suite:

```powershell
composer test
```

Confirmed: this command does not use the live `compagnevaccination` database when the test runner is used as designed.

## Installation rehearsal status

- Confirmed: the release candidate was rehearsed locally against an isolated database using `database/schema.sql`, `database/demo/seed-demo.sql`, and `scripts/create_admin.php`.
- Unresolved limitation: this document does not claim compatibility with arbitrary hosting layouts outside the fixed `/gestion_vaccination` route pattern.
