<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!in_array($action, ['valider', 'rejeter'], true)) {
    setFlash('error', 'Action invalide.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT r.titre, r.statut, v.site_id
         FROM rapports r JOIN visites v ON v.id = r.visite_id
         WHERE r.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $rapport = $stmt->fetch();

    if (!$rapport || $rapport['statut'] !== 'soumis') {
        setFlash('error', 'Ce rapport ne peut pas être traité.');
        header('Location: index.php');
        exit;
    }

    $newStatut = $action === 'valider' ? 'valide' : 'rejete';
    $upd = $pdo->prepare(
        'UPDATE rapports SET statut=:statut, valide_par=:vp, date_validation=NOW() WHERE id=:id'
    );
    $upd->execute([':statut' => $newStatut, ':vp' => $user['id'], ':id' => $id]);

    logActivity($pdo, (int)$user['id'], 'validation_rapport', ucfirst($action) . ' du rapport "' . $rapport['titre'] . '"', null, (int)$rapport['site_id']);
    setFlash('success', 'Rapport ' . ($action === 'valider' ? 'validé' : 'rejeté') . '.');
} catch (Throwable $e) {
    setFlash('error', 'Erreur : ' . $e->getMessage());
}

header('Location: index.php');
exit;