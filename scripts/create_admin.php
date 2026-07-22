<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/connexion.php';

function createAdminFail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function createAdminPrompt(string $label, bool $allowEmpty = false): string
{
    while (true) {
        fwrite(STDOUT, $label);
        $line = fgets(STDIN);
        if ($line === false) {
            createAdminFail('Administrator creation cancelled.');
        }

        $value = trim($line);
        if ($value === '' && !$allowEmpty) {
            fwrite(STDERR, "This field is required." . PHP_EOL);
            continue;
        }

        return $value;
    }
}

function createAdminHasControlCharacters(string $value): bool
{
    return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
}

function createAdminValidateTextField(string $label, string $value, int $maximum): ?string
{
    if ($value === '') {
        return $label . ' is required.';
    }
    if (createAdminHasControlCharacters($value)) {
        return $label . ' contains invalid control characters.';
    }
    if (!preg_match('//u', $value)) {
        return $label . ' must be valid UTF-8 text.';
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);

    if ($length > $maximum) {
        return $label . ' must not exceed ' . $maximum . ' characters.';
    }

    return null;
}

fwrite(STDOUT, "Create a local administrator account for Gestion Vaccination." . PHP_EOL);
fwrite(STDOUT, "Passwords are never stored in the repository. The new account defaults to forced password replacement." . PHP_EOL . PHP_EOL);

$lastName = createAdminPrompt('Last name: ');
$firstName = createAdminPrompt('First name: ');
$email = createAdminPrompt('Email (optional): ', true);
$login = createAdminPrompt('Login: ');
$password = createAdminPrompt('Password: ');
$confirmation = createAdminPrompt('Confirm password: ');
$forceChangeInput = createAdminPrompt('Force password change on first login? [Y/n]: ', true);

$mustChangePassword = match (strtolower($forceChangeInput)) {
    '', 'y', 'yes' => 1,
    'n', 'no' => 0,
    default => createAdminFail('Force-change answer must be Y, Yes, N, No, or empty.'),
};

$errors = [];
foreach ([
    ['Last name', $lastName, 50],
    ['First name', $firstName, 50],
    ['Login', $login, 50],
] as [$label, $value, $maximum]) {
    $error = createAdminValidateTextField($label, $value, $maximum);
    if ($error !== null) {
        $errors[] = $error;
    }
}

if ($email !== '') {
    $emailError = createAdminValidateTextField('Email', $email, 100);
    if ($emailError !== null) {
        $errors[] = $emailError;
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Email must be a valid email address or left empty.';
    }
}

if (!hash_equals($password, $confirmation)) {
    $errors[] = 'Password confirmation does not match.';
}

$errors = array_merge($errors, validateAdministratorPassword($password, '', $login));

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . PHP_EOL);
    }
    exit(1);
}

$existing = $conn->prepare('SELECT 1 FROM agentAdmins WHERE login = ? LIMIT 1');
$existing->bind_param('s', $login);
$existing->execute();
if ($existing->get_result()->num_rows > 0) {
    createAdminFail('An administrator with this login already exists.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if (!is_string($passwordHash) || $passwordHash === '') {
    createAdminFail('Password hashing failed.');
}

$passwordChangedAt = $mustChangePassword === 1
    ? null
    : (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

$statement = $conn->prepare(
    'INSERT INTO agentAdmins '
    . '(nomAdm, prenomAdm, emailAdm, login, motPass, mustChangePassword, passwordChangedAt) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$statement->bind_param(
    'sssssis',
    $lastName,
    $firstName,
    $email,
    $login,
    $passwordHash,
    $mustChangePassword,
    $passwordChangedAt
);
$statement->execute();

fwrite(STDOUT, PHP_EOL . 'Administrator created successfully.' . PHP_EOL);
fwrite(STDOUT, 'Login: ' . $login . PHP_EOL);
fwrite(STDOUT, 'Force password change: ' . ($mustChangePassword === 1 ? 'yes' : 'no') . PHP_EOL);
