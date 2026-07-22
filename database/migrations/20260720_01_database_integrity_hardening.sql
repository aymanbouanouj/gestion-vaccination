-- gestion_vaccination database-integrity hardening
-- Target: MariaDB 10.4, database compagnevaccination
-- Preconditions are documented in the companion notes and must be verified
-- immediately before execution. This migration contains no data mutations.

ALTER TABLE citoyens
    DROP FOREIGN KEY citoyens_ibfk_1,
    MODIFY activiteCit varchar(50) NOT NULL,
    MODIFY dateNaissanceCit date NOT NULL,
    MODIFY exCovid tinyint(1) NOT NULL DEFAULT 0,
    MODIFY idCentre int(11) NOT NULL,
    ADD CONSTRAINT chk_citoyens_nom_nonempty
        CHECK (CHAR_LENGTH(TRIM(nomCit)) > 0),
    ADD CONSTRAINT chk_citoyens_prenom_nonempty
        CHECK (CHAR_LENGTH(TRIM(prenomCit)) > 0),
    ADD CONSTRAINT chk_citoyens_adresse_nonempty
        CHECK (CHAR_LENGTH(TRIM(adresseCit)) > 0),
    ADD CONSTRAINT chk_citoyens_ville_nonempty
        CHECK (CHAR_LENGTH(TRIM(villeCit)) > 0),
    ADD CONSTRAINT chk_citoyens_activite_nonempty
        CHECK (CHAR_LENGTH(TRIM(activiteCit)) > 0),
    ADD CONSTRAINT chk_citoyens_birth_minimum
        CHECK (dateNaissanceCit >= '1900-01-01'),
    ADD CONSTRAINT chk_citoyens_excovid_boolean
        CHECK (exCovid IN (0, 1)),
    ADD CONSTRAINT fk_citoyens_centre
        FOREIGN KEY (idCentre) REFERENCES centres (idCentre);

ALTER TABLE vaccination
    DROP FOREIGN KEY vaccination_ibfk_1,
    DROP FOREIGN KEY vaccination_ibfk_2,
    DROP FOREIGN KEY vaccination_ibfk_3,
    DROP FOREIGN KEY vaccination_ibfk_4,
    MODIFY dose int(11) NOT NULL,
    MODIFY cnieCitoyen varchar(20) NOT NULL,
    MODIFY cnieVaccinateur varchar(20) NOT NULL,
    MODIFY idCentre int(11) NOT NULL,
    MODIFY idVaccin int(11) NOT NULL,
    ADD CONSTRAINT chk_vaccination_dose
        CHECK (dose IN (1, 2)),
    ADD CONSTRAINT uq_vaccination_citizen_dose
        UNIQUE (cnieCitoyen, dose),
    ADD CONSTRAINT fk_vaccination_citizen
        FOREIGN KEY (cnieCitoyen) REFERENCES citoyens (cnieCitoyen),
    ADD CONSTRAINT fk_vaccination_vaccinator
        FOREIGN KEY (cnieVaccinateur) REFERENCES vaccinateurs (cnieVaccinateur),
    ADD CONSTRAINT fk_vaccination_centre
        FOREIGN KEY (idCentre) REFERENCES centres (idCentre),
    ADD CONSTRAINT fk_vaccination_vaccine
        FOREIGN KEY (idVaccin) REFERENCES vaccins (idVaccin);
