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
        // Compter toutes les visites réelles liées aux sites de ce client
        // (et non simplement le nombre de sites).
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM visites v
             INNER JOIN sites s ON s.id = v.site_id
             WHERE s.client_id = :id'
        );
        $countStmt->execute([':id' => $id]);
        $nbVisites = (int)$countStmt->fetchColumn();

        if ($nbVisites > 0) {
            setFlash('error', 'Impossible de supprimer ce client car il possède des visites enregistrées.');
        } else {
            logActivity($pdo, (int)$user['id'], 'suppression_client', 'Suppression du client ' . $target['nom_entreprise'], $id);
            // ON DELETE CASCADE supprime automatiquement les sites/rapports liés (sans visite ici).
            // ON DELETE SET NULL sur journal_activite.client_id conserve la ligne de log ci-dessus.
            $del = $pdo->prepare('DELETE FROM clients WHERE id = :id');
            $del->execute([':id' => $id]);
            setFlash('success', 'Client supprimé.');
        }
    } else {
        setFlash('error', 'Client introuvable.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Suppression impossible : ' . $e->getMessage());
}

header('Location: index.php');
exit;