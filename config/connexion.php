<?php

declare(strict_types=1);

function vaccinationDatabaseUnavailable(): never
{
    http_response_code(500);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Service temporairement indisponible.\n");
        exit(1);
    }

    exit('Service temporairement indisponible.');
}

$privateConfigPath = getenv('GESTION_VACCINATION_DB_CONFIG');

if (!is_string($privateConfigPath) || trim($privateConfigPath) === '') {
    $privateConfigPath = dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR . 'private_config'
        . DIRECTORY_SEPARATOR . 'gestion_vaccination'
        . DIRECTORY_SEPARATOR . 'database.php';
}

if (!is_file($privateConfigPath) || !is_readable($privateConfigPath)) {
    error_log('[gestion_vaccination] Private database configuration is unavailable.');
    vaccinationDatabaseUnavailable();
}

try {
    $databaseConfig = require $privateConfigPath;
} catch (Throwable $exception) {
    error_log('[gestion_vaccination] Private database configuration could not be loaded.');
    vaccinationDatabaseUnavailable();
}

$requiredKeys = ['host', 'username', 'password', 'database'];

if (!is_array($databaseConfig)) {
    error_log('[gestion_vaccination] Private database configuration has an invalid format.');
    vaccinationDatabaseUnavailable();
}

foreach ($requiredKeys as $requiredKey) {
    if (!array_key_exists($requiredKey, $databaseConfig)
        || !is_string($databaseConfig[$requiredKey])
        || $databaseConfig[$requiredKey] === '') {
        error_log('[gestion_vaccination] Private database configuration is incomplete.');
        vaccinationDatabaseUnavailable();
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $databaseConfig['host'],
        $databaseConfig['username'],
        $databaseConfig['password'],
        $databaseConfig['database']
    );
    $conn->set_charset('utf8mb4');

    // Keep strict behavior local to this application connection. These modes
    // are supported by the deployed MariaDB 10.4 server and retain the
    // existing zero-date protections.
    $requiredSqlModes = [
        'STRICT_TRANS_TABLES',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ZERO_IN_DATE',
        'NO_ZERO_DATE',
        'NO_ENGINE_SUBSTITUTION',
    ];
    $conn->query(
        "SET SESSION sql_mode = '"
        . implode(',', $requiredSqlModes)
        . "'"
    );

    $sqlModeRow = $conn->query(
        'SELECT @@SESSION.sql_mode AS sessionSqlMode'
    )->fetch_assoc();
    $activeSqlModes = array_filter(array_map(
        'trim',
        explode(',', (string) ($sqlModeRow['sessionSqlMode'] ?? ''))
    ));

    foreach ($requiredSqlModes as $requiredSqlMode) {
        if (!in_array($requiredSqlMode, $activeSqlModes, true)) {
            throw new RuntimeException('Required database SQL mode is unavailable.');
        }
    }
} catch (Throwable $exception) {
    error_log('[gestion_vaccination] Database connection failed.');
    vaccinationDatabaseUnavailable();
}
