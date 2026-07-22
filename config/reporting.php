<?php

declare(strict_types=1);

function vaccinatorActivitySql(): string
{
    return 'SELECT v.cnieVaccinateur, v.nomVacc, v.prenomVacc, '
        . 'c.intituleCent, c.villeCent, '
        . 'COUNT(vac.idVaccination) AS total_injections '
        . 'FROM vaccinateurs v '
        . 'LEFT JOIN centres c ON v.idCentre = c.idCentre '
        . 'LEFT JOIN vaccination vac ON vac.cnieVaccinateur = v.cnieVaccinateur '
        . 'GROUP BY v.cnieVaccinateur, v.nomVacc, v.prenomVacc, '
        . 'c.intituleCent, c.villeCent '
        . 'ORDER BY total_injections DESC, v.nomVacc ASC, '
        . 'v.prenomVacc ASC, v.cnieVaccinateur ASC';
}

/**
 * @return array{
 *   rows:list<array<string,mixed>>,
 *   summary:array{
 *     registeredVaccinators:int,
 *     recordedVaccinations:int,
 *     vaccinatorsWithActivity:int,
 *     vaccinatorsWithoutActivity:int
 *   }
 * }
 */
function loadVaccinatorActivityReport(mysqli $connection): array
{
    $rows = [];
    $summary = [
        'registeredVaccinators' => 0,
        'recordedVaccinations' => 0,
        'vaccinatorsWithActivity' => 0,
        'vaccinatorsWithoutActivity' => 0,
    ];

    $result = $connection->query(vaccinatorActivitySql());

    while ($row = $result->fetch_assoc()) {
        $injectionCount = filter_var(
            $row['total_injections'] ?? null,
            FILTER_VALIDATE_INT
        );
        if ($injectionCount === false || $injectionCount < 0) {
            throw new UnexpectedValueException('Invalid aggregate count.');
        }

        $row['total_injections'] = (int) $injectionCount;
        $rows[] = $row;
        $summary['recordedVaccinations'] += $row['total_injections'];

        if ($row['total_injections'] > 0) {
            $summary['vaccinatorsWithActivity']++;
        } else {
            $summary['vaccinatorsWithoutActivity']++;
        }
    }

    $summary['registeredVaccinators'] = count($rows);

    return ['rows' => $rows, 'summary' => $summary];
}
