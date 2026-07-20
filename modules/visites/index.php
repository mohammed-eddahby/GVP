<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$pdo = getPDO();
$user = currentUser($pdo);
$role = $user['role'];

$search = trim($_GET['q'] ?? '');
$statutFilter = $_GET['statut'] ?? '';

$sql = "SELECT v.*, s.nom_site, c.nom_entreprise, u.nom AS tech_nom, u.prenom AS tech_prenom
        FROM visites v
        JOIN sites s ON s.id = v.site_id
        JOIN clients c ON c.id = s.client_id
        LEFT JOIN utilisateurs u ON u.id = v.technicien_id
        WHERE 1=1";
$params = [];

if ($role === 'technicien') {
    $sql .= " AND v.technicien_id = :uid";
    $params[':uid'] = $user['id'];
}
if ($search !== '') {
    $sql .= " AND (s.nom_site LIKE :q OR c.nom_entreprise LIKE :q OR v.type_visite LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($statutFilter !== '' && in_array($statutFilter, ['planifiee','en_cours','realisee','annulee'], true)) {
    $sql .= " AND v.statut = :statut";
    $params[':statut'] = $statutFilter;
}
$sql .= " ORDER BY v.date_prevue DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visites = $stmt->fetchAll();

$statutLabels = [
    'planifiee' => ['Planifiée', 'info'],
    'en_cours'  => ['En cours', 'warning'],
    'realisee'  => ['Réalisée', 'success'],
    'annulee'   => ['Annulée', 'danger'],
];

$pageTitle = 'Visites';
$activeNav = 'visites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1>Visites préventives</h1></div>
          <?php if (can('visites.gerer')): ?>
          <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Nouvelle visite</a>
          <?php endif; ?>
        </div>

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Liste</p><h3><?= count($visites) ?> visite(s)</h3></div>
            <form class="panel-actions" method="get">
              <label class="search-inline" aria-label="Rechercher une visite">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" placeholder="Rechercher..." value="<?=htmlspecialchars($search)?>">
              </label>
              <select name="statut" onchange="this.form.submit()">
                <option value="">Tous statuts</option>
                <?php foreach ($statutLabels as $key => [$label, ]): ?>
                <option value="<?=$key?>" <?= $statutFilter === $key ? 'selected' : '' ?>><?=$label?></option>
                <?php endforeach; ?>
              </select>
              
            </form>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>Site / Client</th><th>Type</th><th>Technicien</th><th>Date prévue</th><th>Statut</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php if (!$visites): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-calendar-xmark"></i>Aucune visite trouvée.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($visites as $v): [$label, $badge] = $statutLabels[$v['statut']] ?? [$v['statut'], 'muted']; ?>
                <tr>
                  <td><strong><?=htmlspecialchars($v['nom_site'])?></strong><br><small><?=htmlspecialchars($v['nom_entreprise'])?></small></td>
                  <td><?=htmlspecialchars($v['type_visite'])?></td>
                  <td><?= $v['tech_nom'] ? htmlspecialchars($v['tech_prenom'] . ' ' . $v['tech_nom']) : '<span class="badge muted">Non assigné</span>' ?></td>
                  <td><?=htmlspecialchars(date('d/m/Y', strtotime($v['date_prevue'])))?></td>
                  <td><span class="badge <?=$badge?>"><?=$label?></span></td>
                  <td class="table-actions">
                    <a class="btn btn-secondary small" href="view.php?id=<?=$v['id']?>"><i class="fa-solid fa-eye"></i></a>
                    <?php if (can('visites.gerer') || ($role === 'technicien' && (int)$v['technicien_id'] === (int)$user['id'])): ?>
                    <a class="btn btn-secondary small" href="edit.php?id=<?=$v['id']?>"><i class="fa-solid fa-pen"></i></a>
                    <?php endif; ?>
                    <?php if (can('visites.gerer')): ?>
                    <form method="post" action="delete.php" onsubmit="return confirm('Supprimer cette visite ?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="id" value="<?=$v['id']?>">
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
