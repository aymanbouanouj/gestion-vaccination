<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestEnvironment;

#[Group('integration')]
final class ReportingIntegrationTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once TestEnvironment::projectRoot() . '/config/reporting.php';
    }

    public function testBaseFixtureReportContainsExpectedSummaryAndOrder(): void
    {
        $report = loadVaccinatorActivityReport($this->connection);

        self::assertCount(3, $report['rows']);
        self::assertSame(3, $report['summary']['registeredVaccinators']);
        self::assertSame(3, $report['summary']['recordedVaccinations']);
        self::assertSame(2, $report['summary']['vaccinatorsWithActivity']);
        self::assertSame(1, $report['summary']['vaccinatorsWithoutActivity']);

        self::assertSame('VTESTB0002', $report['rows'][0]['cnieVaccinateur']);
        self::assertSame(2, $report['rows'][0]['total_injections']);
        self::assertSame('VTESTA0001', $report['rows'][1]['cnieVaccinateur']);
        self::assertSame(1, $report['rows'][1]['total_injections']);
        self::assertSame('VTESTC0003', $report['rows'][2]['cnieVaccinateur']);
        self::assertSame(0, $report['rows'][2]['total_injections']);
    }

    public function testReportHandlesAnEmptyVaccinatorDataset(): void
    {
        $this->connection->query('DELETE FROM vaccination');
        $this->connection->query('DELETE FROM vaccinateurs');

        $report = loadVaccinatorActivityReport($this->connection);

        self::assertSame([], $report['rows']);
        self::assertSame(0, $report['summary']['registeredVaccinators']);
        self::assertSame(0, $report['summary']['recordedVaccinations']);
        self::assertSame(0, $report['summary']['vaccinatorsWithActivity']);
        self::assertSame(0, $report['summary']['vaccinatorsWithoutActivity']);
    }
}
