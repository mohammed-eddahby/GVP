<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload_helpers.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();
if (!$target) {
    setFlash('error', 'Client introuvable.');
    header('Location: index.php');
    exit;
}

$errors = [];
$form = [
    'nom_entreprise' => $target['nom_entreprise'], 'contact_nom' => $target['contact_nom'],
    'email' => $target['email'], 'telephone' => $target['telephone'],
    'adresse' => $target['adresse'], 'ville' => $target['ville'], 'actif' => (int)$target['actif'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cas particulier : si le fichier envoyé dépasse post_max_size (php.ini), PHP vide
    // silencieusement $_POST et $_FILES sans code d'erreur exploitable.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Le fichier envoyé est trop volumineux pour le serveur. Réduisez sa taille (max 10 Mo) et réessayez.';
    }

    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    foreach (['nom_entreprise','contact_nom','email','telephone','adresse','ville'] as $k) $form[$k] = trim($_POST[$k] ?? '');
    $form['actif'] = isset($_POST['actif']) ? 1 : 0;

    if ($form['nom_entreprise'] === '') $errors[] = 'Le nom de l\'entreprise est obligatoire.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';

    $contrat = ['success' => true, 'contrat_path' => null];
    if (!$errors) {
        $contrat = traiterContratMaintenance($_FILES['contrat_maintenance'] ?? []);
        if (!$contrat['success']) {
            $errors[] = $contrat['error'];
        }
    }

    if (!$errors) {
        // Un nouveau PDF a été envoyé : on remplace le chemin stocké et on
        // supprime physiquement l'ancien fichier (s'il y en avait un).
        $nouveauCheminContrat = $target['contrat_maintenance_path'];
        if ($contrat['contrat_path'] !== null) {
            supprimerContratMaintenance($target['contrat_maintenance_path']);
            $nouveauCheminContrat = $contrat['contrat_path'];
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE clients SET nom_entreprise=:e, contact_nom=:c, email=:em, telephone=:t, adresse=:a, ville=:v, actif=:actif, contrat_maintenance_path=:contrat WHERE id=:id'
            );
            $stmt->execute([
                ':e' => $form['nom_entreprise'], ':c' => $form['contact_nom'] ?: null, ':em' => $form['email'] ?: null,
                ':t' => $form['telephone'] ?: null, ':a' => $form['adresse'] ?: null, ':v' => $form['ville'] ?: null,
                ':actif' => $form['actif'], ':contrat' => $nouveauCheminContrat, ':id' => $id,
            ]);
            logActivity($pdo, (int)$user['id'], 'modification_client', 'Modification du client ' . $form['nom_entreprise'], $id);
            if ((int)$target['actif'] !== (int)$form['actif']) {
                logActivity(
                    $pdo,
                    (int)$user['id'],
                    'changement_statut_client',
                    'Statut du client ' . $form['nom_entreprise'] . ' changé en ' . ($form['actif'] ? 'Actif' : 'Inactif'),
                    $id
                );
            }
            setFlash('success', 'Client mis à jour.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Modifier client';
$activeNav = 'clients';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1>Modifier : <?=htmlspecialchars($target['nom_entreprise'])?></h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <form method="post" enctype="multipart/form-data" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field full">
                <label for="nom_entreprise">Nom de l'entreprise</label>
                <input id="nom_entreprise" name="nom_entreprise" type="text" required value="<?=htmlspecialchars($form['nom_entreprise'])?>">
              </div>
              <div class="field">
                <label for="contact_nom">Nom du contact</label>
                <input id="contact_nom" name="contact_nom" type="text" value="<?=htmlspecialchars((string)$form['contact_nom'])?>">
              </div>
              <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?=htmlspecialchars((string)$form['email'])?>">
              </div>
              <div class="field">
                <label for="telephone">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" value="<?=htmlspecialchars((string)$form['telephone'])?>">
              </div>
              <div class="field">
                <label for="ville">Ville</label>
                <input id="ville" name="ville" type="text" value="<?=htmlspecialchars((string)$form['ville'])?>">
              </div>
              <div class="field full">
                <label for="adresse">Adresse</label>
                <input id="adresse" name="adresse" type="text" value="<?=htmlspecialchars((string)$form['adresse'])?>">
              </div>
              <div class="field">
                <label for="actif">Statut</label>
                <label style="display:flex;align-items:center;gap:8px;">
                  <input id="actif" name="actif" type="checkbox" style="width:auto;" <?= $form['actif'] ? 'checked' : '' ?>>
                  Client actif
                </label>
              </div>
            </div>

            <div class="form-grid">
              <div class="field full">
                <label>Contrat de maintenance</label>
                <?php if (!empty($target['contrat_maintenance_path'])): ?>
                <p>
                  <span class="badge success">Contrat disponible</span>
                  &nbsp;—&nbsp;
                  <a href="contrat_download.php?id=<?=$target['id']?>&mode=view" target="_blank" rel="noopener">Voir le contrat</a>
                  &nbsp;|&nbsp;
                  <a href="contrat_download.php?id=<?=$target['id']?>&mode=download">Télécharger</a>
                </p>
                <?php else: ?>
                <p style="color:var(--text-muted);">Aucun contrat de maintenance pour le moment.</p>
                <?php endif; ?>
              </div>
              <div class="field full">
                <label for="contrat_maintenance"><?= !empty($target['contrat_maintenance_path']) ? 'Remplacer le contrat (PDF)' : 'Téléverser le contrat (PDF)' ?></label>
                <input id="contrat_maintenance" name="contrat_maintenance" type="file" accept="application/pdf,.pdf">
                <small class="hint">Fichier PDF uniquement, 10 Mo maximum. Laissez vide pour conserver le contrat actuel.</small>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>