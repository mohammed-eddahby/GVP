<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

$sitesList = $pdo->query(
    "SELECT s.id, s.nom_site, c.nom_entreprise FROM sites s JOIN clients c ON c.id = s.client_id WHERE s.actif = 1 ORDER BY c.nom_entreprise, s.nom_site"
)->fetchAll();
$techList = $pdo->query("SELECT id, nom, prenom, role FROM utilisateurs WHERE actif = 1 AND can_be_assigned_to_visits = 1 ORDER BY prenom")->fetchAll();

$errors = [];
$form = ['site_id' => '', 'technicien_id' => '', 'type_visite' => 'Visite préventive', 'date_prevue' => '', 'statut' => 'planifiee', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    foreach (['site_id','technicien_id','type_visite','date_prevue','statut','notes'] as $k) $form[$k] = trim($_POST[$k] ?? '');

    if ($form['site_id'] === '' || !ctype_digit($form['site_id'])) $errors[] = 'Veuillez sélectionner un site.';
    if ($form['date_prevue'] === '') $errors[] = 'La date prévue est obligatoire.';
    if (!in_array($form['statut'], ['planifiee','en_cours','realisee','annulee'], true)) $errors[] = 'Statut invalide.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO visites (site_id, technicien_id, type_visite, date_prevue, statut, notes)
                 VALUES (:sid, :tid, :type, :date, :statut, :notes)'
            );
            $stmt->execute([
                ':sid' => (int)$form['site_id'],
                ':tid' => $form['technicien_id'] !== '' ? (int)$form['technicien_id'] : null,
                ':type' => $form['type_visite'] ?: 'Visite préventive',
                ':date' => $form['date_prevue'],
                ':statut' => $form['statut'],
                ':notes' => $form['notes'] ?: null,
            ]);
            logActivity($pdo, (int)$user['id'], 'creation_visite', 'Création d\'une visite le ' . $form['date_prevue'], null, (int)$form['site_id']);
            setFlash('success', 'Visite créée avec succès.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nouvelle visite';
$activeNav = 'visites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1>Nouvelle visite</h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field full">
                <label for="site_id">Site</label>
                <select id="site_id" name="site_id" required>
                  <option value="">-- Sélectionner un site --</option>
                  <?php foreach ($sitesList as $s): ?>
                  <option value="<?=$s['id']?>" <?= $form['site_id'] == $s['id'] ? 'selected' : '' ?>><?=htmlspecialchars($s['nom_entreprise'] . ' — ' . $s['nom_site'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="technicien_id">Technicien assigné</label>
                <select id="technicien_id" name="technicien_id">
                  <option value="">-- Non assigné --</option>
                  <?php foreach ($techList as $t): ?>
                  <option value="<?=$t['id']?>" <?= $form['technicien_id'] == $t['id'] ? 'selected' : '' ?>><?=htmlspecialchars($t['prenom'] . ' ' . $t['nom'] . ' (' . roleLabel($t['role']) . ')')?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="type_visite">Type de visite</label>
                <input id="type_visite" name="type_visite" type="text" value="<?=htmlspecialchars($form['type_visite'])?>">
              </div>
              <div class="field">
                <label for="date_prevue">Date prévue</label>
                <input id="date_prevue" name="date_prevue" type="date" required value="<?=htmlspecialchars($form['date_prevue'])?>">
              </div>
              <div class="field">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                  <option value="planifiee" <?= $form['statut']==='planifiee'?'selected':'' ?>>Planifiée</option>
                  <option value="en_cours" <?= $form['statut']==='en_cours'?'selected':'' ?>>En cours</option>
                  <option value="realisee" <?= $form['statut']==='realisee'?'selected':'' ?>>Réalisée</option>
                  <option value="annulee" <?= $form['statut']==='annulee'?'selected':'' ?>>Annulée</option>
                </select>
              </div>
              <div class="field full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes"><?=htmlspecialchars($form['notes'])?></textarea>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Créer la visite</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>