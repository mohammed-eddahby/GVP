<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload_helpers.php';

requireLogin();
$pdo = getPDO();
$user = currentUser($pdo);
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    setFlash('error', 'Requête invalide.');
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM rapports WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();

$isOwnerDraft = $target && $role === 'technicien' && (int)$target['redige_par'] === (int)$user['id'] && $target['statut'] === 'brouillon';

if (!$target || (!can('rapports.valider') && $role !== 'administrateur' && !$isOwnerDraft)) {
    setFlash('error', 'Suppression non autorisée.');
    header('Location: index.php');
    exit;
}

try {
    $siteIdStmt = $pdo->prepare('SELECT site_id FROM visites WHERE id = :id');
    $siteIdStmt->execute([':id' => (int)$target['visite_id']]);
    $siteIdRapport = $siteIdStmt->fetchColumn();

    $del = $pdo->prepare('DELETE FROM rapports WHERE id = :id');
    $del->execute([':id' => $id]);
    supprimerDocumentRapport($target['document_path'] ?? null);
    logActivity($pdo, (int)$user['id'], 'suppression_rapport', 'Suppression du rapport "' . $target['titre'] . '"', null, $siteIdRapport !== false ? (int)$siteIdRapport : null);
    setFlash('success', 'Rapport supprimé.');
} catch (Throwable $e) {
    setFlash('error', 'Suppression impossible : ' . $e->getMessage());
}

header('Location: index.php');
exit;