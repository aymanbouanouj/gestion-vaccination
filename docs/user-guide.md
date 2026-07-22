# User Guide

## Confirmed audience

- Confirmed: this interface is for administrator use only.
- Confirmed: there is no citizen self-service account flow.

## 1. Sign in

Route:

```text
/gestion_vaccination/pages/authentification.php
```

Confirmed behavior:

- enter `Login`
- enter `Mot de passe`
- submit `Se connecter`
- a valid administrator is redirected to the dashboard
- an administrator flagged for replacement is redirected to `Changer le mot de passe`

## 2. Use the dashboard

Route:

```text
/gestion_vaccination/index.php
```

Confirmed dashboard actions:

- `Inscrire un citoyen`
- `Rechercher un centre`
- `Activite des vaccinateurs`
- `Mot de passe`

## 3. Register a citizen

Form route:

```text
/gestion_vaccination/pages/formAjout.php
```

Submission route:

```text
/gestion_vaccination/pages/ajoutCitoyen.php
```

Confirmed required fields:

- `CNIE`
- `Nom`
- `Prenom`
- `Date de naissance`
- `Adresse`
- `Ville`
- `Activite`
- `Deja atteint du Covid`
- `Centre attribue`

Confirmed behavior:

- the form uses POST/Redirect/GET
- duplicate CNIE submissions are rejected
- unknown centres are rejected
- success and validation feedback are shown after redirect

## 4. Look up an assigned centre

Route:

```text
/gestion_vaccination/pages/centreVaccination.php
```

Confirmed lookup input:

- `CNIE`

Confirmed visible result fields:

- citizen last name
- citizen first name
- birth date
- city
- assigned-centre title
- assigned-centre address
- assigned-centre city

## 5. View vaccinator activity

Route:

```text
/gestion_vaccination/pages/performanceVaccinateur.php
```

Confirmed report output:

- registered vaccinators count
- recorded vaccinations count
- vaccinators with activity
- vaccinators without activity
- detailed table ordered by recorded injections descending, then identity fields

## 6. Change the administrator password

Route:

```text
/gestion_vaccination/pages/changerMotPasse.php
```

Confirmed form fields:

- `Mot de passe actuel`
- `Nouveau mot de passe`
- `Confirmation du nouveau mot de passe`

Confirmed behavior:

- the current password must be valid
- the new password must satisfy the policy
- the change redirects back to the dashboard after success

## 7. Log out

Confirmed behavior:

- logout is submitted from the shared header
- logout is handled by a POST request to `pages/authentification.php`
- the session is destroyed and the user is redirected to the login page
