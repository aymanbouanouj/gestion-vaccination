<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\HttpTestCase;
use Tests\Support\TestEnvironment;

#[Group('http')]
final class CitizenLookupHttpTest extends HttpTestCase
{
    public function testValidLookupReturnsCitizenAndCentreInformation(): void
    {
        $this->loginAsPrimaryAdministrator();
        $lookupPage = $this->client->request('GET', 'pages/centreVaccination.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($lookupPage->body, 'csrf_token');

        $response = $this->client->request('POST', 'pages/centreVaccination.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'TSTA0001',
        ]);

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('Dupont', $response->body);
        self::assertStringContainsString('Centre Test Rabat', $response->body);
    }

    public function testUnknownAndInvalidIdentifiersReturnSafeMessages(): void
    {
        $this->loginAsPrimaryAdministrator();
        $lookupPage = $this->client->request('GET', 'pages/centreVaccination.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($lookupPage->body, 'csrf_token');

        $unknown = $this->client->request('POST', 'pages/centreVaccination.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'TSTZ9999',
        ]);
        self::assertStringContainsString('Aucun citoyen trouvé avec ce CNIE.', $unknown->body);

        $lookupPage = $this->client->request('GET', 'pages/centreVaccination.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($lookupPage->body, 'csrf_token');
        $invalid = $this->client->request('POST', 'pages/centreVaccination.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'bad',
        ]);
        self::assertStringContainsString('Le CNIE doit contenir', $invalid->body);
    }

    public function testLookupEscapesStoredHtmlLikeContent(): void
    {
        $connection = TestEnvironment::applicationConnection();
        $connection->query("INSERT INTO centres (idCentre, intituleCent, adresseCent, villeCent) VALUES (9, '<strong>Centre HTML</strong>', '<em>Adresse</em>', 'Fès')");
        $connection->query(
            "INSERT INTO citoyens (cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, dateNaissanceCit, exCovid, idCentre) "
            . "VALUES ('TSTH0099', '<script>Nom</script>', 'Prénom', 'Adresse sûre', 'Fès', 'Autre', '1990-01-01', 0, 9)"
        );
        $connection->close();

        $this->loginAsPrimaryAdministrator();
        $lookupPage = $this->client->request('GET', 'pages/centreVaccination.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($lookupPage->body, 'csrf_token');

        $response = $this->client->request('POST', 'pages/centreVaccination.php', [
            'csrf_token' => $csrf,
            'cnieCitoyen' => 'TSTH0099',
        ]);

        self::assertStringContainsString('&lt;script&gt;Nom&lt;/script&gt;', $response->body);
        self::assertStringContainsString('&lt;strong&gt;Centre HTML&lt;/strong&gt;', $response->body);
        self::assertStringNotContainsString('<script>Nom</script>', $response->body);
    }

    public function testLookupDatabaseFailureDoesNotExposeTechnicalDetails(): void
    {
        $this->loginAsPrimaryAdministrator();
        $lookupPage = $this->client->request('GET', 'pages/centreVaccination.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($lookupPage->body, 'csrf_token');

        $connection = TestEnvironment::applicationConnection();
        $connection->query('RENAME TABLE citoyens TO citoyens_temp_failure');

        try {
            $response = $this->client->request('POST', 'pages/centreVaccination.php', [
                'csrf_token' => $csrf,
                'cnieCitoyen' => 'TSTA0001',
            ]);
            self::assertSame(200, $response->statusCode);
            self::assertStringContainsString('La recherche est temporairement indisponible.', $response->body);
            self::assertStringNotContainsString('SQLSTATE', $response->body);
            self::assertStringNotContainsString('mysqli', $response->body);
        } finally {
            $connection->query('RENAME TABLE citoyens_temp_failure TO citoyens');
            $connection->close();
        }
    }
}
