<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\HttpTestCase;
use Tests\Support\TestCredentials;
use Tests\Support\TestEnvironment;

#[Group('http')]
final class AuthenticationHttpTest extends HttpTestCase
{
    public function testValidLoginRedirectsToDashboardAndRegeneratesTheSessionId(): void
    {
        $loginPage = $this->client->request('GET', 'pages/authentification.php');
        $initialSessionId = $this->client->cookieValue('gestion_vaccination_session');

        self::assertSame(200, $loginPage->statusCode);
        self::assertNotNull($initialSessionId);

        $response = $this->loginAsPrimaryAdministrator();

        self::assertSame(303, $response->statusCode);
        self::assertSame('/gestion_vaccination/index.php', $response->header('location'));
        self::assertNotNull($this->client->cookieValue('gestion_vaccination_session'));
        self::assertNotSame($initialSessionId, $this->client->cookieValue('gestion_vaccination_session'));

        $dashboard = $this->followRedirect($response);
        self::assertSame(200, $dashboard->statusCode);
        self::assertStringContainsString('Bienvenue,', $dashboard->body);
    }

    public function testInvalidPasswordAndUnknownLoginExposeTheSameVisibleError(): void
    {
        $invalidPassword = $this->login(TestCredentials::PRIMARY_ADMIN_LOGIN, 'Definitely-Wrong#2026');
        $unknownUser = $this->login('unknown_login', 'Definitely-Wrong#2026');
        $invalidPasswordBody = html_entity_decode($invalidPassword->body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $unknownUserBody = html_entity_decode($unknownUser->body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        self::assertSame(200, $invalidPassword->statusCode);
        self::assertSame(200, $unknownUser->statusCode);
        self::assertStringContainsString("Nom d'utilisateur ou mot de passe incorrect.", $invalidPasswordBody);
        self::assertStringContainsString("Nom d'utilisateur ou mot de passe incorrect.", $unknownUserBody);
    }

    public function testForcedPasswordChangeAdministratorIsRedirectedToPasswordChange(): void
    {
        $response = $this->loginAsForcedPasswordAdministrator();

        self::assertSame(303, $response->statusCode);
        self::assertSame('/gestion_vaccination/pages/changerMotPasse.php', $response->header('location'));
    }

    public function testPasswordChangeSucceedsAndOldPasswordStopsWorking(): void
    {
        $login = $this->loginAsForcedPasswordAdministrator();
        self::assertSame(303, $login->statusCode);

        $passwordPage = $this->client->request('GET', 'pages/changerMotPasse.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($passwordPage->body, 'csrf_token');

        $changeResponse = $this->client->request('POST', 'pages/changerMotPasse.php', [
            'csrf_token' => $csrf,
            'current_password' => TestCredentials::FORCED_ADMIN_PASSWORD,
            'new_password' => TestCredentials::UPDATED_ADMIN_PASSWORD,
            'new_password_confirmation' => TestCredentials::UPDATED_ADMIN_PASSWORD,
        ]);

        self::assertSame(303, $changeResponse->statusCode);
        self::assertSame('/gestion_vaccination/index.php', $changeResponse->header('location'));

        $freshClient = TestEnvironment::newHttpClient();
        $oldPasswordResponse = $freshClient->request('POST', 'pages/authentification.php', [
            'action' => 'login',
            'login' => TestCredentials::FORCED_ADMIN_LOGIN,
            'motPass' => TestCredentials::FORCED_ADMIN_PASSWORD,
        ]);
        self::assertSame(200, $oldPasswordResponse->statusCode);
        self::assertStringContainsString(
            "Nom d'utilisateur ou mot de passe incorrect.",
            html_entity_decode($oldPasswordResponse->body, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );

        $newPasswordResponse = $freshClient->request('POST', 'pages/authentification.php', [
            'action' => 'login',
            'login' => TestCredentials::FORCED_ADMIN_LOGIN,
            'motPass' => TestCredentials::UPDATED_ADMIN_PASSWORD,
        ]);
        self::assertSame(303, $newPasswordResponse->statusCode);
        self::assertSame('/gestion_vaccination/index.php', $newPasswordResponse->header('location'));
    }

    public function testWeakPasswordAndWrongCurrentPasswordRemainRejected(): void
    {
        $this->loginAsForcedPasswordAdministrator();
        $passwordPage = $this->client->request('GET', 'pages/changerMotPasse.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($passwordPage->body, 'csrf_token');

        $wrongCurrent = $this->client->request('POST', 'pages/changerMotPasse.php', [
            'csrf_token' => $csrf,
            'current_password' => 'WrongCurrent#2026',
            'new_password' => TestCredentials::UPDATED_ADMIN_PASSWORD,
            'new_password_confirmation' => TestCredentials::UPDATED_ADMIN_PASSWORD,
        ]);
        self::assertSame(200, $wrongCurrent->statusCode);
        self::assertStringContainsString('Le mot de passe actuel est incorrect.', $wrongCurrent->body);

        $passwordPage = $this->client->request('GET', 'pages/changerMotPasse.php');
        $csrf = TestEnvironment::extractHiddenFieldValue($passwordPage->body, 'csrf_token');

        $weakPassword = $this->client->request('POST', 'pages/changerMotPasse.php', [
            'csrf_token' => $csrf,
            'current_password' => TestCredentials::FORCED_ADMIN_PASSWORD,
            'new_password' => 'weakpass',
            'new_password_confirmation' => 'weakpass',
        ]);
        self::assertSame(200, $weakPassword->statusCode);
        self::assertStringContainsString('Le nouveau mot de passe doit contenir au moins 12 caractères.', $weakPassword->body);
    }

    public function testLogoutRequiresPostAndInvalidatesTheSession(): void
    {
        $login = $this->loginAsPrimaryAdministrator();
        self::assertSame(303, $login->statusCode);

        $dashboard = $this->followRedirect($login);
        $csrf = TestEnvironment::extractHiddenFieldValue($dashboard->body, 'csrf_token');

        $logout = $this->client->request('POST', 'pages/authentification.php', [
            'action' => 'logout',
            'csrf_token' => $csrf,
        ]);

        self::assertSame(303, $logout->statusCode);
        self::assertSame('/gestion_vaccination/pages/authentification.php', $logout->header('location'));

        $postLogoutDashboard = $this->fetchDashboard();
        self::assertSame(302, $postLogoutDashboard->statusCode);
        self::assertSame('/gestion_vaccination/pages/authentification.php', $postLogoutDashboard->header('location'));
    }
}
