<?php

declare(strict_types=1);

namespace Tests\Support;

use DOMDocument;
use mysqli;
use RuntimeException;
use Throwable;

final class TestEnvironment
{
    public const LIVE_DATABASE_NAME = 'compagnevaccination';
    public const DEFAULT_TEST_DATABASE_NAME = 'compagnevaccination_test';
    public const TEST_MARKER_TABLE = '__gv_test_marker';
    public const TEST_MARKER_VALUE = 'gestion_vaccination_isolated_test';

    /**
     * @return array{runtimeDir:string,dbConfigPath:string,securityConfigPath:string,group:string}
     */
    public static function createRuntime(string $group): array
    {
        $runtimeDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'gestion_vaccination_tests_'
            . date('Ymd_His')
            . '_'
            . bin2hex(random_bytes(4));

        if (!mkdir($runtimeDir, 0777, true) && !is_dir($runtimeDir)) {
            throw new RuntimeException('Unable to create the test runtime directory.');
        }

        self::setEnvironmentVariable('GV_TEST_RUNTIME_DIR', $runtimeDir);
        self::setEnvironmentVariable('GV_TEST_GROUP', $group);

        $dbName = getenv('GV_TEST_DB_NAME');
        if (!is_string($dbName) || trim($dbName) === '') {
            $dbName = self::DEFAULT_TEST_DATABASE_NAME;
        }
        self::assertSafeDatabaseName($dbName);

        $dbHost = getenv('GV_TEST_DB_HOST');
        if (!is_string($dbHost) || trim($dbHost) === '') {
            $dbHost = '127.0.0.1';
        }

        $dbConfig = [
            'host' => $dbHost,
            'username' => getenv('GV_TEST_DB_APP_USER') ?: 'gestion_vaccination_test_app',
            'password' => bin2hex(random_bytes(12)) . 'Aa1!',
            'database' => $dbName,
        ];

        $dbConfigPath = $runtimeDir . DIRECTORY_SEPARATOR . 'database.php';
        file_put_contents(
            $dbConfigPath,
            "<?php\n\nreturn " . var_export($dbConfig, true) . ";\n"
        );
        self::setEnvironmentVariable('GESTION_VACCINATION_DB_CONFIG', $dbConfigPath);

        $securityConfig = [
            'fingerprint_key' => bin2hex(random_bytes(32)),
        ];
        $securityConfigPath = $runtimeDir . DIRECTORY_SEPARATOR . 'security.php';
        file_put_contents(
            $securityConfigPath,
            "<?php\n\nreturn " . var_export($securityConfig, true) . ";\n"
        );
        self::setEnvironmentVariable('GESTION_VACCINATION_SECURITY_CONFIG', $securityConfigPath);

        return [
            'runtimeDir' => $runtimeDir,
            'dbConfigPath' => $dbConfigPath,
            'securityConfigPath' => $securityConfigPath,
            'group' => $group,
        ];
    }

    public static function bootstrap(): void
    {
        $runtimeDir = getenv('GV_TEST_RUNTIME_DIR');
        if (!is_string($runtimeDir) || $runtimeDir === '' || !is_dir($runtimeDir)) {
            throw new RuntimeException('The isolated test runtime is missing. Run tests through tests/runner.php.');
        }

        $sessionDirectory = self::runtimePath('cli-sessions');
        if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0777, true) && !is_dir($sessionDirectory)) {
            throw new RuntimeException('Unable to create the CLI session directory.');
        }

        ini_set('session.save_path', $sessionDirectory);
        date_default_timezone_set('UTC');

        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? '80';
        $_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? '';

        self::databaseConfig();
        self::securityConfig();
    }

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function runtimePath(string $suffix): string
    {
        $runtimeDir = getenv('GV_TEST_RUNTIME_DIR');
        if (!is_string($runtimeDir) || $runtimeDir === '') {
            throw new RuntimeException('GV_TEST_RUNTIME_DIR is not configured.');
        }

        return $runtimeDir . DIRECTORY_SEPARATOR . $suffix;
    }

    /**
     * @return array{host:string,username:string,password:string,database:string}
     */
    public static function databaseConfig(): array
    {
        $path = getenv('GESTION_VACCINATION_DB_CONFIG');
        if (!is_string($path) || $path === '' || !is_file($path)) {
            throw new RuntimeException('The isolated database configuration file is missing.');
        }

        /** @var mixed $config */
        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('The isolated database configuration is invalid.');
        }

        foreach (['host', 'username', 'password', 'database'] as $key) {
            if (!array_key_exists($key, $config) || !is_string($config[$key])) {
                throw new RuntimeException('The isolated database configuration is incomplete.');
            }
        }

        self::assertSafeDatabaseName($config['database']);

        return $config;
    }

    /**
     * @return array{fingerprint_key:string}
     */
    public static function securityConfig(): array
    {
        $path = getenv('GESTION_VACCINATION_SECURITY_CONFIG');
        if (!is_string($path) || $path === '' || !is_file($path)) {
            throw new RuntimeException('The isolated security configuration file is missing.');
        }

        /** @var mixed $config */
        $config = require $path;
        if (!is_array($config)
            || !is_string($config['fingerprint_key'] ?? null)
            || strlen($config['fingerprint_key']) !== 64
            || !ctype_xdigit($config['fingerprint_key'])) {
            throw new RuntimeException('The isolated security configuration is invalid.');
        }

        return $config;
    }

    /**
     * @return array{host:string,user:string,password:string,port:int}
     */
    public static function adminDatabaseConfig(): array
    {
        $defaultHost = getenv('GV_TEST_DB_HOST');
        $host = getenv('GV_TEST_DB_ADMIN_HOST');
        $user = getenv('GV_TEST_DB_ADMIN_USER');
        $password = getenv('GV_TEST_DB_ADMIN_PASSWORD');
        $port = getenv('GV_TEST_DB_ADMIN_PORT');

        return [
            'host' => is_string($host) && $host !== ''
                ? $host
                : (is_string($defaultHost) && $defaultHost !== '' ? $defaultHost : '127.0.0.1'),
            'user' => is_string($user) && $user !== '' ? $user : 'root',
            'password' => is_string($password) ? $password : '',
            'port' => is_string($port) && ctype_digit($port) ? (int) $port : 3306,
        ];
    }

    public static function rebuildTestDatabase(): void
    {
        $dbConfig = self::databaseConfig();
        self::assertSafeDatabaseName($dbConfig['database']);

        $admin = self::adminConnection();
        $quotedDatabase = self::quoteIdentifier($dbConfig['database']);
        $quotedUser = self::quoteIdentifier($dbConfig['username']);
        $userHosts = self::testDatabaseUserHosts($dbConfig['host']);

        $admin->query("DROP DATABASE IF EXISTS {$quotedDatabase}");
        $admin->query(
            "CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
        );
        foreach ($userHosts as $userHost) {
            $quotedUserHost = self::quoteSqlString($admin, $userHost);
            $quotedPassword = self::quoteSqlString($admin, $dbConfig['password']);
            $admin->query("DROP USER IF EXISTS {$quotedUser}@{$quotedUserHost}");
            $admin->query(
                "CREATE USER {$quotedUser}@{$quotedUserHost} IDENTIFIED BY {$quotedPassword}"
            );
            $admin->query("GRANT ALL PRIVILEGES ON {$quotedDatabase}.* TO {$quotedUser}@{$quotedUserHost}");
        }
        $admin->query('FLUSH PRIVILEGES');

        $connection = self::applicationConnection();
        self::executeSqlFile($connection, self::projectRoot() . '/tests/fixtures/sql/001_base_schema.sql');
        self::executeSqlFile($connection, self::projectRoot() . '/database/migrations/20260720_01_database_integrity_hardening.sql');
        self::executeSqlFile($connection, self::projectRoot() . '/tests/fixtures/sql/010_seed_reference_data.sql');
        self::seedSyntheticAdministrators($connection);
        self::createTestMarker($connection);
        self::assertMarkerPresent($connection);
        $connection->close();
        $admin->close();
    }

    public static function dropTestDatabaseArtifacts(): void
    {
        $dbConfig = self::databaseConfig();
        $admin = self::adminConnection();
        $quotedDatabase = self::quoteIdentifier($dbConfig['database']);
        $quotedUser = self::quoteIdentifier($dbConfig['username']);
        $userHosts = self::testDatabaseUserHosts($dbConfig['host']);

        try {
            foreach ($userHosts as $userHost) {
                $quotedUserHost = self::quoteSqlString($admin, $userHost);
                $admin->query("DROP USER IF EXISTS {$quotedUser}@{$quotedUserHost}");
            }
        } catch (Throwable) {
        }

        if (getenv('GV_TEST_KEEP_DATABASE') !== '1') {
            try {
                $admin->query("DROP DATABASE IF EXISTS {$quotedDatabase}");
            } catch (Throwable) {
            }
        }

        $admin->close();
    }

    public static function cleanupRuntimeDirectory(): void
    {
        $runtimeDir = getenv('GV_TEST_RUNTIME_DIR');
        if (!is_string($runtimeDir) || $runtimeDir === '' || !is_dir($runtimeDir)) {
            return;
        }

        self::removePath($runtimeDir);

        if (!is_dir($runtimeDir)) {
            return;
        }

        for ($attempt = 0; $attempt < 10 && is_dir($runtimeDir); $attempt++) {
            usleep(100000);
            clearstatcache(true, $runtimeDir);
            @rmdir($runtimeDir);
        }
    }

    public static function applicationConnection(): mysqli
    {
        $dbConfig = self::databaseConfig();

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $connection = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database']
        );

        $connection->set_charset('utf8mb4');
        $connection->query(
            "SET SESSION sql_mode = '"
            . implode(',', self::requiredSqlModes())
            . "'"
        );

        return $connection;
    }

    public static function resetSessionState(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            @session_destroy();
        }

        $_COOKIE = [];
        $_SESSION = [];
        @session_id(bin2hex(random_bytes(16)));
        @session_name('gestion_vaccination_session');
        @session_start();
        $_SESSION = [];
    }

    /**
     * @return array{process:resource,baseUrl:string,logPath:string,port:int,sessionDirectory:string}
     */
    public static function startHttpServer(): array
    {
        $sessionDirectory = self::runtimePath('http-sessions');
        if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0777, true) && !is_dir($sessionDirectory)) {
            throw new RuntimeException('Unable to create the HTTP session directory.');
        }

        $port = self::pickFreePort();
        $documentRoot = dirname(self::projectRoot());
        $logPath = self::runtimePath('php-server.log');
        $command = escapeshellarg(PHP_BINARY)
            . ' -d session.save_path=' . escapeshellarg($sessionDirectory)
            . ' -S 127.0.0.1:' . $port
            . ' -t '
            . escapeshellarg($documentRoot);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ];

        $process = proc_open($command, $descriptors, $pipes, self::projectRoot());
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the isolated PHP test server.');
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $baseUrl = 'http://127.0.0.1:' . $port . '/gestion_vaccination';
        self::setEnvironmentVariable('GV_TEST_BASE_URL', $baseUrl);
        self::waitForServer($baseUrl . '/pages/authentification.php');

        return [
            'process' => $process,
            'baseUrl' => $baseUrl,
            'logPath' => $logPath,
            'port' => $port,
            'sessionDirectory' => $sessionDirectory,
        ];
    }

    /**
     * @param array{process:resource,baseUrl:string,logPath:string,port:int,sessionDirectory:string} $server
     */
    public static function stopHttpServer(array $server): void
    {
        $process = $server['process'];
        if (!is_resource($process)) {
            return;
        }

        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process);
            usleep(250000);
            $status = proc_get_status($process);

            if ($status['running'] && isset($status['pid'])) {
                @exec('taskkill /PID ' . (int) $status['pid'] . ' /T /F');
                usleep(250000);
                $status = proc_get_status($process);
            }
        }

        self::terminateHttpServerBySignature($server['sessionDirectory'], $server['port']);
        @proc_close($process);
    }

    public static function newHttpClient(): HttpClient
    {
        $baseUrl = getenv('GV_TEST_BASE_URL');
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('GV_TEST_BASE_URL is not configured.');
        }

        return new HttpClient($baseUrl);
    }

    public static function extractHiddenFieldValue(string $html, string $name): string
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $value = null;
        foreach ($dom->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('name') === $name) {
                $value = $input->getAttribute('value');
            }
        }

        if (is_string($value)) {
            return $value;
        }

        throw new RuntimeException('Hidden field not found: ' . $name);
    }

    public static function assertSafeDatabaseName(string $databaseName): void
    {
        if ($databaseName === '') {
            throw new RuntimeException('The test database name must not be empty.');
        }

        if (!str_ends_with($databaseName, '_test')) {
            throw new RuntimeException('The test database name must end with "_test".');
        }

        if ($databaseName === self::LIVE_DATABASE_NAME) {
            throw new RuntimeException('The configured database points to the live database.');
        }
    }

    /**
     * @return list<string>
     */
    public static function requiredSqlModes(): array
    {
        return [
            'STRICT_TRANS_TABLES',
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_ZERO_IN_DATE',
            'NO_ZERO_DATE',
            'NO_ENGINE_SUBSTITUTION',
        ];
    }

    private static function adminConnection(): mysqli
    {
        $config = self::adminDatabaseConfig();

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        return new mysqli(
            $config['host'],
            $config['user'],
            $config['password'],
            '',
            $config['port']
        );
    }

    private static function executeSqlFile(mysqli $connection, string $path): void
    {
        $sql = file_get_contents($path);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('SQL fixture is empty: ' . $path);
        }

        if (!$connection->multi_query($sql)) {
            throw new RuntimeException('Unable to execute SQL fixture: ' . $path);
        }

        do {
            $result = $connection->store_result();
            if ($result !== false) {
                $result->free();
            }
        } while ($connection->more_results() && $connection->next_result());
    }

    private static function seedSyntheticAdministrators(mysqli $connection): void
    {
        $statement = $connection->prepare(
            'INSERT INTO agentAdmins '
            . '(idAdmin, nomAdm, prenomAdm, emailAdm, login, motPass, mustChangePassword, passwordChangedAt) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $rows = [
            [
                1,
                'Admin',
                'Principal',
                'qa-admin@example.test',
                TestCredentials::PRIMARY_ADMIN_LOGIN,
                password_hash(TestCredentials::PRIMARY_ADMIN_PASSWORD, PASSWORD_DEFAULT),
                0,
                '2026-01-10 10:00:00',
            ],
            [
                2,
                'Admin',
                'Force',
                'qa-force@example.test',
                TestCredentials::FORCED_ADMIN_LOGIN,
                password_hash(TestCredentials::FORCED_ADMIN_PASSWORD, PASSWORD_DEFAULT),
                1,
                null,
            ],
        ];

        foreach ($rows as $row) {
            [$id, $nom, $prenom, $email, $login, $passwordHash, $mustChange, $changedAt] = $row;
            if (!is_string($passwordHash)) {
                throw new RuntimeException('Unable to hash a synthetic administrator password.');
            }

            $statement->bind_param(
                'isssssis',
                $id,
                $nom,
                $prenom,
                $email,
                $login,
                $passwordHash,
                $mustChange,
                $changedAt
            );
            $statement->execute();
        }
    }

    private static function createTestMarker(mysqli $connection): void
    {
        $connection->query(
            'CREATE TABLE ' . self::TEST_MARKER_TABLE . ' ('
            . 'markerKey varchar(50) NOT NULL PRIMARY KEY, '
            . 'markerValue varchar(100) NOT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $statement = $connection->prepare(
            'INSERT INTO ' . self::TEST_MARKER_TABLE . ' (markerKey, markerValue) VALUES (?, ?)'
        );
        $markerKey = 'environment';
        $markerValue = self::TEST_MARKER_VALUE;
        $statement->bind_param('ss', $markerKey, $markerValue);
        $statement->execute();
    }

    private static function assertMarkerPresent(mysqli $connection): void
    {
        $statement = $connection->prepare(
            'SELECT markerValue FROM ' . self::TEST_MARKER_TABLE . ' WHERE markerKey = ? LIMIT 1'
        );
        $markerKey = 'environment';
        $statement->bind_param('s', $markerKey);
        $statement->execute();
        $result = $statement->get_result()->fetch_assoc();

        if (($result['markerValue'] ?? null) !== self::TEST_MARKER_VALUE) {
            throw new RuntimeException('The isolated test marker is missing or invalid.');
        }
    }

    private static function pickFreePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!is_resource($socket)) {
            throw new RuntimeException('Unable to reserve a free local port for HTTP tests.');
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if (!is_string($address) || !preg_match('/:(\d+)$/', $address, $matches)) {
            throw new RuntimeException('Unable to determine the reserved test port.');
        }

        return (int) $matches[1];
    }

    private static function waitForServer(string $url): void
    {
        $deadline = microtime(true) + 10.0;
        $lastError = 'The isolated HTTP test server did not start.';

        while (microtime(true) < $deadline) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
            ]);
            $ok = curl_exec($curl);
            $error = curl_error($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if ($ok !== false && $statusCode >= 200 && $statusCode < 500) {
                return;
            }

            if ($error !== '') {
                $lastError = $error;
            }

            usleep(200000);
        }

        throw new RuntimeException('The isolated HTTP test server did not become ready: ' . $lastError);
    }

    private static function setEnvironmentVariable(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private static function removePath(string $path): void
    {
        if (is_dir($path)) {
            $items = scandir($path);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    self::removePath($path . DIRECTORY_SEPARATOR . $item);
                }
            }

            @rmdir($path);
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function quoteSqlString(mysqli $connection, string $value): string
    {
        return "'" . $connection->real_escape_string($value) . "'";
    }

    /**
     * @return list<string>
     */
    private static function testDatabaseUserHosts(string $databaseHost): array
    {
        $normalizedHost = strtolower(trim($databaseHost));
        if ($normalizedHost === '' || $normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1') {
            return ['localhost', '127.0.0.1'];
        }

        return [$normalizedHost];
    }

    private static function terminateHttpServerBySignature(string $sessionDirectory, int $port): void
    {
        $signature = str_replace("'", "''", $sessionDirectory);

        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'powershell -NoProfile -Command '
                . escapeshellarg(
                    "Get-CimInstance Win32_Process | "
                    . "Where-Object { \$_.Name -eq 'php.exe' -and \$_.CommandLine -like '*{$signature}*' -and \$_.CommandLine -like '*127.0.0.1:{$port}*' } | "
                    . 'ForEach-Object { Stop-Process -Id $_.ProcessId -Force }'
                );
            @exec($command);
            return;
        }

        @exec('pkill -f ' . escapeshellarg($sessionDirectory));
    }
}
