SET NAMES utf8mb4;

INSERT INTO centres (idCentre, intituleCent, adresseCent, villeCent) VALUES
    (1, 'Centre Demo Rabat', '10 Avenue Demo', 'Rabat'),
    (2, 'Centre Demo Casablanca', '20 Boulevard Demo', 'Casablanca'),
    (3, 'Centre Demo Fes', '30 Rue Demo', 'Fes');

INSERT INTO vaccins (idVaccin, type, datePeremption) VALUES
    (1, 'DemoVac-A', '2030-01-01'),
    (2, 'DemoVac-B', '2030-06-01');

INSERT INTO vaccinateurs (cnieVaccinateur, nomVacc, prenomVacc, idCentre) VALUES
    ('VDEMO0001', 'Martin', 'Alice', 1),
    ('VDEMO0002', 'Bennani', 'Youssef', 2),
    ('VDEMO0003', 'Saadi', 'Leila', NULL);

INSERT INTO citoyens (cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) VALUES
    ('CDEMO0001', 'Dupont', 'Jean', '1 Rue Exemple', 'Rabat', 'Enseignement', '1990-01-15', 0, 1),
    ('CDEMO0002', 'Idrissi', 'Nora', '2 Avenue Exemple', 'Casablanca', 'Sante', '1988-07-20', 1, 2),
    ('CDEMO0003', 'Kettani', 'Sara', '3 Boulevard Exemple', 'Fes', 'Autre', '1995-03-09', 0, 3);

INSERT INTO vaccination (idVaccination, dateVaccin, dose, cnieCitoyen, cnieVaccinateur, idCentre, idVaccin) VALUES
    (1, '2026-01-15', 1, 'CDEMO0001', 'VDEMO0001', 1, 1),
    (2, '2026-02-10', 1, 'CDEMO0002', 'VDEMO0002', 2, 2),
    (3, '2026-03-12', 2, 'CDEMO0002', 'VDEMO0002', 2, 1);
