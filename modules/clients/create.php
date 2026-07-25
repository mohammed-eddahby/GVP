<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

$errors = [];
$form = ['nom_entreprise' => '', 'contact_nom' => '', 'email' => '', 'telephone' => '', 'adresse' => '', 'ville' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) $errors[] = 'Session expirée, veuillez réessayer.';
    foreach (array_keys($form) as $k) $form[$k] = trim($_POST[$k] ?? '');

    if ($form['nom_entreprise'] === '') $errors[] = 'Le nom de l\'entreprise est obligatoire.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO clients (nom_entreprise, contact_nom, email, telephone, adresse, ville, actif)
                 VALUES (:e, :c, :em, :t, :a, :v, 1)'
            );
            $stmt->execute([
                ':e' => $form['nom_entreprise'], ':c' => $form['contact_nom'] ?: null, ':em' => $form['email'] ?: null,
                ':t' => $form['telephone'] ?: null, ':a' => $form['adresse'] ?: null, ':v' => $form['ville'] ?: null,
            ]);
            logActivity($pdo, (int)$user['id'], 'creation_client', 'Création du client ' . $form['nom_entreprise'], (int)$pdo->lastInsertId(), null, 'client', (int)$pdo->lastInsertId());
            setFlash('success', 'Client créé avec succès.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nouveau client';
$activeNav = 'clients';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1>Nouveau client</h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?><div class="alert alert-error"><?=htmlspecialchars($err)?></div><?php endforeach; ?>
          <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field full">
                <label for="nom_entreprise">Nom de l'entreprise</label>
                <input id="nom_entreprise" name="nom_entreprise" type="text" required value="<?=htmlspecialchars($form['nom_entreprise'])?>">
              </div>
              <div class="field">
                <label for="contact_nom">Nom du contact</label>
                <input id="contact_nom" name="contact_nom" type="text" value="<?=htmlspecialchars($form['contact_nom'])?>">
              </div>
              <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?=htmlspecialchars($form['email'])?>">
              </div>
              <div class="field">
                <label for="telephone">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" value="<?=htmlspecialchars($form['telephone'])?>">
              </div>
              <div class="field">
                <label for="ville">Ville</label>
                <input id="ville" name="ville" type="text" value="<?=htmlspecialchars($form['ville'])?>">
              </div>
              <div class="field full">
                <label for="adresse">Adresse</label>
                <input id="adresse" name="adresse" type="text" value="<?=htmlspecialchars($form['adresse'])?>">
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Créer le client</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>