<?php

declare(strict_types=1);

const CITIZEN_CREATION_FLASH_KEY = 'citizen_creation_flash';
const CITIZEN_APPLICATION_MINIMUM_BIRTH_DATE = '1900-01-01';
const CITIZEN_APPLICATION_MAXIMUM_POST_BYTES = 4096;

function escapeHtml(mixed $value): string
{
    if ($value === null) {
        $text = '';
    } elseif (is_string($value) || is_int($value) || is_float($value)) {
        $text = (string) $value;
    } elseif (is_bool($value)) {
        $text = $value ? '1' : '0';
    } elseif ($value instanceof Stringable) {
        $text = (string) $value;
    } else {
        $text = '';
    }

    return htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8',
        false
    );
}

/** @return list<string> */
function citizenActivityOptions(): array
{
    return [
        'Enseignement',
        'Santé',
        'Agent autorité',
        'Sécurité nationale',
        'Forces armées royales',
        'Autre',
    ];
}

function applicationUnicodeLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function applicationTrim(string $value): string
{
    $trimmed = preg_replace('/^\s+|\s+$/u', '', $value);
    return is_string($trimmed) ? $trimmed : trim($value);
}

function applicationStringIsValidUtf8(string $value): bool
{
    return preg_match('//u', $value) === 1;
}

function applicationStringHasControlCharacters(string $value): bool
{
    return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
}

/**
 * Application-format validation only. It does not claim legal validation of
 * Moroccan identity numbers. Current data uses a letter prefix followed by
 * digits, so the accepted normalized format is 1-4 ASCII letters and 4-15
 * digits, with a maximum total length of 20 characters.
 *
 * @return array{value:string,error:?string}
 */
function validateCitizenIdentifier(mixed $input): array
{
    if (!is_string($input)) {
        return ['value' => '', 'error' => 'Le CNIE doit être une valeur texte valide.'];
    }

    $value = applicationTrim($input);
    if ($value === '') {
        return ['value' => '', 'error' => 'Le CNIE est obligatoire.'];
    }
    if (!applicationStringIsValidUtf8($value)
        || applicationStringHasControlCharacters($value)
        || str_contains($value, '<')
        || str_contains($value, '>')) {
        return ['value' => '', 'error' => 'Le format du CNIE est invalide.'];
    }

    $value = strtoupper($value);
    if (strlen($value) > 20 || preg_match('/^[A-Z]{1,4}[0-9]{4,15}$/', $value) !== 1) {
        return ['value' => '', 'error' => 'Le CNIE doit contenir un préfixe alphabétique suivi de chiffres.'];
    }

    return ['value' => $value, 'error' => null];
}

/**
 * @return array{
 *   data:array{cnieCitoyen?:string,nomCit?:string,prenomCit?:string,adresseCit?:string,villeCit?:string,activiteCit?:string,dateNaissanceCit?:string,exCovid?:int,idCentre?:int},
 *   errors:array<string,string>,
 *   fatal:list<string>
 * }
 */
function validateCitizenSubmission(array $input): array
{
    $expectedFields = [
        'cnieCitoyen',
        'nomCit',
        'prenomCit',
        'adresseCit',
        'villeCit',
        'activiteCit',
        'dateNaissanceCit',
        'exCovid',
        'idCentre',
    ];
    $data = [];
    $errors = [];
    $fatal = [];

    $unexpectedFields = array_diff(array_keys($input), $expectedFields);
    if ($unexpectedFields !== []) {
        $fatal[] = 'La requête contient des champs inattendus.';
    }

    $payloadBytes = 0;
    foreach ($input as $field => $value) {
        if (!is_string($field) || !is_string($value)) {
            $fatal[] = 'La requête contient une structure de données invalide.';
            continue;
        }
        $payloadBytes += strlen($field) + strlen($value);
    }
    if ($payloadBytes > CITIZEN_APPLICATION_MAXIMUM_POST_BYTES) {
        $fatal[] = 'La requête dépasse la taille autorisée.';
    }

    foreach ($expectedFields as $field) {
        if (!array_key_exists($field, $input)) {
            $errors[$field] = 'Ce champ est obligatoire.';
        } elseif (!is_string($input[$field])) {
            $fatal[] = 'La requête contient une valeur de champ invalide.';
        }
    }

    if ($fatal !== []) {
        return [
            'data' => [],
            'errors' => $errors,
            'fatal' => array_values(array_unique($fatal)),
        ];
    }

    $identifier = validateCitizenIdentifier($input['cnieCitoyen'] ?? null);
    if ($identifier['error'] !== null) {
        $errors['cnieCitoyen'] = $identifier['error'];
    } else {
        $data['cnieCitoyen'] = $identifier['value'];
    }

    foreach ([
        'nomCit' => ['label' => 'Le nom', 'maximum' => 50],
        'prenomCit' => ['label' => 'Le prénom', 'maximum' => 50],
    ] as $field => $rules) {
        $value = applicationTrim($input[$field] ?? '');
        if ($value === '') {
            $errors[$field] = $rules['label'] . ' est obligatoire.';
        } elseif (!applicationStringIsValidUtf8($value)
            || applicationStringHasControlCharacters($value)
            || applicationUnicodeLength($value) > $rules['maximum']
            || preg_match("/^[\\p{L}\\p{M}]+(?:[ '\x{2019}-][\\p{L}\\p{M}]+)*$/u", $value) !== 1) {
            $errors[$field] = $rules['label'] . ' contient des caractères non autorisés ou dépasse la longueur maximale.';
        } else {
            $data[$field] = $value;
        }
    }

    foreach ([
        'adresseCit' => ['label' => 'L’adresse', 'maximum' => 100],
        'villeCit' => ['label' => 'La ville', 'maximum' => 50],
    ] as $field => $rules) {
        $value = applicationTrim($input[$field] ?? '');
        if ($value === '') {
            $errors[$field] = $rules['label'] . ' est obligatoire.';
        } elseif (!applicationStringIsValidUtf8($value)
            || applicationStringHasControlCharacters($value)
            || applicationUnicodeLength($value) > $rules['maximum']
            || str_contains($value, '<')
            || str_contains($value, '>')) {
            $errors[$field] = $rules['label'] . ' contient des caractères non autorisés ou dépasse la longueur maximale.';
        } else {
            $data[$field] = $value;
        }
    }

    $activity = applicationTrim($input['activiteCit'] ?? '');
    if (!in_array($activity, citizenActivityOptions(), true)) {
        $errors['activiteCit'] = 'L’activité sélectionnée est invalide.';
    } else {
        $data['activiteCit'] = $activity;
    }

    $birthDateInput = $input['dateNaissanceCit'] ?? '';
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDateInput);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $dateIsStrict = $birthDate instanceof DateTimeImmutable
        && ($dateErrors === false
            || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $birthDate->format('Y-m-d') === $birthDateInput;
    $today = new DateTimeImmutable('today');
    $minimumDate = new DateTimeImmutable(CITIZEN_APPLICATION_MINIMUM_BIRTH_DATE);
    if (!$dateIsStrict || $birthDate < $minimumDate || $birthDate > $today) {
        $errors['dateNaissanceCit'] = 'La date de naissance est invalide.';
    } else {
        $data['dateNaissanceCit'] = $birthDate->format('Y-m-d');
    }

    $covidStatus = $input['exCovid'] ?? null;
    if (!is_string($covidStatus) || !in_array($covidStatus, ['0', '1'], true)) {
        $errors['exCovid'] = 'Le statut COVID sélectionné est invalide.';
    } else {
        $data['exCovid'] = (int) $covidStatus;
    }

    $centreInput = $input['idCentre'] ?? null;
    if (!is_string($centreInput)
        || preg_match('/^[1-9][0-9]*$/', $centreInput) !== 1
        || filter_var($centreInput, FILTER_VALIDATE_INT) === false) {
        $errors['idCentre'] = 'Le centre sélectionné est invalide.';
    } else {
        $data['idCentre'] = (int) $centreInput;
    }

    return [
        'data' => $data,
        'errors' => $errors,
        'fatal' => [],
    ];
}

function citizenCentreExists(mysqli $connection, int $centreId): bool
{
    $statement = $connection->prepare('SELECT 1 FROM centres WHERE idCentre = ? LIMIT 1');
    $statement->bind_param('i', $centreId);
    $statement->execute();

    return $statement->get_result()->num_rows === 1;
}

/**
 * @param list<string> $messages
 * @param list<string> $fields
 */
function setCitizenCreationFlash(string $type, array $messages, array $fields = []): void
{
    $allowedTypes = ['success', 'validation', 'duplicate', 'error'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'error';
        $messages = ['Une erreur est survenue. Veuillez réessayer.'];
    }

    $safeMessages = [];
    foreach (array_slice($messages, 0, 12) as $message) {
        if (is_string($message) && $message !== '') {
            $safeMessages[] = $message;
        }
    }
    if ($safeMessages === []) {
        $safeMessages[] = 'Une erreur est survenue. Veuillez réessayer.';
    }

    $_SESSION[CITIZEN_CREATION_FLASH_KEY] = [
        'type' => $type,
        'messages' => $safeMessages,
        'fields' => array_values(array_intersect($fields, [
            'cnieCitoyen',
            'nomCit',
            'prenomCit',
            'adresseCit',
            'villeCit',
            'activiteCit',
            'dateNaissanceCit',
            'exCovid',
            'idCentre',
        ])),
    ];
}

/** @return array{type:string,messages:list<string>,fields:list<string>}|null */
function consumeCitizenCreationFlash(): ?array
{
    $flash = $_SESSION[CITIZEN_CREATION_FLASH_KEY] ?? null;
    unset($_SESSION[CITIZEN_CREATION_FLASH_KEY]);

    if (!is_array($flash)
        || !is_string($flash['type'] ?? null)
        || !is_array($flash['messages'] ?? null)) {
        return null;
    }

    $allowedTypes = ['success', 'validation', 'duplicate', 'error'];
    if (!in_array($flash['type'], $allowedTypes, true)) {
        return null;
    }

    $messages = array_values(array_filter(
        $flash['messages'],
        static fn(mixed $message): bool => is_string($message) && $message !== ''
    ));

    $fields = is_array($flash['fields'] ?? null)
        ? array_values(array_filter(
            $flash['fields'],
            static fn(mixed $field): bool => is_string($field)
        ))
        : [];

    return $messages === []
        ? null
        : ['type' => $flash['type'], 'messages' => $messages, 'fields' => $fields];
}
