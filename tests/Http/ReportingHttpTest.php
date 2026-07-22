<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\HttpTestCase;
use Tests\Support\TestEnvironment;

#[Group('http')]
final class ReportingHttpTest extends HttpTestCase
{
    public function testReportPageShowsExpectedSummaryAndKeepsNeutralWording(): void
    {
        $this->loginAsPrimaryAdministrator();
        $response = $this->client->request('GET', 'pages/performanceVaccinateur.php');

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('Vaccinateurs enregistrés', $response->body);
        self::assertStringContainsString('Vaccinations enregistrées', $response->body);
        self::assertStringContainsString('Sans vaccination', $response->body);
        self::assertStringContainsString('Youssef', $response->body);
        self::assertStringContainsString('Alice', $response->body);
        self::assertStringContainsString('ليلى', $response->body);
        self::assertStringNotContainsString('classement', mb_strtolower($response->body, 'UTF-8'));
    }

    public function testReportEscapesHtmlLikeDataAndRetainsZeroActivityVaccinators(): void
    {
        $connection = TestEnvironment::applicationConnection();
        $connection->query(
            "INSERT INTO vaccinateurs (cnieVaccinateur, nomVacc, prenomVacc, idCentre) "
            . "VALUES ('VTESTH0099', '<b>Nom</b>', '<i>Prenom</i>', NULL)"
        );
        $connection->close();

        $this->loginAsPrimaryAdministrator();
        $response = $this->client->request('GET', 'pages/performanceVaccinateur.php');

        self::assertStringContainsString('&lt;b&gt;Nom&lt;/b&gt;', $response->body);
        self::assertStringContainsString('&lt;i&gt;Prenom&lt;/i&gt;', $response->body);
        self::assertStringContainsString('Non renseigné', $response->body);
        self::assertStringContainsString('<strong>0</strong>', $response->body);
    }
}
