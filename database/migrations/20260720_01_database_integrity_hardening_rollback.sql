-- Technical rollback for 20260720_01_database_integrity_hardening.sql.
-- This weakens the newly added protections but does not mutate row values.

ALTER TABLE vaccination
    DROP FOREIGN KEY fk_vaccination_citizen,
    DROP FOREIGN KEY fk_vaccination_vaccinator,
    DROP FOREIGN KEY fk_vaccination_centre,
    DROP FOREIGN KEY fk_vaccination_vaccine,
    DROP INDEX uq_vaccination_citizen_dose,
    DROP CONSTRAINT chk_vaccination_dose,
    MODIFY dose int(11) NULL DEFAULT NULL,
    MODIFY cnieCitoyen varchar(20) NULL DEFAULT NULL,
    MODIFY cnieVaccinateur varchar(20) NULL DEFAULT NULL,
    MODIFY idCentre int(11) NULL DEFAULT NULL,
    MODIFY idVaccin int(11) NULL DEFAULT NULL,
    ADD CONSTRAINT dose CHECK (dose IN (1, 2)),
    ADD CONSTRAINT vaccination_ibfk_1
        FOREIGN KEY (cnieCitoyen) REFERENCES citoyens (cnieCitoyen),
    ADD CONSTRAINT vaccination_ibfk_2
        FOREIGN KEY (cnieVaccinateur) REFERENCES vaccinateurs (cnieVaccinateur),
    ADD CONSTRAINT vaccination_ibfk_3
        FOREIGN KEY (idCentre) REFERENCES centres (idCentre),
    ADD CONSTRAINT vaccination_ibfk_4
        FOREIGN KEY (idVaccin) REFERENCES vaccins (idVaccin);

ALTER TABLE citoyens
    DROP FOREIGN KEY fk_citoyens_centre,
    DROP CONSTRAINT chk_citoyens_nom_nonempty,
    DROP CONSTRAINT chk_citoyens_prenom_nonempty,
    DROP CONSTRAINT chk_citoyens_adresse_nonempty,
    DROP CONSTRAINT chk_citoyens_ville_nonempty,
    DROP CONSTRAINT chk_citoyens_activite_nonempty,
    DROP CONSTRAINT chk_citoyens_birth_minimum,
    DROP CONSTRAINT chk_citoyens_excovid_boolean,
    MODIFY activiteCit varchar(50) NULL DEFAULT NULL,
    MODIFY dateNaissanceCit date NULL DEFAULT NULL,
    MODIFY exCovid tinyint(1) NULL DEFAULT 0,
    MODIFY idCentre int(11) NULL DEFAULT NULL,
    ADD CONSTRAINT citoyens_ibfk_1
        FOREIGN KEY (idCentre) REFERENCES centres (idCentre);
