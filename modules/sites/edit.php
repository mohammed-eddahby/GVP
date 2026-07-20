<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);
$clientsList = $pdo->query('SELECT id, nom_entreprise FROM clients ORDER BY nom_entreprise')->fetchAll();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM sites WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();
if (!$target) {
    setFlash('error', 'Site introuvable.');
    header('Location: index.php');
    exit;
}

$errors = [];
$form = [
    'client_id' => $target['client_id'], 'nom_site' => $target['nom_site'], 'adresse' => $target['adresse'],
    'ville' => $target['ville'], 'latitude' => $target['latitude'], 'longitude' => $target['longitude'],
    'actif' => (int)$target['actif'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    foreach (['client_id','nom_site','adresse','ville','latitude','longitude'] as $k) $form[$k] = trim($_POST[$k] ?? '');
    $form['actif'] = isset($_POST['actif']) ? 1 : 0;

    if ($form['client_id'] === '' || !ctype_digit($form['client_id'])) $errors[] = 'Veuillez sélectionner un client.';
    if ($form['nom_site'] === '') $errors[] = 'Le nom du site est obligatoire.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE sites SET client_id=:cid, nom_site=:n, adresse=:a, ville=:v, latitude=:lat, longitude=:lng, actif=:actif WHERE id=:id'
            );
            $stmt->execute([
                ':cid' => (int)$form['client_id'], ':n' => $form['nom_site'], ':a' => $form['adresse'] ?: null,
                ':v' => $form['ville'] ?: null, ':lat' => $form['latitude'] !== '' ? $form['latitude'] : null,
                ':lng' => $form['longitude'] !== '' ? $form['longitude'] : null, ':actif' => $form['actif'], ':id' => $id,
            ]);
            logActivity($pdo, (int)$user['id'], 'modification_site', 'Modification du site ' . $form['nom_site'], (int)$form['client_id'], $id);
            if ((int)$target['actif'] !== (int)$form['actif']) {
                logActivity(
                    $pdo,
                    (int)$user['id'],
                    'changement_statut_site',
                    'Statut du site ' . $form['nom_site'] . ' changé en ' . ($form['actif'] ? 'Actif' : 'Inactif'),
                    (int)$form['client_id'],
                    $id
                );
            }
            setFlash('success', 'Site mis à jour.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Modifier site';
$activeNav = 'sites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1>Modifier : <?=htmlspecialchars($target['nom_site'])?></h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field full">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
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
                <input id="ville" name="ville" type="text" value="<?=htmlspecialchars((string)$form['ville'])?>">
              </div>
              <div class="field full">
                <label for="adresse">Adresse</label>
                <input id="adresse" name="adresse" type="text" value="<?=htmlspecialchars((string)$form['adresse'])?>">
              </div>
              <div class="field">
                <label for="latitude">Latitude</label>
                <input id="latitude" name="latitude" type="text" value="<?=htmlspecialchars((string)$form['latitude'])?>">
              </div>
              <div class="field">
                <label for="longitude">Longitude</label>
                <input id="longitude" name="longitude" type="text" value="<?=htmlspecialchars((string)$form['longitude'])?>">
              </div>
              <div class="field">
                <label for="actif">Statut</label>
                <label style="display:flex;align-items:center;gap:8px;">
                  <input id="actif" name="actif" type="checkbox" style="width:auto;" <?= $form['actif'] ? 'checked' : '' ?>>
                  Site actif
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