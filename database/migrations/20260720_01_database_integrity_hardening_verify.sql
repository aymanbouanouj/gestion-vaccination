-- Returns only aggregate verification evidence; it does not expose records.

SELECT 'citizen_candidate_violations' AS check_name, COUNT(*) AS violations
FROM citoyens
WHERE activiteCit IS NULL
   OR dateNaissanceCit IS NULL
   OR exCovid IS NULL
   OR idCentre IS NULL
   OR CHAR_LENGTH(TRIM(nomCit)) = 0
   OR CHAR_LENGTH(TRIM(prenomCit)) = 0
   OR CHAR_LENGTH(TRIM(adresseCit)) = 0
   OR CHAR_LENGTH(TRIM(villeCit)) = 0
   OR CHAR_LENGTH(TRIM(activiteCit)) = 0
   OR dateNaissanceCit < '1900-01-01'
   OR exCovid NOT IN (0, 1);

SELECT 'vaccination_candidate_violations' AS check_name, COUNT(*) AS violations
FROM vaccination
WHERE dose IS NULL
   OR cnieCitoyen IS NULL
   OR cnieVaccinateur IS NULL
   OR idCentre IS NULL
   OR idVaccin IS NULL
   OR dose NOT IN (1, 2);

SELECT 'duplicate_citizen_dose_groups' AS check_name, COUNT(*) AS violations
FROM (
    SELECT cnieCitoyen, dose
    FROM vaccination
    GROUP BY cnieCitoyen, dose
    HAVING COUNT(*) > 1
) AS duplicate_groups;

SELECT 'orphaned_vaccination_relationships' AS check_name, COUNT(*) AS violations
FROM vaccination AS vaccination_event
LEFT JOIN citoyens AS citizen
    ON citizen.cnieCitoyen = vaccination_event.cnieCitoyen
LEFT JOIN vaccinateurs AS vaccinator
    ON vaccinator.cnieVaccinateur = vaccination_event.cnieVaccinateur
LEFT JOIN centres AS centre
    ON centre.idCentre = vaccination_event.idCentre
LEFT JOIN vaccins AS vaccine
    ON vaccine.idVaccin = vaccination_event.idVaccin
WHERE citizen.cnieCitoyen IS NULL
   OR vaccinator.cnieVaccinateur IS NULL
   OR centre.idCentre IS NULL
   OR vaccine.idVaccin IS NULL;

SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
      (TABLE_NAME = 'citoyens'
       AND COLUMN_NAME IN ('activiteCit', 'dateNaissanceCit', 'exCovid', 'idCentre'))
      OR
      (TABLE_NAME = 'vaccination'
       AND COLUMN_NAME IN ('dose', 'cnieCitoyen', 'cnieVaccinateur', 'idCentre', 'idVaccin'))
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT tc.TABLE_NAME, tc.CONSTRAINT_TYPE, tc.CONSTRAINT_NAME
FROM information_schema.TABLE_CONSTRAINTS AS tc
WHERE tc.TABLE_SCHEMA = DATABASE()
  AND tc.CONSTRAINT_NAME IN (
      'chk_citoyens_nom_nonempty',
      'chk_citoyens_prenom_nonempty',
      'chk_citoyens_adresse_nonempty',
      'chk_citoyens_ville_nonempty',
      'chk_citoyens_activite_nonempty',
      'chk_citoyens_birth_minimum',
      'chk_citoyens_excovid_boolean',
      'fk_citoyens_centre',
      'chk_vaccination_dose',
      'fk_vaccination_citizen',
      'fk_vaccination_vaccinator',
      'fk_vaccination_centre',
      'fk_vaccination_vaccine',
      'uq_vaccination_citizen_dose'
  )
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME;
