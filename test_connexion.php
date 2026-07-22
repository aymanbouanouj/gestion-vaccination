<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config/connexion.php';

$result = $conn->query('SELECT 1 AS ok')->fetch_assoc();

if ((int) ($result['ok'] ?? 0) !== 1) {
    fwrite(STDERR, "Database connection check failed.\n");
    exit(1);
}

fwrite(STDOUT, "Database connection: OK\n");
