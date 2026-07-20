<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$pdo = getPDO();
$user = currentUser($pdo);
$role = $user['role'];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM rapports WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();
if (!$target) {
    setFlash('error', 'Rapport introuvable.');
    header('Location: index.php');
    exit;
}

$isOwner = $role === 'technicien' && (int)$target['redige_par'] === (int)$user['id'];
if (!$isOwner || !in_array($target['statut'], ['brouillon', 'rejete'], true)) {
    requireRole(['__forbidden__']);
}

$errors = [];
$form = ['titre' => $target['titre'], 'contenu' => $target['contenu'], 'soumettre' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    $form['titre'] = trim($_POST['titre'] ?? '');
    $form['contenu'] = trim($_POST['contenu'] ?? '');
    $form['soumettre'] = isset($_POST['soumettre']);

    if ($form['titre'] === '') $errors[] = 'Le titre est obligatoire.';

    if (!$errors) {
        try {
            $statut = $form['soumettre'] ? 'soumis' : 'brouillon';
            $stmt = $pdo->prepare(
                'UPDATE rapports SET titre=:titre, contenu=:contenu, statut=:statut, date_soumission=:ds,
                 valide_par=NULL, date_validation=NULL WHERE id=:id'
            );
            $stmt->execute([
                ':titre' => $form['titre'], ':contenu' => $form['contenu'] ?: null, ':statut' => $statut,
                ':ds' => $form['soumettre'] ? date('Y-m-d H:i:s') : null, ':id' => $id,
            ]);
            $siteIdStmt = $pdo->prepare('SELECT site_id FROM visites WHERE id = :id');
            $siteIdStmt->execute([':id' => (int)$target['visite_id']]);
            $siteIdRapport = $siteIdStmt->fetchColumn();
            logActivity($pdo, (int)$user['id'], 'modification_rapport', 'Modification du rapport "' . $form['titre'] . '"', null, $siteIdRapport !== false ? (int)$siteIdRapport : null);
            setFlash('success', 'Rapport mis à jour' . ($form['soumettre'] ? ' et soumis' : '') . '.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Modifier rapport';
$activeNav = 'rapports';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1>Modifier le rapport</h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field full">
                <label for="titre">Titre du rapport</label>
                <input id="titre" name="titre" type="text" required value="<?=htmlspecialchars($form['titre'])?>">
              </div>
              <div class="field full">
                <label for="contenu">Contenu</label>
                <textarea id="contenu" name="contenu" style="min-height:200px;"><?=htmlspecialchars((string)$form['contenu'])?></textarea>
              </div>
              <div class="field full">
                <label style="display:flex;align-items:center;gap:8px;">
                  <input type="checkbox" name="soumettre" style="width:auto;">
                  Soumettre pour validation (sinon reste en brouillon)
                </label>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>