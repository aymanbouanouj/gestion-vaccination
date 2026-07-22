SET NAMES utf8mb4;

CREATE TABLE agentAdmins (
    idAdmin INT(11) NOT NULL AUTO_INCREMENT,
    nomAdm VARCHAR(50) NOT NULL,
    prenomAdm VARCHAR(50) NOT NULL,
    emailAdm VARCHAR(100) DEFAULT NULL,
    login VARCHAR(50) DEFAULT NULL,
    motPass VARCHAR(255) NOT NULL,
    mustChangePassword TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1: password replacement required; 0: normal access',
    passwordChangedAt DATETIME DEFAULT NULL COMMENT 'Timestamp of the latest completed password replacement',
    PRIMARY KEY (idAdmin),
    UNIQUE KEY login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE centres (
    idCentre INT(11) NOT NULL AUTO_INCREMENT,
    intituleCent VARCHAR(100) NOT NULL,
    adresseCent VARCHAR(100) NOT NULL,
    villeCent VARCHAR(50) NOT NULL,
    PRIMARY KEY (idCentre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE citoyens (
    cnieCitoyen VARCHAR(20) NOT NULL,
    nomCit VARCHAR(50) NOT NULL,
    prenomCit VARCHAR(50) NOT NULL,
    adresseCit VARCHAR(100) NOT NULL,
    villeCit VARCHAR(50) NOT NULL,
    activiteCit VARCHAR(50) DEFAULT NULL,
    dateNaissanceCit DATE DEFAULT NULL,
    exCovid TINYINT(1) DEFAULT NULL,
    idCentre INT(11) DEFAULT NULL,
    PRIMARY KEY (cnieCitoyen),
    KEY citoyens_ibfk_1 (idCentre),
    CONSTRAINT citoyens_ibfk_1 FOREIGN KEY (idCentre) REFERENCES centres (idCentre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE vaccinateurs (
    cnieVaccinateur VARCHAR(20) NOT NULL,
    nomVacc VARCHAR(50) NOT NULL,
    prenomVacc VARCHAR(50) NOT NULL,
    idCentre INT(11) DEFAULT NULL,
    PRIMARY KEY (cnieVaccinateur),
    KEY idCentre (idCentre),
    CONSTRAINT vaccinateurs_ibfk_1 FOREIGN KEY (idCentre) REFERENCES centres (idCentre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE vaccins (
    idVaccin INT(11) NOT NULL AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    datePeremption DATE DEFAULT NULL,
    PRIMARY KEY (idVaccin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE vaccination (
    idVaccination INT(11) NOT NULL AUTO_INCREMENT,
    dateVaccin DATE NOT NULL,
    dose INT(11) DEFAULT NULL CHECK (dose IN (1, 2)),
    cnieCitoyen VARCHAR(20) DEFAULT NULL,
    cnieVaccinateur VARCHAR(20) DEFAULT NULL,
    idCentre INT(11) DEFAULT NULL,
    idVaccin INT(11) DEFAULT NULL,
    PRIMARY KEY (idVaccination),
    KEY vaccination_ibfk_1 (cnieCitoyen),
    KEY vaccination_ibfk_2 (cnieVaccinateur),
    KEY vaccination_ibfk_3 (idCentre),
    KEY vaccination_ibfk_4 (idVaccin),
    CONSTRAINT vaccination_ibfk_1 FOREIGN KEY (cnieCitoyen) REFERENCES citoyens (cnieCitoyen),
    CONSTRAINT vaccination_ibfk_2 FOREIGN KEY (cnieVaccinateur) REFERENCES vaccinateurs (cnieVaccinateur),
    CONSTRAINT vaccination_ibfk_3 FOREIGN KEY (idCentre) REFERENCES centres (idCentre),
    CONSTRAINT vaccination_ibfk_4 FOREIGN KEY (idVaccin) REFERENCES vaccins (idVaccin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE authLoginAttempts (
    idAttempt BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    loginFingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ipFingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attemptedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idAttempt),
    KEY idx_login_attempt_time (loginFingerprint, attemptedAt),
    KEY idx_ip_attempt_time (ipFingerprint, attemptedAt),
    KEY idx_attempt_time (attemptedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Short-lived failed login attempts; contains keyed fingerprints only';

CREATE TABLE authSecurityEvents (
    idEvent BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    eventType VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idAdmin INT(11) DEFAULT NULL,
    loginFingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ipFingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    occurredAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (idEvent),
    KEY idx_event_time (occurredAt),
    KEY idx_event_type_time (eventType, occurredAt),
    KEY idx_event_admin_time (idAdmin, occurredAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Authentication security events without credential or session data';
