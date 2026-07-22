<?php

require_once __DIR__ . '/../config/security.php';
requireAuthentication(true);

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit('Methode non autorisee.');
}

$errors = [];
$administratorId = authenticatedAdministratorId();
$administratorLogin = authenticatedAdministratorLogin();
$loginHash = loginFingerprint($administratorLogin);

if ($requestMethod === 'POST') {
    require_once __DIR__ . '/../config/connexion.php';

    if (!verifyCsrfToken('password_change', $_POST['csrf_token'] ?? null)) {
        recordAuthenticationEvent(
            $conn,
            'password_change_failure',
            'invalid_csrf',
            $administratorId,
            $loginHash
        );
        rejectInvalidCsrfToken();
    }

    $currentPassword = is_string($_POST['current_password'] ?? null)
        ? $_POST['current_password']
        : '';
    $newPassword = is_string($_POST['new_password'] ?? null)
        ? $_POST['new_password']
        : '';
    $confirmation = is_string($_POST['new_password_confirmation'] ?? null)
        ? $_POST['new_password_confirmation']
        : '';

    try {
        $statement = $conn->prepare(
            'SELECT idAdmin, login, motPass FROM agentAdmins WHERE idAdmin = ? LIMIT 1'
        );
        $statement->bind_param('i', $administratorId);
        $statement->execute();
        $administrator = $statement->get_result()->fetch_assoc();

        if (!is_array($administrator)
            || !is_string($administrator['motPass'] ?? null)
            || !password_verify($currentPassword, $administrator['motPass'])) {
            $errors[] = 'Le mot de passe actuel est incorrect.';
            recordAuthenticationEvent(
                $conn,
                'password_change_failure',
                'invalid_current_password',
                $administratorId,
                $loginHash
            );
        } elseif (!hash_equals($newPassword, $confirmation)) {
            $errors[] = 'La confirmation du nouveau mot de passe ne correspond pas.';
            recordAuthenticationEvent(
                $conn,
                'password_change_failure',
                'confirmation_mismatch',
                $administratorId,
                $loginHash
            );
        } else {
            $errors = validateAdministratorPassword(
                $newPassword,
                $currentPassword,
                $administratorLogin
            );

            if ($errors !== []) {
                recordAuthenticationEvent(
                    $conn,
                    'password_change_failure',
                    'policy_rejected',
                    $administratorId,
                    $loginHash
                );
            }
        }

        if ($errors === []) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (!is_string($newHash) || $newHash === '') {
                throw new RuntimeException('Password hashing failed.');
            }

            $conn->begin_transaction();
            try {
                $update = $conn->prepare(
                    'UPDATE agentAdmins '
                    . 'SET motPass = ?, mustChangePassword = 0, passwordChangedAt = NOW() '
                    . 'WHERE idAdmin = ?'
                );
                $update->bind_param('si', $newHash, $administratorId);
                $update->execute();

                if ($update->affected_rows !== 1) {
                    throw new RuntimeException('Unexpected password update row count.');
                }

                $conn->commit();
            } catch (Throwable $exception) {
                $conn->rollback();
                throw $exception;
            }

            recordAuthenticationEvent(
                $conn,
                'password_change_success',
                'success',
                $administratorId,
                $loginHash
            );
            markAdministratorPasswordChanged();
            $_SESSION['password_change_success'] = true;
            header('Location: ' . VACCINATION_DASHBOARD_PATH, true, 303);
            exit;
        }
    } catch (Throwable $exception) {
        error_log('[gestion_vaccination] Password change processing failed.');
        recordAuthenticationEvent(
            $conn,
            'password_change_failure',
            'database_failure',
            $administratorId,
            $loginHash
        );
        $errors = ['Le mot de passe n\'a pas pu etre modifie. Veuillez reessayer.'];
    }
}

$passwordChangeToken = csrfToken('password_change');
require_once __DIR__ . '/../config/layout.php';

$passwordChangeRequired = administratorMustChangePassword();
renderPageStart(
    'Changer le mot de passe',
    'Changer le mot de passe',
    'password',
    true,
    '',
    'page-shell--form',
    'Utilisez un mot de passe unique respectant la politique de securite deja appliquee par l\'application.'
);
?>

<?php if ($passwordChangeRequired): ?>
    <?php renderFeedback('warning', [
        'Vous devez remplacer votre mot de passe temporaire avant d\'acceder aux autres fonctions.',
    ]); ?>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <?php renderFeedback('error', $errors, 'password-errors'); ?>
<?php endif; ?>

<section class="page-grid" aria-label="Mise a jour du mot de passe">
    <aside class="aside-stack">
        <section class="card card--muted">
            <h2 class="card__title">Politique de mot de passe</h2>
            <p class="card__lead">Le formulaire conserve les controles existants et n'affiche jamais les mots de passe saisis apres echec.</p>
            <ul class="policy-list" id="password-policy">
                <li>au moins 12 caracteres et au maximum 72 octets ;</li>
                <li>une majuscule, une minuscule, un chiffre et un symbole ;</li>
                <li>aucun espace au debut ou a la fin ;</li>
                <li>ne pas reprendre le login ni le mot de passe actuel ;</li>
                <li>ne pas etre un mot de passe courant.</li>
            </ul>
        </section>

        <section class="card">
            <h2 class="card__title">Rappel de securite</h2>
            <div class="status-list">
                <div class="status-list__item">
                    <span class="status-list__marker" aria-hidden="true"></span>
                    <div>
                        <strong>Session administrateur</strong>
                        <p>La deconnexion securisee reste disponible dans l'en-tete via un formulaire POST.</p>
                    </div>
                </div>
                <div class="status-list__item">
                    <span class="status-list__marker" aria-hidden="true"></span>
                    <div>
                        <strong>Changement force</strong>
                        <p>En mode de remplacement obligatoire, les autres liens metier restent masques.</p>
                    </div>
                </div>
            </div>
        </section>
    </aside>

    <section class="card" aria-labelledby="password-form-title">
        <h2 class="card__title" id="password-form-title">Nouveau mot de passe</h2>

        <form class="form-stack" method="POST" action="" aria-describedby="password-policy<?php echo $errors !== [] ? ' password-errors' : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($passwordChangeToken); ?>">

            <div class="form-field">
                <label for="current_password">Mot de passe actuel<?php renderRequiredIndicator(); ?></label>
                <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" <?php echo $errors !== [] ? 'aria-invalid="true" aria-describedby="password-policy password-errors"' : 'aria-describedby="password-policy"'; ?> required>
            </div>

            <div class="form-field">
                <label for="new_password">Nouveau mot de passe<?php renderRequiredIndicator(); ?></label>
                <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" <?php echo $errors !== [] ? 'aria-invalid="true" aria-describedby="password-policy password-errors"' : 'aria-describedby="password-policy"'; ?> required>
            </div>

            <div class="form-field">
                <label for="new_password_confirmation">Confirmation du nouveau mot de passe<?php renderRequiredIndicator(); ?></label>
                <input class="form-control" type="password" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" <?php echo $errors !== [] ? 'aria-invalid="true" aria-describedby="password-policy password-errors"' : 'aria-describedby="password-policy"'; ?> required>
            </div>

            <button class="button button--primary button--block" type="submit">Enregistrer le nouveau mot de passe</button>
        </form>
    </section>
</section>

<?php renderPageEnd(); ?>
