<?php
/**
 * includes/upload_helpers.php
 * Traitement sécurisé de l'upload d'un document (PDF/Word) joint à un rapport.
 * A inclure APRES config/database.php et includes/auth.php.
 */
declare(strict_types=1);

/**
 * Valide et enregistre le document joint à un rapport (PDF, DOC ou DOCX).
 *
 * @param array $fichier Une entrée de $_FILES (ex: $_FILES['document'])
 * @return array{success:bool, error:?string, document_path:?string, document_type:?string}
 */
function traiterDocumentRapport(array $fichier): array
{
    $result = ['success' => false, 'error' => null, 'document_path' => null, 'document_type' => null];

    // Aucun fichier sélectionné : la pièce jointe est optionnelle, ce n'est pas une erreur.
    if (!isset($fichier['error']) || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
        $result['success'] = true;
        return $result;
    }

    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée.',
            UPLOAD_ERR_PARTIAL    => "Le fichier n'a été que partiellement téléversé. Veuillez réessayer.",
            UPLOAD_ERR_NO_TMP_DIR => 'Erreur serveur : dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => "Erreur serveur : impossible d'écrire le fichier sur le disque.",
            UPLOAD_ERR_EXTENSION  => 'Le téléversement a été bloqué par une extension du serveur.',
        ];
        $result['error'] = $messages[$fichier['error']] ?? 'Erreur lors du téléversement du fichier.';
        return $result;
    }

    if (!is_uploaded_file($fichier['tmp_name'])) {
        $result['error'] = 'Téléversement invalide.';
        return $result;
    }

    // --- Taille maximale : 10 Mo ---
    $tailleMax = 10 * 1024 * 1024;
    if ((int)$fichier['size'] <= 0) {
        $result['error'] = 'Le fichier envoyé est vide ou invalide.';
        return $result;
    }
    if ((int)$fichier['size'] > $tailleMax) {
        $result['error'] = 'Le fichier dépasse la taille maximale autorisée (10 Mo).';
        return $result;
    }

    // --- Extension autorisée (déclarée par le nom du fichier) ---
    $extension = strtolower(pathinfo((string)$fichier['name'], PATHINFO_EXTENSION));
    $extensionsAutorisees = ['pdf', 'doc', 'docx'];
    if (!in_array($extension, $extensionsAutorisees, true)) {
        $result['error'] = 'Format de fichier non autorisé. Seuls les fichiers PDF, DOC et DOCX sont acceptés.';
        return $result;
    }

    // --- Vérification du type MIME réel du contenu (indépendant du nom déclaré) ---
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $fichier['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $mimeAutorises = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        // Les fichiers .docx sont en réalité des archives ZIP : selon la base
        // fileinfo installée sur le serveur, le MIME détecté peut être soit le
        // type Office officiel, soit application/zip. On accepte les deux mais
        // on vérifie ensuite la signature binaire pour confirmer un vrai ZIP.
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
    ];

    if ($mimeType === false || !in_array($mimeType, $mimeAutorises[$extension] ?? [], true)) {
        $result['error'] = 'Le contenu du fichier ne correspond pas à un document PDF ou Word valide.';
        return $result;
    }

    // --- Vérification de la signature binaire (empêche un fichier renommé/malveillant) ---
    $handle = fopen($fichier['tmp_name'], 'rb');
    $entete = $handle ? fread($handle, 8) : '';
    if ($handle) {
        fclose($handle);
    }

    if ($extension === 'pdf' && substr((string)$entete, 0, 4) !== '%PDF') {
        $result['error'] = 'Le fichier PDF semble corrompu ou invalide.';
        return $result;
    }
    if ($extension === 'docx' && substr((string)$entete, 0, 4) !== "PK\x03\x04") {
        $result['error'] = 'Le fichier DOCX semble corrompu ou invalide.';
        return $result;
    }
    if ($extension === 'doc' && substr((string)$entete, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
        $result['error'] = 'Le fichier DOC semble corrompu ou invalide.';
        return $result;
    }

    // --- Enregistrement sur le serveur avec un nom de fichier unique ---
    $dossierUpload = __DIR__ . '/../uploads/rapports/';
    if (!is_dir($dossierUpload) && !mkdir($dossierUpload, 0755, true) && !is_dir($dossierUpload)) {
        $result['error'] = "Erreur serveur : impossible de créer le dossier de destination.";
        return $result;
    }

    $nomUnique = bin2hex(random_bytes(16)) . '.' . $extension;
    $cheminDestination = $dossierUpload . $nomUnique;

    if (!move_uploaded_file($fichier['tmp_name'], $cheminDestination)) {
        $result['error'] = "Erreur lors de l'enregistrement du fichier sur le serveur.";
        return $result;
    }
    @chmod($cheminDestination, 0644);

    $result['success'] = true;
    $result['document_path'] = 'uploads/rapports/' . $nomUnique;
    $result['document_type'] = $extension;
    return $result;
}

/**
 * Supprime physiquement un document précédemment joint (best-effort, ne casse jamais l'appelant).
 */
function supprimerDocumentRapport(?string $documentPath): void
{
    if (!$documentPath) {
        return;
    }
    $racine = realpath(__DIR__ . '/..');
    $dossierAutorise = realpath(__DIR__ . '/../uploads/rapports');
    $cible = $racine ? realpath($racine . '/' . $documentPath) : false;

    if ($cible !== false && $dossierAutorise !== false && strpos($cible, $dossierAutorise) === 0 && is_file($cible)) {
        @unlink($cible);
    }
}

/**
 * Valide et enregistre le contrat de maintenance (PDF uniquement) d'un client.
 * Même logique de sécurité que traiterDocumentRapport() ci-dessus (taille,
 * extension, MIME réel, signature binaire, nom de fichier unique), mais
 * restreinte au PDF puisque le contrat de maintenance ne doit être qu'un PDF.
 *
 * @param array $fichier Une entrée de $_FILES (ex: $_FILES['contrat_maintenance'])
 * @return array{success:bool, error:?string, contrat_path:?string}
 */
function traiterContratMaintenance(array $fichier): array
{
    $result = ['success' => false, 'error' => null, 'contrat_path' => null];

    // Aucun fichier sélectionné : c'est optionnel (on garde l'ancien contrat, s'il existe).
    if (!isset($fichier['error']) || $fichier['error'] === UPLOAD_ERR_NO_FILE) {
        $result['success'] = true;
        return $result;
    }

    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée.',
            UPLOAD_ERR_PARTIAL    => "Le fichier n'a été que partiellement téléversé. Veuillez réessayer.",
            UPLOAD_ERR_NO_TMP_DIR => 'Erreur serveur : dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => "Erreur serveur : impossible d'écrire le fichier sur le disque.",
            UPLOAD_ERR_EXTENSION  => 'Le téléversement a été bloqué par une extension du serveur.',
        ];
        $result['error'] = $messages[$fichier['error']] ?? 'Erreur lors du téléversement du fichier.';
        return $result;
    }

    if (!is_uploaded_file($fichier['tmp_name'])) {
        $result['error'] = 'Téléversement invalide.';
        return $result;
    }

    // --- Taille maximale : 10 Mo ---
    $tailleMax = 10 * 1024 * 1024;
    if ((int)$fichier['size'] <= 0) {
        $result['error'] = 'Le fichier envoyé est vide ou invalide.';
        return $result;
    }
    if ((int)$fichier['size'] > $tailleMax) {
        $result['error'] = 'Le fichier dépasse la taille maximale autorisée (10 Mo).';
        return $result;
    }

    // --- Seul le PDF est accepté pour le contrat de maintenance ---
    $extension = strtolower(pathinfo((string)$fichier['name'], PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        $result['error'] = 'Seuls les fichiers PDF sont acceptés pour le contrat de maintenance.';
        return $result;
    }

    // --- Vérification du type MIME réel du contenu (indépendant du nom déclaré) ---
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $fichier['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mimeType !== 'application/pdf') {
        $result['error'] = 'Le contenu du fichier ne correspond pas à un PDF valide.';
        return $result;
    }

    // --- Vérification de la signature binaire (empêche un fichier renommé/malveillant) ---
    $handle = fopen($fichier['tmp_name'], 'rb');
    $entete = $handle ? fread($handle, 4) : '';
    if ($handle) {
        fclose($handle);
    }
    if (substr((string)$entete, 0, 4) !== '%PDF') {
        $result['error'] = 'Le fichier PDF semble corrompu ou invalide.';
        return $result;
    }

    // --- Enregistrement sur le serveur avec un nom de fichier unique ---
    $dossierUpload = __DIR__ . '/../uploads/contrats/';
    if (!is_dir($dossierUpload) && !mkdir($dossierUpload, 0755, true) && !is_dir($dossierUpload)) {
        $result['error'] = "Erreur serveur : impossible de créer le dossier de destination.";
        return $result;
    }

    $nomUnique = bin2hex(random_bytes(16)) . '.pdf';
    $cheminDestination = $dossierUpload . $nomUnique;

    if (!move_uploaded_file($fichier['tmp_name'], $cheminDestination)) {
        $result['error'] = "Erreur lors de l'enregistrement du fichier sur le serveur.";
        return $result;
    }
    @chmod($cheminDestination, 0644);

    $result['success'] = true;
    $result['contrat_path'] = 'uploads/contrats/' . $nomUnique;
    return $result;
}

/**
 * Supprime physiquement un ancien contrat de maintenance (best-effort, ne casse jamais l'appelant).
 * Utilisé quand un client remplace son contrat par un nouveau PDF.
 */
function supprimerContratMaintenance(?string $contratPath): void
{
    if (!$contratPath) {
        return;
    }
    $racine = realpath(__DIR__ . '/..');
    $dossierAutorise = realpath(__DIR__ . '/../uploads/contrats');
    $cible = $racine ? realpath($racine . '/' . $contratPath) : false;

    if ($cible !== false && $dossierAutorise !== false && strpos($cible, $dossierAutorise) === 0 && is_file($cible)) {
        @unlink($cible);
    }
}