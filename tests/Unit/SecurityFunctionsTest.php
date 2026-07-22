<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironment;

#[Group('unit')]
final class SecurityFunctionsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once TestEnvironment::projectRoot() . '/config/security.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::resetSessionState();
    }

    public function testCsrfTokenLifecycleIsSafeAndConsumptive(): void
    {
        $token = csrfToken('citizen_create');
        self::assertSame(64, strlen($token));
        self::assertTrue(verifyCsrfToken('citizen_create', $token));
        self::assertFalse(verifyCsrfToken('citizen_create', $token));

        $newToken = csrfToken('citizen_create');
        self::assertFalse(hash_equals($token, $newToken));

        rotateCsrfTokens();
        self::assertFalse(verifyCsrfToken('citizen_create', $newToken));
        self::assertFalse(verifyCsrfToken('citizen_create', null));
        self::assertFalse(verifyCsrfToken('citizen_create', 'invalid-token'));
    }

    public function testPasswordPolicyRejectsWeakPasswords(): void
    {
        self::assertNotEmpty(validateAdministratorPassword('short', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('alllowercase123!', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('ALLUPPERCASE123!', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('NoDigitsHere!', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('NoSymbol1234', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword(' qa_admin#123A', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('Current#123', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('qa_admin', 'Current#123', 'qa_admin'));
        self::assertNotEmpty(validateAdministratorPassword('Password123!', 'Current#123', 'qa_admin'));
    }

    public function testPasswordPolicyAcceptsStrongPassword(): void
    {
        $errors = validateAdministratorPassword('Forte#2026Alpha', 'Current#123', 'qa_admin');

        self::assertSame([], $errors);
    }

    public function testAuthenticatedSessionFreshnessDependsOnTimestamps(): void
    {
        $_SESSION[VACCINATION_SESSION_KEY] = [
            'id' => 1,
            'login' => 'qa_admin',
            'display_name' => 'Admin Test',
            'must_change_password' => false,
            'authenticated_at' => time() - 120,
            'last_activity_at' => time() - 60,
        ];

        self::assertTrue(isAuthenticated());
        self::assertTrue(authenticatedSessionIsFresh());

        $_SESSION[VACCINATION_SESSION_KEY]['last_activity_at'] = time() - VACCINATION_IDLE_TIMEOUT_SECONDS - 10;
        self::assertFalse(authenticatedSessionIsFresh());

        $_SESSION[VACCINATION_SESSION_KEY]['last_activity_at'] = time();
        $_SESSION[VACCINATION_SESSION_KEY]['authenticated_at'] = time() - VACCINATION_ABSOLUTE_SESSION_SECONDS - 10;
        self::assertFalse(authenticatedSessionIsFresh());
    }
}
