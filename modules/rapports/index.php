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

$sql = "SELECT r.*, s.nom_site, c.nom_entreprise, u.nom AS auteur_nom, u.prenom AS auteur_prenom
        FROM rapports r
        JOIN visites v ON v.id = r.visite_id
        JOIN sites s ON s.id = v.site_id
        JOIN clients c ON c.id = s.client_id
        LEFT JOIN utilisateurs u ON u.id = r.redige_par
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (r.titre LIKE :q OR s.nom_site LIKE :q OR c.nom_entreprise LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($statutFilter !== '' && in_array($statutFilter, ['brouillon','soumis','valide','rejete'], true)) {
    $sql .= " AND r.statut = :statut";
    $params[':statut'] = $statutFilter;
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rapports = $stmt->fetchAll();

$statutLabels = [
    'brouillon' => ['Brouillon', 'muted'], 'soumis' => ['Soumis', 'warning'],
    'valide' => ['Validé', 'success'], 'rejete' => ['Rejeté', 'danger'],
];

$pageTitle = 'Rapports';
$activeNav = 'rapports';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1>Rapports</h1></div>
        </div>

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Liste</p><h3><?= count($rapports) ?> rapport(s)</h3></div>
            <form class="panel-actions" method="get">
              <label class="search-inline" aria-label="Rechercher un rapport">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" placeholder="Rechercher..." value="<?=htmlspecialchars($search)?>">
              </label>
              <select name="statut" onchange="this.form.submit()">
                <option value="">Tous statuts</option>
                <?php foreach ($statutLabels as $key => [$label, ]): ?>
                <option value="<?=$key?>" <?= $statutFilter === $key ? 'selected' : '' ?>><?=$label?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-secondary small" type="submit">Filtrer</button>
            </form>
          </div>

          <div class="table-wrap">
            <table>
              <thead><tr><th>Titre</th><th>Site / Client</th><th>Auteur</th><th>Document</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (!$rapports): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-file-circle-xmark"></i>Aucun rapport trouvé.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($rapports as $r): [$label, $badge] = $statutLabels[$r['statut']] ?? [$r['statut'], 'muted']; ?>
                <tr>
                  <td><?=htmlspecialchars($r['titre'])?></td>
                  <td><?=htmlspecialchars($r['nom_entreprise'])?> — <?=htmlspecialchars($r['nom_site'])?></td>
                  <td><?=htmlspecialchars(trim(($r['auteur_prenom'] ?? '') . ' ' . ($r['auteur_nom'] ?? '')) ?: '—')?></td>
                  <td>
                    <?php if (!empty($r['document_path'])): ?>
                    <a class="btn btn-secondary small" href="download.php?id=<?=$r['id']?>&mode=view" target="_blank" rel="noopener" title="Ouvrir le document joint">
                      <i class="fa-solid fa-paperclip"></i> <?=strtoupper((string)$r['document_type'])?>
                    </a>
                    <?php else: ?>
                    <span style="color:var(--text-muted);">—</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?=$badge?>"><?=$label?></span></td>
                  <td><?=htmlspecialchars(date('d/m/Y', strtotime($r['created_at'])))?></td>
                  <td class="table-actions">
                    <a class="btn btn-secondary small" href="view.php?id=<?=$r['id']?>"><i class="fa-solid fa-eye"></i></a>
                    <?php if ($role === 'technicien' && (int)$r['redige_par'] === (int)$user['id'] && in_array($r['statut'], ['brouillon','rejete'], true)): ?>
                    <a class="btn btn-secondary small" href="edit.php?id=<?=$r['id']?>"><i class="fa-solid fa-pen"></i></a>
                    <?php endif; ?>
                    <?php if (can('rapports.valider') && $r['statut'] === 'soumis'): ?>
                    <a class="btn btn-primary small" href="validate.php?id=<?=$r['id']?>&action=valider" onclick="return confirm('Valider ce rapport ?');">Valider</a>
                    <a class="btn btn-danger small" href="validate.php?id=<?=$r['id']?>&action=rejeter" onclick="return confirm('Rejeter ce rapport ?');">Rejeter</a>
                    <?php endif; ?>
                    <?php if (($role === 'technicien' && (int)$r['redige_par'] === (int)$user['id'] && $r['statut'] === 'brouillon') || $role === 'administrateur'): ?>
                    <form method="post" action="delete.php" onsubmit="return confirm('Supprimer ce rapport ?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="id" value="<?=$r['id']?>">
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
