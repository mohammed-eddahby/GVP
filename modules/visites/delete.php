<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

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

$stmt = $pdo->prepare('SELECT * FROM visites WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();

if (!$target || !can('visites.gerer')) {
    setFlash('error', 'Suppression non autorisée.');
    header('Location: index.php');
    exit;
}

try {
    $del = $pdo->prepare('DELETE FROM visites WHERE id = :id');
    $del->execute([':id' => $id]);
    logActivity($pdo, (int)$user['id'], 'suppression_visite', 'Suppression de la visite du ' . $target['date_prevue'], null, (int)$target['site_id'], 'visite', $id);
    setFlash('success', 'Visite supprimée.');
} catch (Throwable $e) {
    setFlash('error', 'Suppression impossible : ' . $e->getMessage());
}

header('Location: index.php');
exit;