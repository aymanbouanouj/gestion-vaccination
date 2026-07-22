<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestEnvironment;

#[Group('integration')]
final class ThrottlingIntegrationTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once TestEnvironment::projectRoot() . '/config/security.php';
    }

    public function testFailedAttemptsTriggerThrottlingAndCanBeCleared(): void
    {
        $loginHash = loginFingerprint('synthetic_login');
        $ipHash = hash('sha256', 'synthetic-ip-marker');

        for ($index = 0; $index < VACCINATION_LOGIN_FAILURE_LIMIT; $index++) {
            recordFailedLoginAttempt($this->connection, $loginHash, $ipHash);
        }

        self::assertTrue(loginAttemptIsThrottled($this->connection, $loginHash, $ipHash));

        clearRelevantLoginAttempts($this->connection, $loginHash, $ipHash);
        self::assertFalse(loginAttemptIsThrottled($this->connection, $loginHash, $ipHash));
    }

    public function testExpiredAttemptsDoNotBlockIndefinitely(): void
    {
        $loginHash = loginFingerprint('expired_login');
        $ipHash = hash('sha256', 'synthetic-expired-ip');

        for ($index = 0; $index < VACCINATION_LOGIN_FAILURE_LIMIT; $index++) {
            recordFailedLoginAttempt($this->connection, $loginHash, $ipHash);
        }

        $this->connection->query(
            "UPDATE authLoginAttempts SET attemptedAt = NOW(6) - INTERVAL 2 DAY WHERE loginFingerprint = '{$this->connection->real_escape_string($loginHash)}'"
        );

        self::assertFalse(loginAttemptIsThrottled($this->connection, $loginHash, $ipHash));
        purgeExpiredLoginAttempts($this->connection);
        self::assertSame(0, $this->countRows('authLoginAttempts'));
    }

    public function testAuthenticationEventsStoreOnlyApprovedMetadata(): void
    {
        $loginHash = loginFingerprint('event_login');
        $ipHash = hash('sha256', 'synthetic-event-ip');

        self::assertTrue(recordAuthenticationEvent(
            $this->connection,
            'login_failure',
            'invalid_credentials',
            1,
            $loginHash,
            $ipHash
        ));

        $row = $this->connection->query(
            "SELECT loginFingerprint, ipFingerprint, outcome FROM authSecurityEvents ORDER BY idEvent DESC LIMIT 1"
        )->fetch_assoc();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $row['loginFingerprint']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $row['ipFingerprint']);
        self::assertSame('invalid_credentials', $row['outcome']);
        self::assertNotSame('event_login', $row['loginFingerprint']);
    }
}
