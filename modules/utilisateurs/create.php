<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['administrateur']);
$pdo = getPDO();
$user = currentUser($pdo);

$errors = [];
$form = ['nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '', 'ville' => '', 'role' => 'technicien', 'can_be_assigned_to_visits' => 1];

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
    $form['can_be_assigned_to_visits'] = isset($_POST['can_be_assigned_to_visits']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');

    if ($form['nom'] === '' || $form['prenom'] === '') {
        $errors[] = 'Le nom et le prénom sont obligatoires.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if (!in_array($form['role'], ['administrateur', 'superviseur', 'technicien'], true)) {
        $errors[] = 'Rôle invalide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }

    if (!$errors) {
        try {
            $check = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = :email');
            $check->execute([':email' => $form['email']]);
            if ($check->fetch()) {
                $errors[] = 'Cet email est déjà utilisé.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la vérification de l\'email.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, ville, role, actif, can_be_assigned_to_visits)
                 VALUES (:nom, :prenom, :email, :pass, :tel, :ville, :role, 1, :cba)'
            );
            $stmt->execute([
                ':nom' => $form['nom'],
                ':prenom' => $form['prenom'],
                ':email' => $form['email'],
                ':pass' => password_hash($password, PASSWORD_DEFAULT),
                ':tel' => $form['telephone'] ?: null,
                ':ville' => $form['ville'] ?: null,
                ':role' => $form['role'],
                ':cba' => $form['can_be_assigned_to_visits'],
            ]);
            logActivity($pdo, (int)$user['id'], 'creation_utilisateur', 'Création de ' . $form['prenom'] . ' ' . $form['nom'], null, null, 'utilisateur', (int)$pdo->lastInsertId());
            setFlash('success', 'Utilisateur créé avec succès.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nouvel utilisateur';
$activeNav = 'utilisateurs';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div>
            <p class="eyebrow">Administration</p>
            <h1>Nouvel utilisateur</h1>
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
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" required minlength="8">
                <small class="hint">8 caractères minimum.</small>
              </div>
              <div class="field">
                <label for="telephone">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" value="<?=htmlspecialchars($form['telephone'])?>">
              </div>
              <div class="field">
                <label for="ville">Ville</label>
                <input id="ville" name="ville" type="text" value="<?=htmlspecialchars($form['ville'])?>">
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
                <label for="can_be_assigned_to_visits">Affectation aux visites</label>
                <label style="display:flex;align-items:center;gap:8px;">
                  <input id="can_be_assigned_to_visits" name="can_be_assigned_to_visits" type="checkbox" style="width:auto;" <?= $form['can_be_assigned_to_visits'] ? 'checked' : '' ?>>
                  Peut être affecté aux visites préventives
                </label>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Créer l'utilisateur</button>
              <a class="btn btn-secondary" href="index.php">Annuler</a>
            </div>
          </form>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
