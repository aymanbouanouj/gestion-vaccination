<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli_sql_exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('integration')]
final class CitizenConstraintIntegrationTest extends DatabaseTestCase
{
    public function testCitizenInsertionIntoTheTestDatabaseWorks(): void
    {
        $this->insertCitizen('TSTI0010', 'Valid', 'Citizen', '30 rue Test', 'Rabat', 'Autre', '1990-01-01', 0, 1);

        self::assertSame(1, $this->countRows('citoyens', "cnieCitoyen = 'TSTI0010'"));
    }

    public function testDuplicateCitizenIdentifierIsRejected(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->insertCitizen('TSTA0001', 'Dupont', 'Jean', '10 rue de Test', 'Rabat', 'Enseignement', '1990-01-15', 0, 1);
    }

    public function testCitizenCentreForeignKeyIsEnforced(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->insertCitizen('TSTI0011', 'NoCentre', 'Citizen', '31 rue Test', 'Rabat', 'Autre', '1991-01-01', 0, 999);
    }

    public function testCitizenNonNullAndWhitespaceChecksAreEnforced(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->connection->query(
            "INSERT INTO citoyens (cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) "
            . "VALUES ('TSTI0012', '   ', 'Citizen', '32 rue Test', 'Rabat', 'Autre', '1992-01-01', 0, 1)"
        );
    }

    public function testInvalidCovidValueIsRejected(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->connection->query(
            "INSERT INTO citoyens (cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) "
            . "VALUES ('TSTI0013', 'Covid', 'Invalid', '33 rue Test', 'Rabat', 'Autre', '1992-01-01', 2, 1)"
        );
    }

    public function testInvalidDoseAndDuplicateCitizenDoseAreRejected(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->connection->query(
            "INSERT INTO vaccination (dateVaccin, dose, cnieCitoyen, cnieVaccinateur, idCentre, idVaccin) "
            . "VALUES ('2026-05-01', 3, 'TSTA0001', 'VTESTA0001', 1, 1)"
        );
    }

    public function testDuplicateCitizenDoseConstraintIsEnforced(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->connection->query(
            "INSERT INTO vaccination (dateVaccin, dose, cnieCitoyen, cnieVaccinateur, idCentre, idVaccin) "
            . "VALUES ('2026-06-01', 1, 'TSTA0001', 'VTESTA0001', 1, 1)"
        );
    }

    public function testVaccinationRelationshipsAndNonNullConstraintsAreEnforced(): void
    {
        $this->expectException(mysqli_sql_exception::class);
        $this->connection->query(
            "INSERT INTO vaccination (dateVaccin, dose, cnieCitoyen, cnieVaccinateur, idCentre, idVaccin) "
            . "VALUES ('2026-06-10', 1, 'UNKNOWN0001', 'VTESTA0001', 1, 1)"
        );
    }

    private function insertCitizen(
        string $cnie,
        string $nom,
        string $prenom,
        string $adresse,
        string $ville,
        string $activite,
        string $dateNaissance,
        int $exCovid,
        int $centre
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO citoyens '
            . '(cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param('sssssssii', $cnie, $nom, $prenom, $adresse, $ville, $activite, $dateNaissance, $exCovid, $centre);
        $statement->execute();
    }
}
