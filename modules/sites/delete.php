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
    $stmt = $pdo->prepare('SELECT nom_site, client_id FROM sites WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $target = $stmt->fetch();

    if ($target) {
        logActivity($pdo, (int)$user['id'], 'suppression_site', 'Suppression du site ' . $target['nom_site'], (int)$target['client_id'], $id, 'site', $id);
        $del = $pdo->prepare('DELETE FROM sites WHERE id = :id');
        $del->execute([':id' => $id]);
        setFlash('success', 'Site supprimé.');
    } else {
        setFlash('error', 'Site introuvable.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Suppression impossible : ' . $e->getMessage());
}

header('Location: index.php');
exit;