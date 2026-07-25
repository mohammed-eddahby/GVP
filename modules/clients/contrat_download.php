<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']); // même accès que le reste du module Clients
$pdo = getPDO();
$user = currentUser($pdo); // vérifie aussi que le compte est toujours actif

$id = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'download') === 'view' ? 'view' : 'download';

$stmt = $pdo->prepare('SELECT nom_entreprise, contrat_maintenance_path FROM clients WHERE id = :id');
$stmt->execute([':id' => $id]);
$client = $stmt->fetch();

if (!$client || empty($client['contrat_maintenance_path'])) {
    setFlash('error', 'Aucun contrat de maintenance pour ce client.');
    header('Location: index.php');
    exit;
}

// Résolution et validation du chemin réel (empêche toute traversée de répertoire)
$racineProjet = realpath(__DIR__ . '/../../');
$dossierAutorise = $racineProjet ? realpath($racineProjet . '/uploads/contrats') : false;
$cheminFichier = $racineProjet ? realpath($racineProjet . '/' . $client['contrat_maintenance_path']) : false;

if (
    $cheminFichier === false
    || $dossierAutorise === false
    || strpos($cheminFichier, $dossierAutorise) !== 0
    || !is_file($cheminFichier)
) {
    setFlash('error', 'Contrat introuvable sur le serveur.');
    header('Location: index.php');
    exit;
}

$nomTelecharge = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)$client['nom_entreprise']);
$nomTelecharge = trim($nomTelecharge, '_') ?: 'contrat';
$nomTelecharge .= '_contrat_maintenance.pdf';

logActivity(
    $pdo,
    (int)$user['id'],
    'telechargement_contrat',
    ($mode === 'view' ? 'Consultation' : 'Téléchargement') . ' du contrat de maintenance de "' . $client['nom_entreprise'] . '"',
    $id,
    null,
    'client',
    $id
);

// Nettoie tout tampon de sortie pour éviter de corrompre le flux binaire
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($mode === 'view' ? 'inline' : 'attachment') . '; filename="' . $nomTelecharge . '"');
header('Content-Length: ' . filesize($cheminFichier));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($cheminFichier);
exit;