<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\HttpTestCase;
use Tests\Support\TestEnvironment;

#[Group('http')]
final class CitizenWorkflowHttpTest extends HttpTestCase
{
    public function testCitizenFormRendersWithCsrfAndValidSubmissionUsesPrg(): void
    {
        $this->loginAsPrimaryAdministrator();

        $formPage = $this->client->request('GET', 'pages/formAjout.php');
        self::assertSame(200, $formPage->statusCode);
        $csrf = TestEnvironment::extractHiddenFieldValue($formPage->body, 'csrf_token');

        $submit = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'TSTP0010',
            'nomCit' => 'Workflow',
            'prenomCit' => 'Valid',
            'adresseCit' => '50 rue PRG',
            'villeCit' => 'Rabat',
            'activiteCit' => 'Autre',
            'dateNaissanceCit' => '1994-06-14',
            'exCovid' => '0',
            'idCentre' => '1',
        ]);

        self::assertSame(303, $submit->statusCode);
        self::assertSame('formAjout.php', $submit->header('location'));

        $connection = TestEnvironment::applicationConnection();
        self::assertSame(1, (int) $connection->query("SELECT COUNT(*) AS total FROM citoyens WHERE cnieCitoyen = 'TSTP0010'")->fetch_assoc()['total']);
        $connection->close();

        $afterRedirect = $this->client->request('GET', 'pages/formAjout.php');
        self::assertSame(200, $afterRedirect->statusCode);
        self::assertStringContainsString('Le citoyen a été enregistré avec succès.', $afterRedirect->body);

        $connection = TestEnvironment::applicationConnection();
        self::assertSame(1, (int) $connection->query("SELECT COUNT(*) AS total FROM citoyens WHERE cnieCitoyen = 'TSTP0010'")->fetch_assoc()['total']);
        $connection->close();
    }

    public function testDuplicateCitizenSubmissionStaysSafeAndDoesNotInsert(): void
    {
        $this->loginAsPrimaryAdministrator();
        $formPage = $this->client->request('GET', 'pages/formAjout.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($formPage->body, 'csrf_token');

        $submit = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'TSTA0001',
            'nomCit' => 'Dupont',
            'prenomCit' => 'Jean',
            'adresseCit' => '10 rue de Test',
            'villeCit' => 'Rabat',
            'activiteCit' => 'Enseignement',
            'dateNaissanceCit' => '1990-01-15',
            'exCovid' => '0',
            'idCentre' => '1',
        ]);
        self::assertSame(303, $submit->statusCode);

        $afterRedirect = $this->client->request('GET', 'pages/formAjout.php');
        self::assertStringContainsString('Un citoyen avec ce CNIE existe déjà.', $afterRedirect->body);

        $connection = TestEnvironment::applicationConnection();
        self::assertSame(1, (int) $connection->query("SELECT COUNT(*) AS total FROM citoyens WHERE cnieCitoyen = 'TSTA0001'")->fetch_assoc()['total']);
        $connection->close();
    }

    public function testInvalidFieldsAndUnknownCentreDoNotInsert(): void
    {
        $this->loginAsPrimaryAdministrator();
        $formPage = $this->client->request('GET', 'pages/formAjout.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($formPage->body, 'csrf_token');

        $invalid = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'bad',
            'nomCit' => '',
            'prenomCit' => 'Citizen',
            'adresseCit' => '60 rue Test',
            'villeCit' => 'Rabat',
            'activiteCit' => 'Autre',
            'dateNaissanceCit' => '2999-01-01',
            'exCovid' => '0',
            'idCentre' => '1',
        ]);
        self::assertSame(303, $invalid->statusCode);

        $invalidPage = $this->client->request('GET', 'pages/formAjout.php');
        self::assertStringContainsString('Veuillez vérifier les points suivants', $invalidPage->body);

        $formPage = $this->client->request('GET', 'pages/formAjout.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($formPage->body, 'csrf_token');
        $unknownCentre = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'TSTP0011',
            'nomCit' => 'Centre',
            'prenomCit' => 'Absent',
            'adresseCit' => '61 rue Test',
            'villeCit' => 'Rabat',
            'activiteCit' => 'Autre',
            'dateNaissanceCit' => '1991-01-01',
            'exCovid' => '0',
            'idCentre' => '999',
        ]);
        self::assertSame(303, $unknownCentre->statusCode);

        $unknownCentrePage = $this->client->request('GET', 'pages/formAjout.php');
        self::assertStringContainsString('Le centre sélectionné n’existe pas.', $unknownCentrePage->body);

        $connection = TestEnvironment::applicationConnection();
        self::assertSame(0, (int) $connection->query("SELECT COUNT(*) AS total FROM citoyens WHERE cnieCitoyen = 'TSTP0011'")->fetch_assoc()['total']);
        $connection->close();
    }

    public function testCitizenCreationDatabaseFailureStaysGeneric(): void
    {
        $this->loginAsPrimaryAdministrator();
        $formPage = $this->client->request('GET', 'pages/formAjout.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($formPage->body, 'csrf_token');

        $connection = TestEnvironment::applicationConnection();
        $connection->query('RENAME TABLE citoyens TO citoyens_temp_failure');

        try {
            $submit = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
                'csrf_token' => $csrf,
                'cnieCitoyen' => 'TSTP0012',
                'nomCit' => 'Db',
                'prenomCit' => 'Failure',
                'adresseCit' => '62 rue Test',
                'villeCit' => 'Rabat',
                'activiteCit' => 'Autre',
                'dateNaissanceCit' => '1992-02-02',
                'exCovid' => '0',
                'idCentre' => '1',
            ]);
            self::assertSame(303, $submit->statusCode);
        } finally {
            $connection->query('RENAME TABLE citoyens_temp_failure TO citoyens');
            $connection->close();
        }

        $page = $this->client->request('GET', 'pages/formAjout.php');
        self::assertStringContainsString('L’enregistrement n’a pas pu être effectué. Veuillez réessayer.', $page->body);
        self::assertStringNotContainsString('SQLSTATE', $page->body);
        self::assertStringNotContainsString('mysqli', $page->body);
    }
}
