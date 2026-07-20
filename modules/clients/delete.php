<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    setFlash('error', 'Requête invalide.');
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare('SELECT nom_entreprise FROM clients WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $target = $stmt->fetch();

    if ($target) {
        logActivity($pdo, (int)$user['id'], 'suppression_client', 'Suppression du client ' . $target['nom_entreprise'], $id);
        // ON DELETE CASCADE supprime automatiquement les sites/visites/rapports liés.
        // ON DELETE SET NULL sur journal_activite.client_id conserve la ligne de log ci-dessus.
        $del = $pdo->prepare('DELETE FROM clients WHERE id = :id');
        $del->execute([':id' => $id]);
        setFlash('success', 'Client supprimé.');
    } else {
        setFlash('error', 'Client introuvable.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Suppression impossible : ' . $e->getMessage());
}

header('Location: index.php');
exit;