<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironment;

#[Group('security')]
final class StaticSecurityTest extends TestCase
{
    public function testForbiddenPublicSecretAndDumpFilesAreAbsent(): void
    {
        $projectRoot = TestEnvironment::projectRoot();
        $forbiddenFiles = [
            $projectRoot . '/config/database.php',
            $projectRoot . '/config/infosConnexion.txt',
            $projectRoot . '/credentials',
            $projectRoot . '/exports',
            $projectRoot . '/database/export.sql',
        ];

        foreach ($forbiddenFiles as $path) {
            self::assertFalse(file_exists($path), $path);
        }
    }

    public function testOperationalRoutesRemainProtectedAndLogoutRemainsPostOnly(): void
    {
        $protectedFiles = [
            'index.php',
            'pages/formAjout.php',
            'pages/centreVaccination.php',
            'pages/performanceVaccinateur.php',
            'pages/changerMotPasse.php',
            'pages/ajoutCitoyen.php',
        ];

        foreach ($protectedFiles as $file) {
            $contents = file_get_contents(TestEnvironment::projectRoot() . '/' . $file);
            self::assertIsString($contents);
            self::assertStringContainsString('requireAuthentication', $contents, $file);
        }

        $authPage = file_get_contents(TestEnvironment::projectRoot() . '/pages/authentification.php');
        self::assertIsString($authPage);
        self::assertStringContainsString("\$requestMethod === 'POST' && \$action === 'logout'", $authPage);
        self::assertStringNotContainsString('href="logout', $authPage);
    }

    public function testRuntimeMarkupHasNoLargeInlineCssBlocksOrRemoteFrontendDependencies(): void
    {
        $files = [
            TestEnvironment::projectRoot() . '/index.php',
            TestEnvironment::projectRoot() . '/pages/authentification.php',
            TestEnvironment::projectRoot() . '/pages/formAjout.php',
            TestEnvironment::projectRoot() . '/pages/centreVaccination.php',
            TestEnvironment::projectRoot() . '/pages/performanceVaccinateur.php',
            TestEnvironment::projectRoot() . '/pages/changerMotPasse.php',
            TestEnvironment::projectRoot() . '/config/layout.php',
            TestEnvironment::projectRoot() . '/assets/css/style.css',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('<style', $contents, $file);
            self::assertStringNotContainsString('cdnjs.cloudflare.com', $contents, $file);
            self::assertStringNotContainsString('jsdelivr', $contents, $file);
            self::assertStringNotContainsString('unpkg.com', $contents, $file);
        }
    }

    public function testViewportAndSharedLayoutHooksRemainPresent(): void
    {
        $layout = file_get_contents(TestEnvironment::projectRoot() . '/config/layout.php');
        self::assertIsString($layout);
        self::assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $layout);
        self::assertStringContainsString('renderPageStart(', $layout);
    }

    public function testNoRawRequestOrDatabaseErrorsAreRenderedDirectly(): void
    {
        $phpFiles = glob(TestEnvironment::projectRoot() . '/config/*.php');
        $phpFiles = array_merge($phpFiles ?: [], glob(TestEnvironment::projectRoot() . '/pages/*.php') ?: [], [TestEnvironment::projectRoot() . '/index.php']);

        foreach ($phpFiles as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('var_dump($_POST', $contents, $file);
            self::assertStringNotContainsString('print_r($_POST', $contents, $file);
            self::assertStringNotContainsString('echo $exception->getMessage()', $contents, $file);
            self::assertStringNotContainsString('mysqli_error(', $contents, $file);
        }
    }

    public function testPasswordIsNotComparedInRawSqlAndPhpSyntaxPasses(): void
    {
        $phpFiles = glob(TestEnvironment::projectRoot() . '/config/*.php');
        $phpFiles = array_merge($phpFiles ?: [], glob(TestEnvironment::projectRoot() . '/pages/*.php') ?: [], [TestEnvironment::projectRoot() . '/index.php', TestEnvironment::projectRoot() . '/test_connexion.php']);

        foreach ($phpFiles as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertSame(0, preg_match('/WHERE[^\\n;]*motPass\\s*=\\s*\\?/i', $contents), $file);

            $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
            exec($command . ' 2>&1', $output, $exitCode);
            self::assertSame(0, $exitCode, implode(PHP_EOL, $output));
        }
    }
}
