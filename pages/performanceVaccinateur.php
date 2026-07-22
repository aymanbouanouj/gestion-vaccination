<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
requireAuthentication();
require_once __DIR__ . '/../config/application.php';
require_once __DIR__ . '/../config/connexion.php';
require_once __DIR__ . '/../config/reporting.php';
require_once __DIR__ . '/../config/layout.php';

$vaccinators = [];
$queryError = null;
$summary = [
    'registeredVaccinators' => 0,
    'recordedVaccinations' => 0,
    'vaccinatorsWithActivity' => 0,
    'vaccinatorsWithoutActivity' => 0,
];

try {
    $report = loadVaccinatorActivityReport($conn);
    $vaccinators = $report['rows'];
    $summary = $report['summary'];
} catch (Throwable $exception) {
    $vaccinators = [];
    $summary = [
        'registeredVaccinators' => 0,
        'recordedVaccinations' => 0,
        'vaccinatorsWithActivity' => 0,
        'vaccinatorsWithoutActivity' => 0,
    ];
    error_log('[gestion_vaccination] Report query failed [code=VACCINATOR_ACTIVITY_REPORT_DB].');
    $queryError = 'Le rapport est temporairement indisponible.';
}

renderPageStart(
    'Activité des vaccinateurs',
    'Activité enregistrée des vaccinateurs',
    'activity',
    true,
    'report-page',
    '',
    'Ce rapport présente chaque vaccinateur enregistré et le nombre de dossiers de vaccination qui lui sont actuellement associés. Ce décompte ne constitue pas une évaluation professionnelle ou médicale.'
);
?>

<?php if ($queryError !== null): ?>
    <?php renderFeedback('error', [$queryError]); ?>
<?php else: ?>
    <section class="page-section" aria-labelledby="report-summary-title">
        <div class="section-heading">
            <p class="section-kicker">Synthèse</p>
            <h2 id="report-summary-title">Activité réellement enregistrée</h2>
            <p>Les cartes ci-dessous proviennent du helper de reporting existant et conservent ses calculs actuels.</p>
        </div>

        <dl class="summary-grid" aria-label="Resume de l'activite enregistree">
            <div class="summary-card">
                <dt>Vaccinateurs enregistrés</dt>
                <dd><?php echo escapeHtml($summary['registeredVaccinators']); ?></dd>
            </div>
            <div class="summary-card">
                <dt>Vaccinations enregistrées</dt>
                <dd><?php echo escapeHtml($summary['recordedVaccinations']); ?></dd>
            </div>
            <div class="summary-card">
                <dt>Avec au moins une vaccination</dt>
                <dd><?php echo escapeHtml($summary['vaccinatorsWithActivity']); ?></dd>
            </div>
            <div class="summary-card">
                <dt>Sans vaccination</dt>
                <dd><?php echo escapeHtml($summary['vaccinatorsWithoutActivity']); ?></dd>
            </div>
        </dl>
    </section>

    <section class="page-section" aria-labelledby="report-table-title">
        <div class="section-heading">
            <p class="section-kicker">Détail</p>
            <h2 id="report-table-title">Tableau de l'activité par vaccinateur</h2>
            <p>L'ordre d'affichage, les zéro-activités et le libellé du centre d'affectation restent inchangés.</p>
        </div>

        <?php if ($vaccinators === []): ?>
            <div class="empty-state" role="status">
                <p>Aucun vaccinateur n'est actuellement enregistré.</p>
            </div>
        <?php else: ?>
            <div
                class="table-region report-table"
                role="region"
                aria-label="Tableau de l'activité enregistrée des vaccinateurs"
                tabindex="0"
            >
                <table>
                    <caption>
                        Nombre de vaccinations enregistrées par vaccinateur, trié par nombre décroissant puis par identité.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Identifiant du vaccinateur</th>
                            <th scope="col">Nom</th>
                            <th scope="col">Prénom</th>
                            <th scope="col">Centre d'affectation</th>
                            <th scope="col">Ville du centre d'affectation</th>
                            <th scope="col" class="number">Vaccinations enregistrées</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vaccinators as $vaccinator): ?>
                            <?php
                            $assignedCentre = is_string($vaccinator['intituleCent'] ?? null)
                                && $vaccinator['intituleCent'] !== ''
                                ? $vaccinator['intituleCent']
                                : 'Non renseigné';
                            $assignedCentreCity = is_string($vaccinator['villeCent'] ?? null)
                                && $vaccinator['villeCent'] !== ''
                                ? $vaccinator['villeCent']
                                : 'Non renseignée';
                            ?>
                            <tr>
                                <td><?php echo escapeHtml($vaccinator['cnieVaccinateur'] ?? null); ?></td>
                                <td><?php echo escapeHtml($vaccinator['nomVacc'] ?? null); ?></td>
                                <td><?php echo escapeHtml($vaccinator['prenomVacc'] ?? null); ?></td>
                                <td><?php echo escapeHtml($assignedCentre); ?></td>
                                <td><?php echo escapeHtml($assignedCentreCity); ?></td>
                                <td class="number"><strong><?php echo escapeHtml((int) $vaccinator['total_injections']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="page-section page-actions">
    <a class="button button--secondary" href="<?php echo escapeHtml(VACCINATION_DASHBOARD_PATH); ?>">Retour au tableau de bord</a>
</div>

<?php renderPageEnd(); ?>
