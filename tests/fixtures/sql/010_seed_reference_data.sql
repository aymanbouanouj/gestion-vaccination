SET NAMES utf8mb4;

INSERT INTO centres (idCentre, intituleCent, adresseCent, villeCent) VALUES
    (1, 'Centre Test Rabat', '1 avenue des Tests', 'Rabat'),
    (2, 'Centre Synthétique Casablanca', '2 boulevard QA', 'Casablanca'),
    (3, 'Centre Accentué Fès', '3 rue Échantillon', 'Fès'),
    (4, 'مركز الاختبار سلا', '4 شارع الاختبار', 'سلا'),
    (5, 'Centre Test Tanger', '5 place de Contrôle', 'Tanger');

INSERT INTO vaccins (idVaccin, type, datePeremption) VALUES
    (1, 'VacSynth-A', '2030-01-01'),
    (2, 'VacSynth-B', '2030-06-01');

INSERT INTO vaccinateurs (cnieVaccinateur, nomVacc, prenomVacc, idCentre) VALUES
    ('VTESTA0001', 'Martin', 'Alice', 1),
    ('VTESTB0002', 'Él Amrani', 'Youssef', 2),
    ('VTESTC0003', 'النجار', 'ليلى', NULL);

INSERT INTO citoyens (cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) VALUES
    ('TSTA0001', 'Dupont', 'Jean', '10 rue de Test', 'Rabat', 'Enseignement', '1990-01-15', 0, 1),
    ('TSTB0002', 'Él Idrissi', 'Nora', '11 avenue Synthétique', 'Casablanca', 'Santé', '1988-07-20', 1, 2),
    ('TSTC0003', 'الكتاني', 'سارة', '12 شارع الاختبار', 'سلا', 'Autre', '1995-03-09', 0, 4);

INSERT INTO vaccination (idVaccination, dateVaccin, dose, cnieCitoyen, cnieVaccinateur, idCentre, idVaccin) VALUES
    (1, '2026-01-15', 1, 'TSTA0001', 'VTESTA0001', 1, 1),
    (2, '2026-02-10', 1, 'TSTB0002', 'VTESTB0002', 2, 2),
    (3, '2026-03-12', 2, 'TSTB0002', 'VTESTB0002', 1, 1);
