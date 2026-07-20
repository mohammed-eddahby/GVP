<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload_helpers.php';

requireRole(['technicien']);
$pdo = getPDO();
$user = currentUser($pdo);

$visiteId = (int)($_GET['visite_id'] ?? $_POST['visite_id'] ?? 0);

// Le technicien ne peut créer un rapport que pour une visite qui lui est assignée.
$visitesList = $pdo->prepare(
    "SELECT v.id, v.type_visite, v.date_prevue, s.nom_site, c.nom_entreprise
     FROM visites v JOIN sites s ON s.id = v.site_id JOIN clients c ON c.id = s.client_id
     WHERE v.technicien_id = :uid ORDER BY v.date_prevue DESC"
);
$visitesList->execute([':uid' => $user['id']]);
$visitesList = $visitesList->fetchAll();

$errors = [];
$form = ['visite_id' => $visiteId ?: '', 'titre' => 'Rapport de visite', 'contenu' => '', 'soumettre' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cas particulier : si le fichier envoyé dépasse post_max_size (php.ini), PHP vide
    // silencieusement $_POST et $_FILES sans code d'erreur exploitable. On le détecte ici.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Le fichier envoyé est trop volumineux pour le serveur. Réduisez sa taille (max 10 Mo) et réessayez.';
    }

    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    $form['visite_id'] = trim($_POST['visite_id'] ?? '');
    $form['titre'] = trim($_POST['titre'] ?? '');
    $form['contenu'] = trim($_POST['contenu'] ?? '');
    $form['soumettre'] = isset($_POST['soumettre']);

    if ($form['visite_id'] === '' || !ctype_digit($form['visite_id'])) $errors[] = 'Veuillez sélectionner une visite.';
    if ($form['titre'] === '') $errors[] = 'Le titre est obligatoire.';

    if (!$errors) {
        // Vérifie que la visite appartient bien au technicien connecté
        $check = $pdo->prepare('SELECT id FROM visites WHERE id = :id AND technicien_id = :uid');
        $check->execute([':id' => (int)$form['visite_id'], ':uid' => $user['id']]);
        if (!$check->fetch()) {
            $errors[] = 'Cette visite ne vous est pas assignée.';
        }
    }

    $document = ['success' => true, 'document_path' => null, 'document_type' => null];
    if (!$errors) {
        $document = traiterDocumentRapport($_FILES['document'] ?? []);
        if (!$document['success']) {
            $errors[] = $document['error'];
        }
    }

    if (!$errors) {
        try {
            $statut = $form['soumettre'] ? 'soumis' : 'brouillon';
            $stmt = $pdo->prepare(
                'INSERT INTO rapports (visite_id, redige_par, titre, contenu, statut, date_soumission, document_path, document_type)
                 VALUES (:vid, :uid, :titre, :contenu, :statut, :ds, :doc_path, :doc_type)'
            );
            $stmt->execute([
                ':vid' => (int)$form['visite_id'], ':uid' => $user['id'], ':titre' => $form['titre'],
                ':contenu' => $form['contenu'] ?: null, ':statut' => $statut,
                ':ds' => $form['soumettre'] ? date('Y-m-d H:i:s') : null,
                ':doc_path' => $document['document_path'], ':doc_type' => $document['document_type'],
            ]);
            $siteIdStmt = $pdo->prepare('SELECT site_id FROM visites WHERE id = :id');
            $siteIdStmt->execute([':id' => (int)$form['visite_id']]);
            $siteIdRapport = $siteIdStmt->fetchColumn();
            logActivity($pdo, (int)$user['id'], 'creation_rapport', 'Création du rapport "' . $form['titre'] . '"', null, $siteIdRapport !== false ? (int)$siteIdRapport : null);
            setFlash('success', 'Rapport enregistré' . ($form['soumettre'] ? ' et soumis' : ' en brouillon') . '.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            supprimerDocumentRapport($document['document_path']);
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nouveau rapport';
$activeNav = 'rapports';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1>Nouveau rapport</h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <?php if (!$visitesList): ?>
            <div class="alert alert-error">Aucune visite ne vous est actuellement assignée.</div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
            <div class="form-grid">
              <div class="field full">
                <label for="visite_id">Visite concernée</label>
                <select id="visite_id" name="visite_id" required>
                  <option value="">-- Sélectionner une visite --</option>
                  <?php foreach ($visitesList as $v): ?>
                  <option value="<?=$v['id']?>" <?= $form['visite_id'] == $v['id'] ? 'selected' : '' ?>>
                    <?=htmlspecialchars($v['nom_entreprise'] . ' — ' . $v['nom_site'] . ' (' . date('d/m/Y', strtotime($v['date_prevue'])) . ')')?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field full">
                <label for="titre">Titre du rapport</label>
                <input id="titre" name="titre" type="text" required value="<?=htmlspecialchars($form['titre'])?>">
              </div>
              <div class="field full">
                <label for="contenu">Contenu</label>
                <textarea id="contenu" name="contenu" style="min-height:200px;"><?=htmlspecialchars($form['contenu'])?></textarea>
              </div>
              <div class="field full">
                <label for="document">Joindre un fichier (PDF ou Word — optionnel)</label>
                <input id="document" name="document" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <p style="margin:6px 0 0;font-size:.82rem;color:var(--text-muted);">Formats acceptés : .pdf, .doc, .docx — taille maximale 10 Mo.</p>
              </div>
              <div class="field full">
                <label style="display:flex;align-items:center;gap:8px;">
                  <input type="checkbox" name="soumettre" style="width:auto;">
                  Soumettre immédiatement pour validation (sinon enregistré en brouillon)
                </label>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Enregistrer le rapport</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>