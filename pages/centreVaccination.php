<?php

require_once __DIR__ . '/../config/security.php';
requireAuthentication();
require_once __DIR__ . '/../config/application.php';

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit('Méthode non autorisée.');
}

$citizen = null;
$message = null;
$messageType = 'error';
$identifierInvalid = false;

if ($requestMethod === 'POST') {
    if (!verifyCsrfToken('citizen_lookup', $_POST['csrf_token'] ?? null)) {
        rejectInvalidCsrfToken();
    }

    $contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
    $unexpectedFields = array_diff(array_keys($_POST), ['csrf_token', 'cnieCitoyen']);
    $identifier = validateCitizenIdentifier($_POST['cnieCitoyen'] ?? null);

    if (($contentLength !== false && $contentLength > 1024)
        || $unexpectedFields !== []
        || $identifier['error'] !== null) {
        $identifierInvalid = true;
        $message = ($contentLength !== false && $contentLength > 1024)
            ? 'La requête de recherche dépasse la taille autorisée.'
            : ($unexpectedFields !== []
            ? 'La requête de recherche est invalide.'
            : $identifier['error']);
    } else {
        try {
            require_once __DIR__ . '/../config/connexion.php';
            $statement = $conn->prepare(
                'SELECT c.nomCit, c.prenomCit, c.dateNaissanceCit, c.villeCit, '
                . 'ce.intituleCent, ce.adresseCent, ce.villeCent '
                . 'FROM citoyens c '
                . 'JOIN centres ce ON c.idCentre = ce.idCentre '
                . 'WHERE c.cnieCitoyen = ? LIMIT 1'
            );
            $statement->bind_param('s', $identifier['value']);
            $statement->execute();
            $citizen = $statement->get_result()->fetch_assoc();

            if (!is_array($citizen)) {
                $citizen = null;
                $message = 'Aucun citoyen trouvé avec ce CNIE.';
                $messageType = 'info';
            }
        } catch (Throwable $exception) {
            error_log('[gestion_vaccination] Citizen centre lookup failed [code=CITIZEN_LOOKUP_DB].');
            $message = 'La recherche est temporairement indisponible.';
        }
    }
}

$lookupToken = csrfToken('citizen_lookup');
require_once __DIR__ . '/../config/layout.php';

renderPageStart(
    'Rechercher un centre',
    'Rechercher le centre attribué',
    'centre-lookup',
    true,
    '',
    'page-shell--form',
    'La recherche affiche uniquement les informations déjà prévues par cette application.'
);
?>

<?php if ($message !== null): ?>
    <?php renderFeedback($messageType, [$message], 'lookup-feedback'); ?>
<?php endif; ?>

<section class="page-grid page-grid--wide" aria-label="Recherche de centre">
    <section class="card" aria-labelledby="lookup-form-title">
        <h2 class="card__title" id="lookup-form-title">Identifier le dossier citoyen</h2>
        <p class="card__lead">Saisissez un CNIE déjà présent dans l'application pour retrouver le centre associé.</p>

        <form class="form-stack" method="POST" action="" <?php echo $message !== null ? 'aria-describedby="lookup-feedback"' : ''; ?>>
            <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($lookupToken); ?>">

            <div class="form-field">
                <label for="cnieCitoyen">CNIE<?php renderRequiredIndicator(); ?></label>
                <input
                    class="form-control"
                    type="text"
                    name="cnieCitoyen"
                    id="cnieCitoyen"
                    maxlength="20"
                    pattern="[A-Za-z]{1,4}[0-9]{4,15}"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    aria-describedby="lookup-help<?php echo $message !== null ? ' lookup-feedback' : ''; ?>"
                    <?php echo $identifierInvalid ? 'aria-invalid="true"' : ''; ?>
                    required
                >
                <p class="help-text" id="lookup-help">Saisissez le préfixe alphabétique et les chiffres, sans espace.</p>
            </div>

            <div class="button-row">
                <button class="button button--primary" type="submit">Rechercher</button>
                <a class="button button--secondary" href="<?php echo escapeHtml(VACCINATION_DASHBOARD_PATH); ?>">Retour au tableau de bord</a>
            </div>
        </form>
    </section>

    <aside class="aside-stack" aria-label="Guidage de recherche">
        <section class="card card--muted">
            <h2 class="card__title">Guidage</h2>
            <ul class="info-list">
                <li>La recherche reste limitée au CNIE.</li>
                <li>Aucune carte, horaire ou disponibilité n'est affichée.</li>
                <li>Le message de résultat reste volontairement sobre et protecteur.</li>
            </ul>
        </section>

        <?php if ($citizen === null && $requestMethod !== 'POST'): ?>
            <section class="empty-state" aria-live="polite">
                <p>Aucun résultat n'est affiché tant qu'une recherche valide n'a pas été lancée.</p>
                <p>Utilisez le formulaire pour consulter un centre déjà attribué.</p>
            </section>
        <?php endif; ?>
    </aside>
</section>

<?php if ($citizen !== null): ?>
    <section class="page-section" aria-labelledby="lookup-result-title" aria-live="polite">
        <div class="section-heading">
            <p class="section-kicker">Résultat</p>
            <h2 id="lookup-result-title">Centre attribué retrouvé</h2>
            <p>Les informations ci-dessous reprennent uniquement les champs actuellement stockés et autorisés à l'affichage.</p>
        </div>

        <div class="definition-grid">
            <article class="card definition-card">
                <h3>Résumé du citoyen</h3>
                <dl class="result-list">
                    <div><dt>Nom</dt><dd><?php echo escapeHtml($citizen['nomCit'] ?? null); ?></dd></div>
                    <div><dt>Prenom</dt><dd><?php echo escapeHtml($citizen['prenomCit'] ?? null); ?></dd></div>
                    <div><dt>Date de naissance</dt><dd><?php echo escapeHtml($citizen['dateNaissanceCit'] ?? null); ?></dd></div>
                    <div><dt>Ville</dt><dd><?php echo escapeHtml($citizen['villeCit'] ?? null); ?></dd></div>
                </dl>
            </article>

            <article class="card definition-card">
                <h3>Résumé du centre attribué</h3>
                <dl class="result-list">
                    <div><dt>Intitulé</dt><dd><?php echo escapeHtml($citizen['intituleCent'] ?? null); ?></dd></div>
                    <div><dt>Adresse</dt><dd><?php echo escapeHtml($citizen['adresseCent'] ?? null); ?></dd></div>
                    <div><dt>Ville</dt><dd><?php echo escapeHtml($citizen['villeCent'] ?? null); ?></dd></div>
                </dl>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php renderPageEnd(); ?>
