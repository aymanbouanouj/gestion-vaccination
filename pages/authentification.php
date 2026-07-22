<?php

require_once __DIR__ . '/../config/security.php';

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$genericAuthenticationError = "Nom d'utilisateur ou mot de passe incorrect.";
$erreur = null;

if ($requestMethod === 'POST' && $action === 'logout') {
    requireAuthentication(true);

    if (!verifyCsrfToken('logout', $_POST['csrf_token'] ?? null)) {
        rejectInvalidCsrfToken();
    }

    require_once __DIR__ . '/../config/connexion.php';
    $administratorId = authenticatedAdministratorId();
    $loginHash = loginFingerprint(authenticatedAdministratorLogin());
    recordAuthenticationEvent($conn, 'logout', 'success', $administratorId, $loginHash);
    destroyAuthenticatedSession();
    header('Location: ' . VACCINATION_LOGIN_PATH, true, 303);
    exit;
}

if ($requestMethod === 'POST' && $action === 'login') {
    $login = trim(is_string($_POST['login'] ?? null) ? $_POST['login'] : '');
    $motPass = is_string($_POST['motPass'] ?? null) ? $_POST['motPass'] : '';
    $erreur = $genericAuthenticationError;

    try {
        require_once __DIR__ . '/../config/connexion.php';
        $loginHash = loginFingerprint($login);
        $ipHash = clientIpFingerprint();
        purgeExpiredLoginAttempts($conn);

        if (loginAttemptIsThrottled($conn, $loginHash, $ipHash)) {
            recordAuthenticationEvent(
                $conn,
                'login_throttled',
                'throttled',
                null,
                $loginHash,
                $ipHash
            );
        } else {
            $stmt = $conn->prepare(
                'SELECT idAdmin, nomAdm, prenomAdm, login, motPass, mustChangePassword '
                . 'FROM agentAdmins WHERE login = ? LIMIT 1'
            );
            $stmt->bind_param('s', $login);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            $storedHash = is_array($user) && is_string($user['motPass'] ?? null)
                ? $user['motPass']
                : '$2y$10$JHbG9uSZQ.P26W1fx6qVyOiR9isPBn4ZwnXHbf9gIXYJdU88FjPfa';
            $passwordIsValid = $login !== ''
                && $motPass !== ''
                && password_verify($motPass, $storedHash)
                && is_array($user);

            if ($passwordIsValid) {
                $administratorId = (int) $user['idAdmin'];
                clearRelevantLoginAttempts($conn, $loginHash, $ipHash);
                recordAuthenticationEvent(
                    $conn,
                    'login_success',
                    'success',
                    $administratorId,
                    $loginHash,
                    $ipHash
                );
                authenticateAdministrator($user);
                $destination = administratorMustChangePassword()
                    ? VACCINATION_PASSWORD_CHANGE_PATH
                    : VACCINATION_DASHBOARD_PATH;
                header('Location: ' . $destination, true, 303);
                exit;
            }

            recordFailedLoginAttempt($conn, $loginHash, $ipHash);
            $administratorId = is_array($user) ? (int) $user['idAdmin'] : null;
            recordAuthenticationEvent(
                $conn,
                'login_failure',
                'invalid_credentials',
                $administratorId,
                $loginHash,
                $ipHash
            );
        }
    } catch (Throwable $exception) {
        error_log('[gestion_vaccination] Authentication processing failed.');
        $erreur = 'Service temporairement indisponible.';
    }
} elseif ($requestMethod === 'POST') {
    http_response_code(400);
    exit('Requete invalide.');
} elseif (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit('Methode non autorisee.');
}

require_once __DIR__ . '/../config/layout.php';

renderPageStart(
    'Authentification',
    'Connexion administrateur',
    '',
    false,
    'auth-page',
    '',
    'Connectez-vous a l espace d administration local pour acceder aux fonctions deja presentes dans l application.'
);
?>

<section class="auth-shell" aria-label="Connexion administrateur" data-reveal>
    <article class="auth-showcase">
        <div class="auth-showcase__surface">
            <p class="auth-showcase__eyebrow">Prototype pedagogique local</p>
            <img
                class="auth-showcase__logo"
                src="<?php echo escapeHtml(vaccinationAssetPath('branding/logo-full-light.png')); ?>"
                alt="Identite visuelle Gestion Vaccination"
                width="160"
                height="160"
            >
            <h1>Connexion administrateur</h1>
            <p>Connectez-vous pour acceder aux fonctions deja presentes dans l'application, sans module citoyen, catalogue ou parcours additionnel.</p>
        </div>

        <div class="auth-showcase__grid">
            <article class="auth-note">
                <span class="auth-note__icon"><?php renderInlineIcon('shield'); ?></span>
                <div>
                    <strong>Acces reserve</strong>
                    <p>Authentification administrateur et sessions protegees.</p>
                </div>
            </article>

            <article class="auth-note">
                <span class="auth-note__icon"><?php renderInlineIcon('validation'); ?></span>
                <div>
                    <strong>Parcours borne</strong>
                    <p>Cycle de mot de passe, inscription citoyenne et recherche de centre.</p>
                </div>
            </article>

            <article class="auth-note">
                <span class="auth-note__icon"><?php renderInlineIcon('data'); ?></span>
                <div>
                    <strong>Demonstration locale</strong>
                    <p>Aucune affiliation officielle et aucune connexion citoyenne n'est simulee.</p>
                </div>
            </article>
        </div>
    </article>

    <section class="card auth-card" aria-labelledby="login-form-title">
        <p class="auth-card__eyebrow">Acces securise</p>
        <h2 class="card__title" id="login-form-title">Se connecter</h2>
        <p class="card__lead">Utilisez les acces prevus pour cette demonstration locale.</p>

        <?php if ($erreur !== null && $erreur !== ''): ?>
            <?php renderFeedback('error', [$erreur], 'auth-error'); ?>
        <?php endif; ?>

        <form class="form-stack" method="POST" action="" <?php echo $erreur !== null ? 'aria-describedby="auth-error"' : ''; ?>>
            <input type="hidden" name="action" value="login">

            <div class="form-field">
                <label for="login">Login<?php renderRequiredIndicator(); ?></label>
                <input
                    class="form-control"
                    type="text"
                    name="login"
                    id="login"
                    maxlength="50"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    <?php echo $erreur !== null ? 'aria-invalid="true" aria-describedby="auth-error"' : ''; ?>
                    required
                >
            </div>

            <div class="form-field">
                <label for="motPass">Mot de passe<?php renderRequiredIndicator(); ?></label>
                <input
                    class="form-control"
                    type="password"
                    name="motPass"
                    id="motPass"
                    autocomplete="current-password"
                    <?php echo $erreur !== null ? 'aria-invalid="true" aria-describedby="auth-error"' : ''; ?>
                    required
                >
            </div>

            <button class="button button--primary button--block" type="submit">Se connecter</button>
        </form>

        <p class="auth-card__context">Prototype pedagogique local. Cette interface ne constitue pas un service medical officiel.</p>
    </section>
</section>

<?php renderPageEnd(); ?>
