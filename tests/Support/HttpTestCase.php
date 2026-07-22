<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class HttpTestCase extends TestCase
{
    protected HttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        TestEnvironment::rebuildTestDatabase();
        TestEnvironment::resetSessionState();
        $this->client = TestEnvironment::newHttpClient();
    }

    protected function loginAsPrimaryAdministrator(): HttpResponse
    {
        return $this->login(TestCredentials::PRIMARY_ADMIN_LOGIN, TestCredentials::PRIMARY_ADMIN_PASSWORD);
    }

    protected function loginAsForcedPasswordAdministrator(): HttpResponse
    {
        return $this->login(TestCredentials::FORCED_ADMIN_LOGIN, TestCredentials::FORCED_ADMIN_PASSWORD);
    }

    protected function login(string $login, string $password): HttpResponse
    {
        return $this->client->request('POST', 'pages/authentification.php', [
            'action' => 'login',
            'login' => $login,
            'motPass' => $password,
        ]);
    }

    protected function followRedirect(HttpResponse $response): HttpResponse
    {
        $location = $response->header('location');
        self::assertNotNull($location, 'Expected a redirect location header.');

        return $this->client->request('GET', $location);
    }

    protected function fetchDashboard(): HttpResponse
    {
        return $this->client->request('GET', 'index.php');
    }
}
