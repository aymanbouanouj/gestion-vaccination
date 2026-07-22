<?php

require_once __DIR__ . '/../config/security.php';
requireAuthentication();
require_once __DIR__ . '/../config/application.php';

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit('Methode non autorisee.');
}

require_once __DIR__ . '/../config/connexion.php';
require_once __DIR__ . '/../config/layout.php';

$flash = consumeCitizenCreationFlash();
$centres = [];
$centreLoadError = null;
$centreFeedbackType = 'error';

try {
    $result = $conn->query(
        'SELECT idCentre, intituleCent, villeCent FROM centres ORDER BY intituleCent, villeCent'
    );
    while ($row = $result->fetch_assoc()) {
        $centreId = filter_var($row['idCentre'] ?? null, FILTER_VALIDATE_INT);
        if ($centreId === false || $centreId < 1) {
            error_log('[gestion_vaccination] Centre list contained an invalid identifier [code=CENTRE_LIST_DATA].');
            continue;
        }
        $centres[] = [
            'id' => $centreId,
            'title' => (string) ($row['intituleCent'] ?? ''),
            'city' => (string) ($row['villeCent'] ?? ''),
        ];
    }
} catch (Throwable $exception) {
    error_log('[gestion_vaccination] Centre list query failed [code=CENTRE_LIST_DB].');
    $centreLoadError = 'La liste des centres est temporairement indisponible.';
}

if ($centreLoadError === null && $centres === []) {
    $centreLoadError = 'Aucun centre n\'est actuellement disponible. L\'inscription est temporairement desactivee.';
    $centreFeedbackType = 'warning';
}

$citizenCreationToken = csrfToken('citizen_create');
$maximumBirthDate = (new DateTimeImmutable('today'))->format('Y-m-d');
$invalidFields = $flash['fields'] ?? [];
$flashType = $flash !== null
    ? match ($flash['type']) {
        'success' => 'success',
        'validation', 'duplicate' => 'warning',
        default => 'error',
    }
    : null;
$formDescriptionIds = ['required-note'];
if ($flash !== null) {
    $formDescriptionIds[] = 'citizen-feedback';
}
if ($centreLoadError !== null) {
    $formDescriptionIds[] = 'centre-load-error';
}

renderPageStart(
    'Inscrire un citoyen',
    'Inscrire un citoyen',
    'citizen-create',
    true,
    '',
    'page-shell--form',
    'Renseignez les informations necessaires a la creation du dossier citoyen, sans ajouter de donnees hors perimetre.'
);
?>

<?php if ($flash !== null && $flashType !== null): ?>
    <?php renderFeedback($flashType, $flash['messages'], 'citizen-feedback'); ?>
<?php endif; ?>

<?php if ($centreLoadError !== null): ?>
    <?php renderFeedback($centreFeedbackType, [$centreLoadError], 'centre-load-error'); ?>
<?php endif; ?>

<section class="form-layout" aria-label="Parcours d'inscription citoyenne">
    <section class="card" aria-labelledby="citizen-form-title">
        <h2 class="card__title" id="citizen-form-title">Formulaire d'inscription</h2>
        <p class="form-note" id="required-note">Les champs marques d'un asterisque sont obligatoires.</p>

        <form
            class="form-stack"
            method="POST"
            action="ajoutCitoyen.php"
            aria-describedby="<?php echo escapeHtml(implode(' ', $formDescriptionIds)); ?>"
        >
            <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($citizenCreationToken); ?>">

            <fieldset class="form-section">
                <legend>Identite du citoyen</legend>
                <div class="form-grid">
                    <div class="form-field form-field--full">
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
                            aria-describedby="cnie-help<?php echo in_array('cnieCitoyen', $invalidFields, true) ? ' citizen-feedback' : ''; ?>"
                            <?php echo in_array('cnieCitoyen', $invalidFields, true) ? 'aria-invalid="true"' : ''; ?>
                            required
                        >
                        <p class="help-text" id="cnie-help">Prefixe alphabetique suivi de chiffres, sans espace.</p>
                    </div>

                    <div class="form-field">
                        <label for="nomCit">Nom<?php renderRequiredIndicator(); ?></label>
                        <input class="form-control" type="text" name="nomCit" id="nomCit" maxlength="50" autocomplete="family-name" <?php echo in_array('nomCit', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                    </div>

                    <div class="form-field">
                        <label for="prenomCit">Prenom<?php renderRequiredIndicator(); ?></label>
                        <input class="form-control" type="text" name="prenomCit" id="prenomCit" maxlength="50" autocomplete="given-name" <?php echo in_array('prenomCit', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                    </div>

                    <div class="form-field form-field--full">
                        <label for="dateNaissanceCit">Date de naissance<?php renderRequiredIndicator(); ?></label>
                        <input class="form-control" type="date" name="dateNaissanceCit" id="dateNaissanceCit" min="<?php echo escapeHtml(CITIZEN_APPLICATION_MINIMUM_BIRTH_DATE); ?>" max="<?php echo escapeHtml($maximumBirthDate); ?>" autocomplete="bday" <?php echo in_array('dateNaissanceCit', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Coordonnees et affectation</legend>
                <div class="form-grid">
                    <div class="form-field form-field--full">
                        <label for="adresseCit">Adresse<?php renderRequiredIndicator(); ?></label>
                        <input class="form-control" type="text" name="adresseCit" id="adresseCit" maxlength="100" autocomplete="street-address" <?php echo in_array('adresseCit', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                    </div>

                    <div class="form-field">
                        <label for="villeCit">Ville<?php renderRequiredIndicator(); ?></label>
                        <input class="form-control" type="text" name="villeCit" id="villeCit" maxlength="50" autocomplete="address-level2" <?php echo in_array('villeCit', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                    </div>

                    <div class="form-field">
                        <label for="activiteCit">Activite<?php renderRequiredIndicator(); ?></label>
                        <select class="form-control" name="activiteCit" id="activiteCit" <?php echo in_array('activiteCit', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                            <option value="">Selectionnez une activite</option>
                            <?php foreach (citizenActivityOptions() as $activity): ?>
                                <option value="<?php echo escapeHtml($activity); ?>"><?php echo escapeHtml($activity); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="exCovid">Deja atteint du Covid<?php renderRequiredIndicator(); ?></label>
                        <select class="form-control" name="exCovid" id="exCovid" <?php echo in_array('exCovid', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required>
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="idCentre">Centre attribue<?php renderRequiredIndicator(); ?></label>
                        <select class="form-control" name="idCentre" id="idCentre" <?php echo in_array('idCentre', $invalidFields, true) ? 'aria-invalid="true" aria-describedby="citizen-feedback"' : ''; ?> required <?php echo $centres === [] ? 'disabled' : ''; ?>>
                            <option value="">Selectionnez un centre</option>
                            <?php foreach ($centres as $centre): ?>
                                <option value="<?php echo escapeHtml($centre['id']); ?>">
                                    <?php echo escapeHtml($centre['title']); ?> - <?php echo escapeHtml($centre['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <div class="button-row">
                <button class="button button--primary" type="submit" <?php echo $centres === [] ? 'disabled' : ''; ?>>Enregistrer le citoyen</button>
                <a class="button button--secondary" href="<?php echo escapeHtml(VACCINATION_DASHBOARD_PATH); ?>">Retour au tableau de bord</a>
            </div>
        </form>
    </section>

    <aside class="aside-stack" aria-label="Informations d'accompagnement">
        <section class="card card--muted">
            <h2 class="card__title">Saisie attendue</h2>
            <p class="card__lead">Le formulaire conserve les noms de champs et le comportement serveur existants.</p>
            <ul class="info-list">
                <li>Verifier le format du CNIE avant envoi.</li>
                <li>Choisir une activite parmi les options deja gerees.</li>
                <li>Selectionner un centre dans la liste fournie.</li>
            </ul>
        </section>

        <section class="card">
            <h2 class="card__title">Traitement de la demande</h2>
            <div class="status-list">
                <div class="status-list__item">
                    <span class="status-list__marker" aria-hidden="true"></span>
                    <div>
                        <strong>Validation serveur</strong>
                        <p>Les controles existants restent appliques apres la soumission.</p>
                    </div>
                </div>
                <div class="status-list__item">
                    <span class="status-list__marker" aria-hidden="true"></span>
                    <div>
                        <strong>Retour utilisateur</strong>
                        <p>Les messages de succes, d'erreur ou de doublon restent affiches apres redirection.</p>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</section>

<?php renderPageEnd(); ?>
