<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$pdo = getPDO();
$user = currentUser($pdo); // vérifie aussi que le compte est toujours actif

$id = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'download') === 'view' ? 'view' : 'download';

$stmt = $pdo->prepare('SELECT titre, visite_id, document_path, document_type FROM rapports WHERE id = :id');
$stmt->execute([':id' => $id]);
$rapport = $stmt->fetch();

$redirectUrl = $rapport ? '../visites/view.php?id=' . (int)$rapport['visite_id'] : '../visites/index.php';

if (!$rapport || empty($rapport['document_path'])) {
    setFlash('error', 'Aucun document joint pour ce rapport.');
    header('Location: ' . $redirectUrl);
    exit;
}

// Résolution et validation du chemin réel (empêche toute traversée de répertoire)
$racineProjet = realpath(__DIR__ . '/../../');
$dossierAutorise = $racineProjet ? realpath($racineProjet . '/uploads/rapports') : false;
$cheminFichier = $racineProjet ? realpath($racineProjet . '/' . $rapport['document_path']) : false;

if (
    $cheminFichier === false
    || $dossierAutorise === false
    || strpos($cheminFichier, $dossierAutorise) !== 0
    || !is_file($cheminFichier)
) {
    setFlash('error', 'Document introuvable sur le serveur.');
    header('Location: ' . $redirectUrl);
    exit;
}

$typesMime = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$extension = $rapport['document_type'] ?: pathinfo($cheminFichier, PATHINFO_EXTENSION);
$mime = $typesMime[$extension] ?? 'application/octet-stream';

$nomTelecharge = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)$rapport['titre']);
$nomTelecharge = trim($nomTelecharge, '_') ?: 'rapport';
$nomTelecharge .= '.' . $extension;

logActivity(
    $pdo,
    (int)$user['id'],
    'telechargement_rapport',
    ($mode === 'view' ? 'Consultation' : 'Téléchargement') . ' du document du rapport "' . $rapport['titre'] . '"',
    null,
    null,
    'rapport',
    $id
);

// Nettoie tout tampon de sortie pour éviter de corrompre le flux binaire
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($mode === 'view' ? 'inline' : 'attachment') . '; filename="' . $nomTelecharge . '"');
header('Content-Length: ' . filesize($cheminFichier));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($cheminFichier);
exit;