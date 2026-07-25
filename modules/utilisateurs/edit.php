<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['administrateur']);
$pdo = getPDO();
$user = currentUser($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = :id');
$stmt->execute([':id' => $id]);
$target = $stmt->fetch();
if (!$target) {
    setFlash('error', 'Utilisateur introuvable.');
    header('Location: index.php');
    exit;
}

$errors = [];
$form = [
    'nom' => $target['nom'], 'prenom' => $target['prenom'], 'email' => $target['email'],
    'telephone' => $target['telephone'], 'ville' => $target['ville'], 'role' => $target['role'],
    'actif' => (int)$target['actif'],
    'can_be_assigned_to_visits' => (int)$target['can_be_assigned_to_visits'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $errors[] = 'Session expirée, veuillez réessayer.';
    }
    $form['nom'] = trim($_POST['nom'] ?? '');
    $form['prenom'] = trim($_POST['prenom'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['telephone'] = trim($_POST['telephone'] ?? '');
    $form['ville'] = trim($_POST['ville'] ?? '');
    $form['role'] = $_POST['role'] ?? 'technicien';
    $form['actif'] = isset($_POST['actif']) ? 1 : 0;
    $form['can_be_assigned_to_visits'] = isset($_POST['can_be_assigned_to_visits']) ? 1 : 0;
    $newPassword = (string)($_POST['password'] ?? '');

    if ($form['nom'] === '' || $form['prenom'] === '') {
        $errors[] = 'Le nom et le prénom sont obligatoires.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = :email AND id != :id');
        $check->execute([':email' => $form['email'], ':id' => $id]);
        if ($check->fetch()) {
            $errors[] = 'Cet email est déjà utilisé par un autre compte.';
        }
    }

    if (!$errors) {
        try {
            if ($newPassword !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE utilisateurs SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel,
                     ville=:ville, role=:role, actif=:actif, can_be_assigned_to_visits=:cba, mot_de_passe=:pass WHERE id=:id'
                );
                $stmt->execute([
                    ':nom' => $form['nom'], ':prenom' => $form['prenom'], ':email' => $form['email'],
                    ':tel' => $form['telephone'] ?: null, ':ville' => $form['ville'] ?: null,
                    ':role' => $form['role'], ':actif' => $form['actif'],
                    ':cba' => $form['can_be_assigned_to_visits'],
                    ':pass' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE utilisateurs SET nom=:nom, prenom=:prenom, email=:email, telephone=:tel,
                     ville=:ville, role=:role, actif=:actif, can_be_assigned_to_visits=:cba WHERE id=:id'
                );
                $stmt->execute([
                    ':nom' => $form['nom'], ':prenom' => $form['prenom'], ':email' => $form['email'],
                    ':tel' => $form['telephone'] ?: null, ':ville' => $form['ville'] ?: null,
                    ':role' => $form['role'], ':actif' => $form['actif'],
                    ':cba' => $form['can_be_assigned_to_visits'], ':id' => $id,
                ]);
            }
            logActivity($pdo, (int)$user['id'], 'modification_utilisateur', 'Modification de ' . $form['prenom'] . ' ' . $form['nom'], null, null, 'utilisateur', $id);
            setFlash('success', 'Utilisateur mis à jour.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Modifier utilisateur';
$activeNav = 'utilisateurs';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div>
            <p class="eyebrow">Administration</p>
            <h1>Modifier : <?=htmlspecialchars($target['prenom'] . ' ' . $target['nom'])?></h1>
          </div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <?php foreach ($errors as $err): ?>
            <div class="alert alert-error" role="alert"><?=htmlspecialchars($err)?></div>
          <?php endforeach; ?>

          <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="form-grid">
              <div class="field">
                <label for="prenom">Prénom</label>
                <input id="prenom" name="prenom" type="text" required value="<?=htmlspecialchars($form['prenom'])?>">
              </div>
              <div class="field">
                <label for="nom">Nom</label>
                <input id="nom" name="nom" type="text" required value="<?=htmlspecialchars($form['nom'])?>">
              </div>
              <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="<?=htmlspecialchars($form['email'])?>">
              </div>
              <div class="field">
                <label for="password">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" minlength="8">
                <small class="hint">Laisser vide pour ne pas changer.</small>
              </div>
              <div class="field">
                <label for="telephone">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" value="<?=htmlspecialchars((string)$form['telephone'])?>">
              </div>
              <div class="field">
                <label for="ville">Ville</label>
                <input id="ville" name="ville" type="text" value="<?=htmlspecialchars((string)$form['ville'])?>">
              </div>
              <div class="field">
                <label for="role">Rôle</label>
                <select id="role" name="role">
                  <option value="technicien" <?= $form['role']==='technicien'?'selected':'' ?>>Technicien</option>
                  <option value="superviseur" <?= $form['role']==='superviseur'?'selected':'' ?>>Superviseur</option>
                  <option value="administrateur" <?= $form['role']==='administrateur'?'selected':'' ?>>Administrateur</option>
                </select>
              </div>
              <div class="field">
                <label for="actif">Statut</label>
                <label style="display:flex;align-items:center;gap:8px;">
                  <input id="actif" name="actif" type="checkbox" style="width:auto;" <?= $form['actif'] ? 'checked' : '' ?>>
                  Compte actif
                </label>
              </div>
              <div class="field">
                <label for="can_be_assigned_to_visits">Affectation aux visites</label>
                <label style="display:flex;align-items:center;gap:8px;">
                  <input id="can_be_assigned_to_visits" name="can_be_assigned_to_visits" type="checkbox" style="width:auto;" <?= $form['can_be_assigned_to_visits'] ? 'checked' : '' ?>>
                  Peut être affecté aux visites préventives
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
