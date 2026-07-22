<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestEnvironment;

#[Group('integration')]
final class CreateAdminScriptIntegrationTest extends DatabaseTestCase
{
    public function testCreateAdminScriptCreatesAHardenedAdministrator(): void
    {
        $script = TestEnvironment::projectRoot() . '/scripts/create_admin.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, TestEnvironment::projectRoot());
        self::assertIsResource($process);

        $input = implode(PHP_EOL, [
            'Release',
            'Admin',
            'release-admin@example.test',
            'release_admin',
            'Release-Admin-2026!',
            'Release-Admin-2026!',
            '',
        ]) . PHP_EOL;

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, (string) $stderr);
        self::assertStringContainsString('Administrator created successfully.', (string) $stdout);

        $statement = $this->connection->prepare(
            'SELECT login, motPass, mustChangePassword, passwordChangedAt '
            . 'FROM agentAdmins WHERE login = ? LIMIT 1'
        );
        $login = 'release_admin';
        $statement->bind_param('s', $login);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();

        self::assertIsArray($row);
        self::assertSame('release_admin', $row['login']);
        self::assertSame('1', (string) $row['mustChangePassword']);
        self::assertSame(null, $row['passwordChangedAt']);
        self::assertTrue(password_verify('Release-Admin-2026!', (string) $row['motPass']));
    }
}
