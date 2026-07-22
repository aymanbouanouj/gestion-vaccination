<?php

declare(strict_types=1);

namespace Tests\Support;

use mysqli;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected mysqli $connection;

    protected function setUp(): void
    {
        parent::setUp();

        TestEnvironment::rebuildTestDatabase();
        TestEnvironment::resetSessionState();
        $this->connection = TestEnvironment::applicationConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }

    protected function countRows(string $table, string $whereClause = '1 = 1'): int
    {
        $result = $this->connection->query(
            'SELECT COUNT(*) AS total FROM ' . $table . ' WHERE ' . $whereClause
        )->fetch_assoc();

        return (int) ($result['total'] ?? 0);
    }
}
