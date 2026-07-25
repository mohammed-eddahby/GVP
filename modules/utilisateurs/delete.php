<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['administrateur']);
$pdo = getPDO();
$user = currentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    setFlash('error', 'Requête invalide.');
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id === (int)$user['id']) {
    setFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT nom, prenom FROM utilisateurs WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $target = $stmt->fetch();

    if ($target) {
        $del = $pdo->prepare('DELETE FROM utilisateurs WHERE id = :id');
        $del->execute([':id' => $id]);
        logActivity($pdo, (int)$user['id'], 'suppression_utilisateur', 'Suppression de ' . $target['prenom'] . ' ' . $target['nom'], null, null, 'utilisateur', $id);
        setFlash('success', 'Utilisateur supprimé.');
    } else {
        setFlash('error', 'Utilisateur introuvable.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Suppression impossible : ' . $e->getMessage());
}

header('Location: index.php');
exit;
