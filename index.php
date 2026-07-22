<?php

require_once __DIR__ . '/config/security.php';
requireAuthentication();
require_once __DIR__ . '/config/connexion.php';
require_once __DIR__ . '/config/reporting.php';
require_once __DIR__ . '/config/layout.php';

$nomAdmin = authenticatedAdministratorDisplayName();
$passwordChangeSucceeded = ($_SESSION['password_change_success'] ?? false) === true;
unset($_SESSION['password_change_success']);

$activityOverview = null;

try {
    $activityOverview = loadVaccinatorActivityReport($conn)['summary'];
} catch (Throwable $exception) {
    error_log('[gestion_vaccination] Dashboard activity overview unavailable [code=DASHBOARD_REPORT_DB].');
}

renderPageStart(
    'Tableau de bord',
    'Gestion Vaccination',
    'dashboard',
    true,
    'dashboard-page',
    '',
    'Accedez aux fonctions disponibles dans cette demonstration locale sans modifier les routes ni les traitements existants.'
);
?>

<?php if ($passwordChangeSucceeded): ?>
    <?php renderFeedback('success', ['Le mot de passe a ete modifie avec succes.']); ?>
<?php endif; ?>

<section class="dashboard-hero card card--hero" aria-labelledby="dashboard-hero-title" data-reveal>
    <div class="dashboard-hero__content">
        <p class="dashboard-hero__eyebrow">Prototype pedagogique local</p>
        <h1 id="dashboard-hero-title">Gestion Vaccination</h1>
        <p class="dashboard-hero__lead">Interface locale d'administration pour l'inscription citoyenne, la recherche de centre, le suivi d'activite et la gestion securisee du mot de passe.</p>

        <div class="hero-actions">
            <a class="button button--primary" href="<?php echo escapeHtml(VACCINATION_BASE_PATH . '/pages/formAjout.php'); ?>">Inscrire un citoyen</a>
            <a class="button button--secondary button--secondary-light" href="<?php echo escapeHtml(VACCINATION_BASE_PATH . '/pages/centreVaccination.php'); ?>">Rechercher un centre</a>
        </div>

        <ul class="hero-highlights">
            <li><span class="hero-highlights__icon"><?php renderInlineIcon('shield'); ?></span><span>Authentification et sessions protegees.</span></li>
            <li><span class="hero-highlights__icon"><?php renderInlineIcon('validation'); ?></span><span>Validation serveur et parcours deja implementes.</span></li>
            <li><span class="hero-highlights__icon"><?php renderInlineIcon('responsive'); ?></span><span>Interface responsive pour demonstration locale.</span></li>
        </ul>
    </div>

    <aside class="dashboard-hero__aside" aria-label="Resume du contexte applicatif">
        <div class="dashboard-hero__identity">
            <img
                class="dashboard-hero__logo"
                src="<?php echo escapeHtml(vaccinationAssetPath('branding/logo-full-light.png')); ?>"
                alt="Identite visuelle Gestion Vaccination"
                width="104"
                height="104"
            >
            <div class="dashboard-hero__identity-copy">
                <p class="dashboard-hero__meta">Session administrateur</p>
                <h2>Bienvenue, <?php echo $nomAdmin; ?></h2>
                <p>Perimetre borne a l'authentification administrateur, l'inscription citoyenne, la recherche de centre, le rapport d'activite et le cycle de mot de passe.</p>
            </div>
        </div>

        <div class="dashboard-hero__panel">
            <p class="dashboard-hero__panel-label">Cadre d'usage</p>
            <ul class="dashboard-hero__panel-list">
                <li>Aucune fonction supplementaire n'est simulee.</li>
                <li>Les donnees affichees proviennent des traitements deja presents.</li>
                <li>Le prototype ne revendique aucune affiliation officielle.</li>
            </ul>
        </div>
    </aside>
</section>

<section class="page-section" aria-labelledby="actions-title" data-reveal>
    <div class="section-heading">
        <p class="section-kicker">Acces rapide</p>
        <h2 id="actions-title">Actions disponibles</h2>
        <p>Chaque acces correspond a une fonction deja presente dans l'application. Aucun module supplementaire n'est simule.</p>
    </div>

    <div class="action-grid">
        <article class="action-card action-card--featured">
            <span class="action-card__icon"><?php renderInlineIcon('citizen'); ?></span>
            <p class="action-card__label">Priorite</p>
            <h3>Inscrire un citoyen</h3>
            <p>Enregistrer un nouveau dossier citoyen et son centre attribue avec les champs et les validations deja attendus par l'application.</p>
            <a class="action-card__link" href="<?php echo escapeHtml(VACCINATION_BASE_PATH . '/pages/formAjout.php'); ?>">Acceder au formulaire <span aria-hidden="true"><?php renderInlineIcon('arrow'); ?></span></a>
        </article>

        <article class="action-card">
            <span class="action-card__icon"><?php renderInlineIcon('centre'); ?></span>
            <p class="action-card__label">Recherche</p>
            <h3>Rechercher un centre</h3>
            <p>Consulter le centre deja associe a un citoyen a partir d'un CNIE, sans carte, contact ni information non stockee.</p>
            <a class="action-card__link" href="<?php echo escapeHtml(VACCINATION_BASE_PATH . '/pages/centreVaccination.php'); ?>">Lancer la recherche <span aria-hidden="true"><?php renderInlineIcon('arrow'); ?></span></a>
        </article>

        <article class="action-card">
            <span class="action-card__icon"><?php renderInlineIcon('report'); ?></span>
            <p class="action-card__label">Suivi</p>
            <h3>Activite des vaccinateurs</h3>
            <p>Afficher le rapport existant sur les vaccinations enregistrees, avec ses totaux reels et sa terminologie neutre.</p>
            <a class="action-card__link" href="<?php echo escapeHtml(VACCINATION_BASE_PATH . '/pages/performanceVaccinateur.php'); ?>">Voir le rapport <span aria-hidden="true"><?php renderInlineIcon('arrow'); ?></span></a>
        </article>

        <article class="action-card">
            <span class="action-card__icon"><?php renderInlineIcon('password'); ?></span>
            <p class="action-card__label">Securite</p>
            <h3>Changer le mot de passe</h3>
            <p>Mettre a jour le mot de passe administrateur dans le cadre de la politique de securite deja en place.</p>
            <a class="action-card__link" href="<?php echo escapeHtml(VACCINATION_PASSWORD_CHANGE_PATH); ?>">Mettre a jour <span aria-hidden="true"><?php renderInlineIcon('arrow'); ?></span></a>
        </article>
    </div>
</section>

<?php if ($activityOverview !== null): ?>
    <section class="page-section" aria-labelledby="overview-title" data-reveal>
        <div class="section-heading">
            <p class="section-kicker">Vue d'ensemble</p>
            <h2 id="overview-title">Activite enregistree</h2>
            <p>Les valeurs suivantes proviennent du rapport deja en service, sans recalcul alternatif ni statistique inventee.</p>
        </div>

        <dl class="summary-grid" aria-label="Resume de l'activite enregistree">
            <div class="summary-card">
                <dt>Vaccinateurs enregistres</dt>
                <dd><?php echo escapeHtml($activityOverview['registeredVaccinators']); ?></dd>
            </div>
            <div class="summary-card">
                <dt>Vaccinations enregistrees</dt>
                <dd><?php echo escapeHtml($activityOverview['recordedVaccinations']); ?></dd>
            </div>
            <div class="summary-card">
                <dt>Avec au moins une vaccination</dt>
                <dd><?php echo escapeHtml($activityOverview['vaccinatorsWithActivity']); ?></dd>
            </div>
            <div class="summary-card">
                <dt>Sans vaccination</dt>
                <dd><?php echo escapeHtml($activityOverview['vaccinatorsWithoutActivity']); ?></dd>
            </div>
        </dl>
    </section>
<?php endif; ?>

<section class="page-section" aria-labelledby="trust-title" data-reveal>
    <div class="section-heading">
        <p class="section-kicker">Cadre institutionnel</p>
        <h2 id="trust-title">Fonctions factuelles et protections en place</h2>
        <p>Les points suivants decrivent des capacites deja presentes dans l'application. Ils ne constituent pas une certification officielle.</p>
    </div>

    <div class="trust-grid">
        <article class="trust-card">
            <span class="trust-card__icon"><?php renderInlineIcon('shield'); ?></span>
            <h3>Authentification securisee</h3>
            <p>Acces reserve a l'administration avec deconnexion en POST et jetons CSRF.</p>
        </article>

        <article class="trust-card">
            <span class="trust-card__icon"><?php renderInlineIcon('validation'); ?></span>
            <h3>Validation serveur</h3>
            <p>Les traitements conservent leurs controles de format, de taille et de coherence.</p>
        </article>

        <article class="trust-card">
            <span class="trust-card__icon"><?php renderInlineIcon('data'); ?></span>
            <h3>Donnees reelles du prototype</h3>
            <p>Les vues de synthese exploitent uniquement les requetes et helpers deja existants.</p>
        </article>

        <article class="trust-card">
            <span class="trust-card__icon"><?php renderInlineIcon('responsive'); ?></span>
            <h3>Interface responsive</h3>
            <p>Le parcours reste consultable sur ecran mobile, tablette et ordinateur sans changer la logique metier.</p>
        </article>
    </div>
</section>

<?php renderPageEnd(); ?>
