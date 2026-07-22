<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\HttpResponse;
use Tests\Support\HttpTestCase;
use Tests\Support\TestEnvironment;

#[Group('http')]
final class AuthorizationCsrfHttpTest extends HttpTestCase
{
    public static function protectedGetRoutes(): array
    {
        return [
            ['index.php'],
            ['pages/formAjout.php'],
            ['pages/centreVaccination.php'],
            ['pages/performanceVaccinateur.php'],
            ['pages/changerMotPasse.php'],
        ];
    }

    #[DataProvider('protectedGetRoutes')]
    public function testAnonymousProtectedGetRoutesRedirectToLogin(string $path): void
    {
        $response = $this->client->request('GET', $path);

        self::assertSame(302, $response->statusCode);
        self::assertSame('/gestion_vaccination/pages/authentification.php', $response->header('location'));
    }

    public function testAnonymousProtectedPostReturnsForbidden(): void
    {
        $response = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'cnieCitoyen' => 'TSTP0099',
        ]);

        self::assertSame(403, $response->statusCode);
        self::assertStringContainsString('Accès refusé.', $response->body);
    }

    public function testLoginPageRemainsPublic(): void
    {
        $response = $this->client->request('GET', 'pages/authentification.php');

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('Connexion administrateur', $response->body);
    }

    public function testForcedChangeAdministratorCannotAccessBusinessRoutes(): void
    {
        $login = $this->loginAsForcedPasswordAdministrator();
        self::assertSame(303, $login->statusCode);

        $dashboard = $this->fetchDashboard();
        self::assertSame(302, $dashboard->statusCode);
        self::assertSame('/gestion_vaccination/pages/changerMotPasse.php', $dashboard->header('location'));

        $post = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'csrf_token' => 'invalid',
        ]);
        self::assertSame(403, $post->statusCode);
        self::assertStringContainsString('Changement de mot de passe requis.', $post->body);
    }

    public function testAuthenticatedAdministratorCanAccessAllowedRoutes(): void
    {
        $this->loginAsPrimaryAdministrator();

        foreach (['index.php', 'pages/formAjout.php', 'pages/centreVaccination.php', 'pages/performanceVaccinateur.php'] as $path) {
            $response = $this->client->request('GET', $path);
            self::assertSame(200, $response->statusCode, $path);
        }
    }

    public function testCitizenCreationAndLookupRequireValidCsrfTokens(): void
    {
        $this->loginAsPrimaryAdministrator();

        $citizenPost = $this->client->request('POST', 'pages/ajoutCitoyen.php', [
            'csrf_token' => 'invalid',
            'cnieCitoyen' => 'TSTP0010',
            'nomCit' => 'Invalid',
            'prenomCit' => 'Token',
            'adresseCit' => '1 rue Test',
            'villeCit' => 'Rabat',
            'activiteCit' => 'Autre',
            'dateNaissanceCit' => '1990-01-01',
            'exCovid' => '0',
            'idCentre' => '1',
        ]);
        self::assertSame(403, $citizenPost->statusCode);

        $lookupPost = $this->client->request('POST', 'pages/centreVaccination.php', [
            'csrf_token' => 'invalid',
            'cnieCitoyen' => 'TSTA0001',
        ]);
        self::assertSame(403, $lookupPost->statusCode);
    }

    public function testLogoutGetDoesNotLogOut(): void
    {
        $this->loginAsPrimaryAdministrator();

        $getResponse = $this->client->request('GET', 'pages/authentification.php?action=logout');
        self::assertSame(200, $getResponse->statusCode);

        $dashboard = $this->fetchDashboard();
        self::assertSame(200, $dashboard->statusCode);
    }

    public function testLogoutPostWithoutValidCsrfFails(): void
    {
        $this->loginAsPrimaryAdministrator();

        $response = $this->client->request('POST', 'pages/authentification.php', [
            'action' => 'logout',
            'csrf_token' => 'invalid',
        ]);

        self::assertSame(403, $response->statusCode);
        self::assertStringContainsString('Requête invalide.', $response->body);
    }

    public function testPasswordChangeRequiresCsrf(): void
    {
        $this->loginAsForcedPasswordAdministrator();

        $response = $this->client->request('POST', 'pages/changerMotPasse.php', [
            'csrf_token' => 'invalid',
            'current_password' => 'invalid',
            'new_password' => 'Strong#Password2026',
            'new_password_confirmation' => 'Strong#Password2026',
        ]);

        self::assertSame(403, $response->statusCode);
        self::assertStringContainsString('Requête invalide.', $response->body);
    }
}
