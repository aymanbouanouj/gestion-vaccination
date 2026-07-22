<?php

declare(strict_types=1);

ini_set('log_errors', '1');
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

const VACCINATION_BASE_PATH = '/gestion_vaccination';
const VACCINATION_LOGIN_PATH = VACCINATION_BASE_PATH . '/pages/authentification.php';
const VACCINATION_PASSWORD_CHANGE_PATH = VACCINATION_BASE_PATH . '/pages/changerMotPasse.php';
const VACCINATION_DASHBOARD_PATH = VACCINATION_BASE_PATH . '/index.php';
const VACCINATION_SESSION_KEY = 'authenticated_administrator';
const VACCINATION_CSRF_KEY = 'csrf_tokens';
const VACCINATION_IDLE_TIMEOUT_SECONDS = 1800;
const VACCINATION_ABSOLUTE_SESSION_SECONDS = 28800;
const VACCINATION_LOGIN_WINDOW_MINUTES = 15;
const VACCINATION_LOGIN_FAILURE_LIMIT = 5;

function vaccinationRequestUsesHttps(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';

    return (is_string($https) && $https !== '' && strtolower($https) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function vaccinationSecurityUnavailable(): never
{
    http_response_code(500);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Service temporairement indisponible.\n");
        exit(1);
    }

    exit('Service temporairement indisponible.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', vaccinationRequestUsesHttps() ? '1' : '0');

    session_name('gestion_vaccination_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => VACCINATION_BASE_PATH . '/',
        'secure' => vaccinationRequestUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!@session_start()) {
        error_log('[gestion_vaccination] Secure session initialization failed.');
        vaccinationSecurityUnavailable();
    }
}

function isAuthenticated(): bool
{
    $administrator = $_SESSION[VACCINATION_SESSION_KEY] ?? null;

    return is_array($administrator)
        && isset(
            $administrator['id'],
            $administrator['login'],
            $administrator['display_name'],
            $administrator['must_change_password'],
            $administrator['authenticated_at'],
            $administrator['last_activity_at']
        )
        && is_int($administrator['id'])
        && $administrator['id'] > 0
        && is_string($administrator['login'])
        && $administrator['login'] !== ''
        && is_string($administrator['display_name'])
        && $administrator['display_name'] !== ''
        && is_bool($administrator['must_change_password'])
        && is_int($administrator['authenticated_at'])
        && is_int($administrator['last_activity_at']);
}

function authenticatedSessionIsFresh(): bool
{
    if (!isAuthenticated()) {
        return false;
    }

    $now = time();
    $administrator = $_SESSION[VACCINATION_SESSION_KEY];

    return $now - $administrator['last_activity_at'] <= VACCINATION_IDLE_TIMEOUT_SECONDS
        && $now - $administrator['authenticated_at'] <= VACCINATION_ABSOLUTE_SESSION_SECONDS;
}

function requireAuthentication(bool $allowPendingPasswordChange = false): void
{
    $authenticated = isAuthenticated() && authenticatedSessionIsFresh();

    if (!$authenticated && session_status() === PHP_SESSION_ACTIVE) {
        destroyAuthenticatedSession();
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if (!$authenticated) {
        if ($method === 'GET' || $method === 'HEAD') {
            header('Location: ' . VACCINATION_LOGIN_PATH, true, 302);
            exit;
        }

        http_response_code(403);
        exit('Accès refusé.');
    }

    $_SESSION[VACCINATION_SESSION_KEY]['last_activity_at'] = time();

    if (!$allowPendingPasswordChange && administratorMustChangePassword()) {
        if ($method === 'GET' || $method === 'HEAD') {
            header('Location: ' . VACCINATION_PASSWORD_CHANGE_PATH, true, 302);
            exit;
        }

        http_response_code(403);
        exit('Changement de mot de passe requis.');
    }
}

function authenticateAdministrator(array $administrator): void
{
    $id = filter_var($administrator['idAdmin'] ?? null, FILTER_VALIDATE_INT);
    $login = $administrator['login'] ?? null;
    $displayName = trim(
        (string) ($administrator['nomAdm'] ?? '') . ' ' . (string) ($administrator['prenomAdm'] ?? '')
    );
    $mustChangePassword = filter_var(
        $administrator['mustChangePassword'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($id === false
        || $id < 1
        || !is_string($login)
        || $login === ''
        || $displayName === ''
        || !in_array($mustChangePassword, [0, 1], true)) {
        error_log('[gestion_vaccination] Invalid administrator identity data.');
        vaccinationSecurityUnavailable();
    }

    $_SESSION = [];

    if (!session_regenerate_id(true)) {
        error_log('[gestion_vaccination] Session identifier regeneration failed.');
        vaccinationSecurityUnavailable();
    }

    $now = time();
    $_SESSION[VACCINATION_SESSION_KEY] = [
        'id' => $id,
        'login' => $login,
        'display_name' => $displayName,
        'must_change_password' => $mustChangePassword === 1,
        'authenticated_at' => $now,
        'last_activity_at' => $now,
    ];
}

function authenticatedAdministratorId(): int
{
    return isAuthenticated() ? $_SESSION[VACCINATION_SESSION_KEY]['id'] : 0;
}

function authenticatedAdministratorLogin(): string
{
    return isAuthenticated() ? $_SESSION[VACCINATION_SESSION_KEY]['login'] : '';
}

function administratorMustChangePassword(): bool
{
    return isAuthenticated()
        && $_SESSION[VACCINATION_SESSION_KEY]['must_change_password'] === true;
}

function markAdministratorPasswordChanged(): void
{
    if (!isAuthenticated() || !session_regenerate_id(true)) {
        error_log('[gestion_vaccination] Post-password-change session rotation failed.');
        vaccinationSecurityUnavailable();
    }

    $_SESSION[VACCINATION_SESSION_KEY]['must_change_password'] = false;
    $_SESSION[VACCINATION_SESSION_KEY]['last_activity_at'] = time();
    rotateCsrfTokens();
}

function authenticatedAdministratorDisplayName(): string
{
    if (!isAuthenticated()) {
        return '';
    }

    return htmlspecialchars(
        $_SESSION[VACCINATION_SESSION_KEY]['display_name'],
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function csrfToken(string $action): string
{
    if (!isset($_SESSION[VACCINATION_CSRF_KEY]) || !is_array($_SESSION[VACCINATION_CSRF_KEY])) {
        $_SESSION[VACCINATION_CSRF_KEY] = [];
    }

    if (!isset($_SESSION[VACCINATION_CSRF_KEY][$action])
        || !is_string($_SESSION[VACCINATION_CSRF_KEY][$action])) {
        $_SESSION[VACCINATION_CSRF_KEY][$action] = bin2hex(random_bytes(32));
    }

    return $_SESSION[VACCINATION_CSRF_KEY][$action];
}

function verifyCsrfToken(string $action, mixed $submittedToken): bool
{
    $expectedToken = $_SESSION[VACCINATION_CSRF_KEY][$action] ?? null;

    if (!is_string($submittedToken) || !is_string($expectedToken)) {
        return false;
    }

    $valid = hash_equals($expectedToken, $submittedToken);

    if ($valid) {
        unset($_SESSION[VACCINATION_CSRF_KEY][$action]);
    }

    return $valid;
}

function rotateCsrfTokens(): void
{
    $_SESSION[VACCINATION_CSRF_KEY] = [];
}

function rejectInvalidCsrfToken(): never
{
    http_response_code(403);
    exit('Requête invalide.');
}

function destroyAuthenticatedSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE && !session_destroy()) {
        error_log('[gestion_vaccination] Session destruction failed.');
    }
}

/**
 * Central password policy: 12+ characters (72 UTF-8 bytes maximum for the
 * current PASSWORD_DEFAULT bcrypt backend), upper/lower case, digit and symbol,
 * no surrounding whitespace, no current password/login reuse, and no common
 * placeholder password. Returns French messages safe for direct display.
 *
 * @return list<string>
 */
function validateAdministratorPassword(
    string $newPassword,
    string $currentPassword,
    string $administratorLogin
): array {
    $errors = [];
    $length = function_exists('mb_strlen')
        ? mb_strlen($newPassword, 'UTF-8')
        : strlen($newPassword);

    if ($length < 12) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 12 caractères.';
    }
    if (strlen($newPassword) > 72) {
        $errors[] = 'Le nouveau mot de passe ne doit pas dépasser 72 octets.';
    }
    if (!preg_match('/\p{Lu}/u', $newPassword)) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins une majuscule.';
    }
    if (!preg_match('/\p{Ll}/u', $newPassword)) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins une minuscule.';
    }
    if (!preg_match('/\p{N}/u', $newPassword)) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins un chiffre.';
    }
    if (!preg_match('/[^\p{L}\p{N}\s]/u', $newPassword)) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins un symbole.';
    }
    if (trim($newPassword) !== $newPassword) {
        $errors[] = 'Le nouveau mot de passe ne doit pas commencer ou finir par un espace.';
    }
    if (hash_equals($currentPassword, $newPassword)) {
        $errors[] = 'Le nouveau mot de passe doit être différent du mot de passe actuel.';
    }

    $normalizedPassword = function_exists('mb_strtolower')
        ? mb_strtolower($newPassword, 'UTF-8')
        : strtolower($newPassword);
    $normalizedLogin = normalizeAdministratorLogin($administratorLogin);

    if ($normalizedLogin !== '' && hash_equals($normalizedLogin, $normalizedPassword)) {
        $errors[] = 'Le nouveau mot de passe ne doit pas être identique au login.';
    }

    $loginLength = function_exists('mb_strlen')
        ? mb_strlen($normalizedLogin, 'UTF-8')
        : strlen($normalizedLogin);
    if ($loginLength >= 4 && str_contains($normalizedPassword, $normalizedLogin)) {
        $errors[] = 'Le nouveau mot de passe ne doit pas contenir le login complet.';
    }

    $placeholderCandidate = preg_replace('/[^a-z0-9]/', '', $normalizedPassword);
    if (is_string($placeholderCandidate)
        && preg_match('/^(?:password|admin|123456|azerty|qwerty)[0-9]*$/', $placeholderCandidate)) {
        $errors[] = 'Le nouveau mot de passe est trop courant.';
    }

    return array_values(array_unique($errors));
}

function normalizeAdministratorLogin(string $login): string
{
    $login = trim($login);

    return function_exists('mb_strtolower')
        ? mb_strtolower($login, 'UTF-8')
        : strtolower($login);
}

function authenticationFingerprintKey(): string
{
    static $key = null;

    if (is_string($key)) {
        return $key;
    }

    $path = getenv('GESTION_VACCINATION_SECURITY_CONFIG');
    if (!is_string($path) || trim($path) === '') {
        $path = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'private_config'
            . DIRECTORY_SEPARATOR . 'gestion_vaccination'
            . DIRECTORY_SEPARATOR . 'security.php';
    }

    if (!is_file($path) || !is_readable($path)) {
        error_log('[gestion_vaccination] Private authentication security configuration is unavailable.');
        vaccinationSecurityUnavailable();
    }

    try {
        $config = require $path;
    } catch (Throwable $exception) {
        error_log('[gestion_vaccination] Private authentication security configuration could not be loaded.');
        vaccinationSecurityUnavailable();
    }

    $encodedKey = is_array($config) ? ($config['fingerprint_key'] ?? null) : null;
    if (!is_string($encodedKey) || strlen($encodedKey) !== 64 || !ctype_xdigit($encodedKey)) {
        error_log('[gestion_vaccination] Private authentication security configuration is invalid.');
        vaccinationSecurityUnavailable();
    }

    $decodedKey = hex2bin($encodedKey);
    if (!is_string($decodedKey) || strlen($decodedKey) !== 32) {
        error_log('[gestion_vaccination] Private authentication fingerprint key is invalid.');
        vaccinationSecurityUnavailable();
    }

    $key = $decodedKey;
    return $key;
}

function loginFingerprint(string $login): string
{
    return hash_hmac('sha256', 'login:' . normalizeAdministratorLogin($login), authenticationFingerprintKey());
}

function clientIpFingerprint(): string
{
    $clientAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($clientAddress) || filter_var($clientAddress, FILTER_VALIDATE_IP) === false) {
        $clientAddress = 'unavailable';
    }

    return hash_hmac('sha256', 'ip:' . $clientAddress, authenticationFingerprintKey());
}

function loginAttemptIsThrottled(mysqli $connection, string $loginHash, string $ipHash): bool
{
    $sql = 'SELECT '
        . '(SELECT COUNT(*) FROM authLoginAttempts '
        . 'WHERE loginFingerprint = ? AND attemptedAt >= NOW(6) - INTERVAL '
        . VACCINATION_LOGIN_WINDOW_MINUTES . ' MINUTE) AS loginFailures, '
        . '(SELECT COUNT(*) FROM authLoginAttempts '
        . 'WHERE ipFingerprint = ? AND attemptedAt >= NOW(6) - INTERVAL '
        . VACCINATION_LOGIN_WINDOW_MINUTES . ' MINUTE) AS ipFailures';
    $statement = $connection->prepare($sql);
    $statement->bind_param('ss', $loginHash, $ipHash);
    $statement->execute();
    $counts = $statement->get_result()->fetch_assoc();

    return (int) ($counts['loginFailures'] ?? 0) >= VACCINATION_LOGIN_FAILURE_LIMIT
        || (int) ($counts['ipFailures'] ?? 0) >= VACCINATION_LOGIN_FAILURE_LIMIT;
}

function recordFailedLoginAttempt(mysqli $connection, string $loginHash, string $ipHash): void
{
    $statement = $connection->prepare(
        'INSERT INTO authLoginAttempts (loginFingerprint, ipFingerprint) VALUES (?, ?)'
    );
    $statement->bind_param('ss', $loginHash, $ipHash);
    $statement->execute();
}

function clearRelevantLoginAttempts(mysqli $connection, string $loginHash, string $ipHash): void
{
    $statement = $connection->prepare(
        'DELETE FROM authLoginAttempts WHERE loginFingerprint = ? OR ipFingerprint = ?'
    );
    $statement->bind_param('ss', $loginHash, $ipHash);
    $statement->execute();
}

function purgeExpiredLoginAttempts(mysqli $connection): void
{
    $connection->query(
        'DELETE FROM authLoginAttempts '
        . 'WHERE attemptedAt < NOW(6) - INTERVAL 24 HOUR LIMIT 1000'
    );
}

/**
 * Authentication events intentionally contain only keyed fingerprints and
 * approved metadata. A privileged maintenance process should retain them for
 * 90 days, then delete older rows; the web application has INSERT-only access.
 */
function recordAuthenticationEvent(
    mysqli $connection,
    string $eventType,
    string $outcome,
    ?int $administratorId,
    ?string $loginHash,
    ?string $ipHash = null
): bool {
    $allowedEvents = [
        'login_success',
        'login_failure',
        'login_throttled',
        'password_change_success',
        'password_change_failure',
        'logout',
    ];
    $allowedOutcomes = [
        'success',
        'invalid_credentials',
        'throttled',
        'invalid_csrf',
        'invalid_current_password',
        'policy_rejected',
        'confirmation_mismatch',
        'database_failure',
    ];

    if (!in_array($eventType, $allowedEvents, true)
        || !in_array($outcome, $allowedOutcomes, true)) {
        error_log('[gestion_vaccination] Invalid authentication event was rejected.');
        return false;
    }

    try {
        $ipHash ??= clientIpFingerprint();
        $statement = $connection->prepare(
            'INSERT INTO authSecurityEvents '
            . '(eventType, idAdmin, loginFingerprint, ipFingerprint, outcome) '
            . 'VALUES (?, ?, ?, ?, ?)'
        );
        $statement->bind_param('sisss', $eventType, $administratorId, $loginHash, $ipHash, $outcome);
        $statement->execute();
        return true;
    } catch (Throwable $exception) {
        error_log('[gestion_vaccination] Noncritical authentication event logging failed.');
        return false;
    }
}
