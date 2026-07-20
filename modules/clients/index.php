<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']);
$pdo = getPDO();
$user = currentUser($pdo);

$search = trim($_GET['q'] ?? '');
$statutFilter = $_GET['statut'] ?? 'actif';

$sql = "SELECT c.*, (SELECT COUNT(*) FROM sites s WHERE s.client_id = c.id) AS nb_sites
        FROM clients c
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.nom_entreprise LIKE :q OR c.contact_nom LIKE :q OR c.ville LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($statutFilter === 'actif' || $statutFilter === 'inactif') {
    $sql .= " AND c.actif = :actif";
    $params[':actif'] = $statutFilter === 'actif' ? 1 : 0;
}
$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
$activeNav = 'clients';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div>
            <p class="eyebrow">Gestion</p>
            <h1>Clients</h1>
          </div>
          <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Nouveau client</a>
        </div>

        <section class="panel table-panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Liste</p>
              <h3><?= count($clients) ?> client(s)</h3>
            </div>
            <form class="panel-actions" method="get">
              <label class="search-inline" aria-label="Rechercher un client">
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
                  <th>Entreprise</th>
                  <th>Contact</th>
                  <th>Ville</th>
                  <th>Sites</th>
                  <th>Statut</th>
                  <th>Contrat</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$clients): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-building-circle-xmark"></i>Aucun client trouvé.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($clients as $c): ?>
                <tr>
                  <td><strong><?=htmlspecialchars($c['nom_entreprise'])?></strong><br><small><?=htmlspecialchars($c['email'] ?? '')?></small></td>
                  <td><?=htmlspecialchars($c['contact_nom'] ?? '—')?><br><small><?=htmlspecialchars($c['telephone'] ?? '')?></small></td>
                  <td><?=htmlspecialchars($c['ville'] ?? '—')?></td>
                  <td><span class="badge info"><?=$c['nb_sites']?></span></td>
                  <td><span class="badge <?= $c['actif'] ? 'success' : 'muted' ?>"><?= $c['actif'] ? 'Actif' : 'Inactif' ?></span></td>
                  <td>
                    <?php if (!empty($c['contrat_maintenance_path'])): ?>
                    <span class="badge success" title="Contrat disponible">✅ Contrat</span>
                    <?php else: ?>
                    <span class="badge muted" title="Aucun contrat">❌ Aucun</span>
                    <?php endif; ?>
                  </td>
                  <td class="table-actions">
                    <a class="btn btn-secondary small" href="view.php?id=<?=$c['id']?>"><i class="fa-solid fa-eye"></i></a>
                    <a class="btn btn-secondary small" href="edit.php?id=<?=$c['id']?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="delete.php" onsubmit="return confirm('Supprimer ce client et tous ses sites/visites associés ?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="id" value="<?=$c['id']?>">
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
