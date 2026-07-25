<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);
$clientsList = $pdo->query('SELECT id, nom_entreprise FROM clients WHERE actif = 1 ORDER BY nom_entreprise')->fetchAll();

$errors = [];
$form = ['client_id' => '', 'nom_site' => '', 'adresse' => '', 'ville' => '', 'latitude' => '', 'longitude' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    foreach (array_keys($form) as $k) $form[$k] = trim($_POST[$k] ?? '');

    if ($form['client_id'] === '' || !ctype_digit($form['client_id'])) $errors[] = 'Veuillez sélectionner un client.';
    if ($form['nom_site'] === '') $errors[] = 'Le nom du site est obligatoire.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO sites (client_id, nom_site, adresse, ville, latitude, longitude, actif)
                 VALUES (:cid, :n, :a, :v, :lat, :lng, 1)'
            );
            $stmt->execute([
                ':cid' => (int)$form['client_id'], ':n' => $form['nom_site'],
                ':a' => $form['adresse'] ?: null, ':v' => $form['ville'] ?: null,
                ':lat' => $form['latitude'] !== '' ? $form['latitude'] : null,
                ':lng' => $form['longitude'] !== '' ? $form['longitude'] : null,
            ]);
            logActivity($pdo, (int)$user['id'], 'creation_site', 'Création du site ' . $form['nom_site'], (int)$form['client_id'], (int)$pdo->lastInsertId(), 'site', (int)$pdo->lastInsertId());
            setFlash('success', 'Site créé avec succès.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nouveau site';
$activeNav = 'sites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1>Nouveau site</h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <?php if (!$clientsList): ?>
            <div class="alert alert-error">Vous devez d'abord créer un client avant d'ajouter un site.</div>
          <?php endif; ?>
          <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field full">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
                  <option value="">-- Sélectionner un client --</option>
                  <?php foreach ($clientsList as $c): ?>
                  <option value="<?=$c['id']?>" <?= $form['client_id'] == $c['id'] ? 'selected' : '' ?>><?=htmlspecialchars($c['nom_entreprise'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field full">
                <label for="nom_site">Nom du site</label>
                <input id="nom_site" name="nom_site" type="text" required value="<?=htmlspecialchars($form['nom_site'])?>">
              </div>
              <div class="field">
                <label for="ville">Ville</label>
                <input id="ville" name="ville" type="text" value="<?=htmlspecialchars($form['ville'])?>">
              </div>
              <div class="field full">
                <label for="adresse">Adresse</label>
                <input id="adresse" name="adresse" type="text" value="<?=htmlspecialchars($form['adresse'])?>">
              </div>
              <div class="field">
                <label for="latitude">Latitude (optionnel)</label>
                <input id="latitude" name="latitude" type="text" value="<?=htmlspecialchars($form['latitude'])?>">
              </div>
              <div class="field">
                <label for="longitude">Longitude (optionnel)</label>
                <input id="longitude" name="longitude" type="text" value="<?=htmlspecialchars($form['longitude'])?>">
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Créer le site</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>