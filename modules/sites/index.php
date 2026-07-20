<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

$search = trim($_GET['q'] ?? '');
$statutFilter = $_GET['statut'] ?? 'actif';

$sql = "SELECT s.*, c.nom_entreprise,
        (SELECT COUNT(*) FROM visites v WHERE v.site_id = s.id) AS nb_visites
        FROM sites s JOIN clients c ON c.id = s.client_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.nom_site LIKE :q OR s.ville LIKE :q OR c.nom_entreprise LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($statutFilter === 'actif' || $statutFilter === 'inactif') {
    $sql .= " AND s.actif = :actif";
    $params[':actif'] = $statutFilter === 'actif' ? 1 : 0;
}
$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

$pageTitle = 'Sites';
$activeNav = 'sites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1>Sites</h1></div>
          <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Nouveau site</a>
        </div>

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Liste</p><h3><?= count($sites) ?> site(s)</h3></div>
            <form class="panel-actions" method="get">
              <label class="search-inline" aria-label="Rechercher un site">
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
                <tr><th>Site</th><th>Client</th><th>Ville</th><th>Visites</th><th>Statut</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php if (!$sites): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-map-location-dot"></i>Aucun site trouvé.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($sites as $s): ?>
                <tr>
                  <td><strong><?=htmlspecialchars($s['nom_site'])?></strong><br><small><?=htmlspecialchars($s['adresse'] ?? '')?></small></td>
                  <td><?=htmlspecialchars($s['nom_entreprise'])?></td>
                  <td><?=htmlspecialchars($s['ville'] ?? '—')?></td>
                  <td><span class="badge info"><?=$s['nb_visites']?></span></td>
                  <td><span class="badge <?= $s['actif'] ? 'success' : 'muted' ?>"><?= $s['actif'] ? 'Actif' : 'Inactif' ?></span></td>
                  <td class="table-actions">
                    <a class="btn btn-secondary small" href="view.php?id=<?=$s['id']?>"><i class="fa-solid fa-eye"></i></a>
                    <a class="btn btn-secondary small" href="edit.php?id=<?=$s['id']?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="delete.php" onsubmit="return confirm('Supprimer ce site et ses visites associées ?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="id" value="<?=$s['id']?>">
                      <button class="btn btn-danger small" type="submit"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>