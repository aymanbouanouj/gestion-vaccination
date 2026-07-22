<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestEnvironment;

#[Group('integration')]
final class DatabaseIntegrationTest extends DatabaseTestCase
{
    public function testApplicationConnectionUsesUtf8mb4AndStrictSqlMode(): void
    {
        $charsetRow = $this->connection->query(
            'SELECT @@character_set_connection AS charsetName, @@collation_connection AS collationName, @@session.sql_mode AS sessionSqlMode'
        )->fetch_assoc();

        self::assertSame('utf8mb4', $charsetRow['charsetName']);
        self::assertStringStartsWith('utf8mb4_', $charsetRow['collationName']);

        foreach (TestEnvironment::requiredSqlModes() as $mode) {
            self::assertStringContainsString($mode, $charsetRow['sessionSqlMode']);
        }
    }

    public function testConnectionScriptRunsAgainstTheIsolatedDatabase(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(TestEnvironment::projectRoot() . '/test_connexion.php');
        exec($command . ' 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode, implode(PHP_EOL, $output));
        self::assertStringContainsString('Database connection: OK', implode(PHP_EOL, $output));
    }

    public function testTransactionRollbackLeavesNoCitizenBehind(): void
    {
        $this->connection->begin_transaction();
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO citoyens '
                . '(cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $cnie = 'TSTR0010';
            $nom = 'Rollback';
            $prenom = 'Citizen';
            $adresse = '20 rue Transaction';
            $ville = 'Rabat';
            $activite = 'Autre';
            $date = '1992-11-05';
            $covid = 0;
            $centre = 1;
            $statement->bind_param('sssssssii', $cnie, $nom, $prenom, $adresse, $ville, $activite, $date, $covid, $centre);
            $statement->execute();
        } finally {
            $this->connection->rollback();
        }

        self::assertSame(0, $this->countRows('citoyens', "cnieCitoyen = 'TSTR0010'"));
    }

    public function testArabicAndAccentedDataRoundTripsThroughTheDatabase(): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO citoyens '
            . '(cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $cnie = 'TSTR0011';
        $nom = 'بنصالح';
        $prenom = 'Élodie';
        $adresse = '21 شارع الاختبار';
        $ville = 'Fès';
        $activite = 'Enseignement';
        $date = '1993-12-24';
        $covid = 1;
        $centre = 3;
        $statement->bind_param('sssssssii', $cnie, $nom, $prenom, $adresse, $ville, $activite, $date, $covid, $centre);
        $statement->execute();

        $row = $this->connection->query(
            "SELECT nomCit, prenomCit, adresseCit FROM citoyens WHERE cnieCitoyen = 'TSTR0011'"
        )->fetch_assoc();

        self::assertSame('بنصالح', $row['nomCit']);
        self::assertSame('Élodie', $row['prenomCit']);
        self::assertSame('21 شارع الاختبار', $row['adresseCit']);
    }
}
