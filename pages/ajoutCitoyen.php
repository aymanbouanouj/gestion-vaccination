<?php

require_once __DIR__ . '/../config/security.php';
requireAuthentication();

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Méthode non autorisée.');
}

if (!verifyCsrfToken('citizen_create', $_POST['csrf_token'] ?? null)) {
    rejectInvalidCsrfToken();
}

require_once __DIR__ . '/../config/application.php';
require_once __DIR__ . '/../config/connexion.php';

$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($contentLength !== false && $contentLength > CITIZEN_APPLICATION_MAXIMUM_POST_BYTES) {
    setCitizenCreationFlash('validation', ['La requête dépasse la taille autorisée.']);
    header('Location: formAjout.php', true, 303);
    exit;
}

$businessInput = $_POST;
unset($businessInput['csrf_token']);
$validation = validateCitizenSubmission($businessInput);

if ($validation['fatal'] !== []) {
    setCitizenCreationFlash('validation', $validation['fatal']);
    header('Location: formAjout.php', true, 303);
    exit;
}

if ($validation['errors'] !== []) {
    setCitizenCreationFlash(
        'validation',
        array_values($validation['errors']),
        array_keys($validation['errors'])
    );
    header('Location: formAjout.php', true, 303);
    exit;
}

$data = $validation['data'];

try {
    if (!citizenCentreExists($conn, $data['idCentre'])) {
        setCitizenCreationFlash('validation', ['Le centre sélectionné n’existe pas.'], ['idCentre']);
        header('Location: formAjout.php', true, 303);
        exit;
    }

    $duplicateCheck = $conn->prepare(
        'SELECT 1 FROM citoyens WHERE cnieCitoyen = ? LIMIT 1'
    );
    $duplicateCheck->bind_param('s', $data['cnieCitoyen']);
    $duplicateCheck->execute();

    if ($duplicateCheck->get_result()->num_rows > 0) {
        setCitizenCreationFlash('duplicate', ['Un citoyen avec ce CNIE existe déjà.'], ['cnieCitoyen']);
        header('Location: formAjout.php', true, 303);
        exit;
    }

    $insert = $conn->prepare(
        'INSERT INTO citoyens '
        . '(cnieCitoyen, nomCit, prenomCit, adresseCit, villeCit, activiteCit, '
        . 'dateNaissanceCit, exCovid, idCentre) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param(
        'sssssssii',
        $data['cnieCitoyen'],
        $data['nomCit'],
        $data['prenomCit'],
        $data['adresseCit'],
        $data['villeCit'],
        $data['activiteCit'],
        $data['dateNaissanceCit'],
        $data['exCovid'],
        $data['idCentre']
    );
    $insert->execute();

    if ($insert->affected_rows !== 1) {
        throw new RuntimeException('Unexpected citizen insertion row count.');
    }

    setCitizenCreationFlash('success', ['Le citoyen a été enregistré avec succès.']);
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1062) {
        setCitizenCreationFlash('duplicate', ['Un citoyen avec ce CNIE existe déjà.'], ['cnieCitoyen']);
    } else {
        error_log('[gestion_vaccination] Citizen creation database failure [code=CITIZEN_CREATE_DB].');
        setCitizenCreationFlash('error', ['L’enregistrement n’a pas pu être effectué. Veuillez réessayer.']);
    }
} catch (Throwable $exception) {
    error_log('[gestion_vaccination] Citizen creation application failure [code=CITIZEN_CREATE_APP].');
    setCitizenCreationFlash('error', ['L’enregistrement n’a pas pu être effectué. Veuillez réessayer.']);
}

header('Location: formAjout.php', true, 303);
exit;
