<?php

declare(strict_types=1);

use Tests\Support\TestEnvironment;

require dirname(__DIR__) . '/vendor/autoload.php';

$relayProcessLog = static function (string $path, bool $toStderr = false): void {
    clearstatcache(true, $path);
    if (!is_file($path) || filesize($path) === 0) {
        return;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd /c type ' . escapeshellarg($path);
        if ($toStderr) {
            $command .= ' 1>&2';
        }
        passthru($command);
        return;
    }

    $command = 'cat ' . escapeshellarg($path);
    if ($toStderr) {
        $command .= ' >&2';
    }
    passthru($command);
};

$allowedGroups = ['all', 'unit', 'integration', 'http', 'security'];
$group = $argv[1] ?? 'all';

if (!in_array($group, $allowedGroups, true)) {
    fwrite(STDERR, "Unknown test group: {$group}\n");
    exit(2);
}

$runtime = TestEnvironment::createRuntime($group);
$server = null;
$exitCode = 1;

try {
    TestEnvironment::rebuildTestDatabase();

    if (in_array($group, ['all', 'http'], true)) {
        $server = TestEnvironment::startHttpServer();
    }

    $phpunit = TestEnvironment::projectRoot() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit';
    if (!is_file($phpunit)) {
        throw new RuntimeException('PHPUnit executable is unavailable. Run composer install first.');
    }

    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($phpunit)
        . ' --configuration '
        . escapeshellarg(TestEnvironment::projectRoot() . DIRECTORY_SEPARATOR . 'phpunit.xml.dist');

    if ($group !== 'all') {
        $command .= ' --group ' . escapeshellarg($group);
    }

    $stdoutLog = TestEnvironment::runtimePath('phpunit.stdout.log');
    $stderrLog = TestEnvironment::runtimePath('phpunit.stderr.log');
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $stdoutLog, 'w'],
        2 => ['file', $stderrLog, 'w'],
    ];

    $phpunitProcess = proc_open($command, $descriptors, $pipes, TestEnvironment::projectRoot());
    if (!is_resource($phpunitProcess)) {
        throw new RuntimeException('Unable to launch PHPUnit.');
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $exitCode = proc_close($phpunitProcess);

    $relayProcessLog($stdoutLog, false);
    $relayProcessLog($stderrLog, true);

    clearstatcache(true, $stdoutLog);
    clearstatcache(true, $stderrLog);
    $stdoutSize = is_file($stdoutLog) ? (int) filesize($stdoutLog) : 0;
    $stderrSize = is_file($stderrLog) ? (int) filesize($stderrLog) : 0;
    if ($stdoutSize === 0 && $stderrSize === 0) {
        $statusMessage = $exitCode === 0
            ? 'completed successfully'
            : 'failed';
        echo 'PHPUnit group "' . $group . '" ' . $statusMessage . ' (exit code ' . $exitCode . ').' . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if (is_array($server) && isset($server['process'])) {
        TestEnvironment::stopHttpServer($server);
    }

    TestEnvironment::dropTestDatabaseArtifacts();
    TestEnvironment::cleanupRuntimeDirectory();
}

exit($exitCode);
