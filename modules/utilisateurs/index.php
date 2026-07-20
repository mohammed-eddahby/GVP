<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['administrateur']); // gestion des utilisateurs : administrateur uniquement
$pdo = getPDO();
$user = currentUser($pdo);

$search = trim($_GET['q'] ?? '');
$statutFilter = $_GET['statut'] ?? '';

$sql = "SELECT * FROM utilisateurs WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (nom LIKE :q OR prenom LIKE :q OR email LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($statutFilter === 'actif' || $statutFilter === 'inactif') {
    $sql .= " AND actif = :actif";
    $params[':actif'] = $statutFilter === 'actif' ? 1 : 0;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

$pageTitle = 'Utilisateurs';
$activeNav = 'utilisateurs';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div>
            <p class="eyebrow">Administration</p>
            <h1>Utilisateurs</h1>
          </div>
          <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Nouvel utilisateur</a>
        </div>

        <section class="panel table-panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Liste</p>
              <h3><?= count($utilisateurs) ?> utilisateur(s)</h3>
            </div>
            <form class="panel-actions" method="get">
              <label class="search-inline" aria-label="Rechercher un utilisateur">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" placeholder="Rechercher..." value="<?=htmlspecialchars($search)?>">
              </label>
              <select name="statut" onchange="this.form.submit()">
                <option value="">Tous</option>
                <option value="actif" <?= $statutFilter === 'actif' ? 'selected' : '' ?>>Actif</option>
                <option value="inactif" <?= $statutFilter === 'inactif' ? 'selected' : '' ?>>Inactif</option>
              </select>
            </form>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Nom complet</th>
                  <th>Email</th>
                  <th>Ville</th>
                  <th>Rôle</th>
                  <th>Statut</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$utilisateurs): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-user-slash"></i>Aucun utilisateur trouvé.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($utilisateurs as $u): ?>
                <tr>
                  <td><?=htmlspecialchars($u['prenom'] . ' ' . $u['nom'])?></td>
                  <td><?=htmlspecialchars($u['email'])?></td>
                  <td><?=htmlspecialchars($u['ville'] ?? '—')?></td>
                  <td><span class="badge info"><?=htmlspecialchars(roleLabel($u['role']))?></span></td>
                  <td><span class="badge <?= $u['actif'] ? 'success' : 'muted' ?>"><?= $u['actif'] ? 'Actif' : 'Inactif' ?></span></td>
                  <td class="table-actions">
                    <a class="btn btn-secondary small" href="edit.php?id=<?=$u['id']?>"><i class="fa-solid fa-pen"></i></a>
                    <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                    <form method="post" action="delete.php" onsubmit="return confirm('Supprimer cet utilisateur ?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="id" value="<?=$u['id']?>">
                      <button class="btn btn-danger small" type="submit"><i class="fa-solid fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
