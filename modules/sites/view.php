<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['superviseur']); // même accès que le reste du module Sites
$pdo = getPDO();
$user = currentUser($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT s.*, c.nom_entreprise
     FROM sites s
     JOIN clients c ON c.id = s.client_id
     WHERE s.id = :id'
);
$stmt->execute([':id' => $id]);
$site = $stmt->fetch();

if (!$site) {
    setFlash('error', 'Site introuvable.');
    header('Location: index.php');
    exit;
}

// Uniquement les visites appartenant à CE site (filtrées par son id).
$stmtVisites = $pdo->prepare(
    "SELECT v.*, u.nom AS tech_nom, u.prenom AS tech_prenom,
            (SELECT r.id FROM rapports r WHERE r.visite_id = v.id ORDER BY r.created_at DESC LIMIT 1) AS dernier_rapport_id
     FROM visites v
     LEFT JOIN utilisateurs u ON u.id = v.technicien_id
     WHERE v.site_id = :id
     ORDER BY v.date_prevue DESC"
);
$stmtVisites->execute([':id' => $id]);
$visites = $stmtVisites->fetchAll();

// Journal d'activité : uniquement les entrées liées à CE site (filtré par site_id),
// via le système de journalisation existant (journal_activite / logActivity()).
$stmtJournal = $pdo->prepare(
    "SELECT j.action, j.description, j.created_at, u.nom, u.prenom
     FROM journal_activite j
     LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
     WHERE j.site_id = :id
     ORDER BY j.created_at DESC"
);
$stmtJournal->execute([':id' => $id]);
$activites = $stmtJournal->fetchAll();

$statutLabels = [
    'planifiee' => ['Planifiée', 'info'], 'en_cours' => ['En cours', 'warning'],
    'realisee'  => ['Réalisée', 'success'], 'annulee'  => ['Annulée', 'danger'],
];

$pageTitle = 'Détail site';
$activeNav = 'sites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1><?=htmlspecialchars($site['nom_site'])?></h1></div>
          <div class="hero-actions">
            <?php if (can('sites.gerer')): ?>
            <a class="btn btn-primary" href="edit.php?id=<?=$site['id']?>"><i class="fa-solid fa-pen"></i> Modifier</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
          </div>
        </div>

        <section class="panel">
          <div class="form-grid">
            <div class="field"><label>Nom du site</label><p><?=htmlspecialchars($site['nom_site'])?></p></div>
            <div class="field"><label>Client associé</label><p><a href="../clients/view.php?id=<?=$site['client_id']?>" style="color: orange; text-decoration: none;"><?=htmlspecialchars($site['nom_entreprise'])?></a></p></div>
            <div class="field"><label>Ville</label><p><?=htmlspecialchars($site['ville'] ?? '—')?></p></div>
            <div class="field"><label>Statut</label><p><span class="badge <?= $site['actif'] ? 'success' : 'muted' ?>"><?= $site['actif'] ? 'Actif' : 'Inactif' ?></span></p></div>
            <div class="field full"><label>Adresse</label><p><?=htmlspecialchars($site['adresse'] ?? '—')?></p></div>
            <div class="field"><label>Latitude</label><p><?=htmlspecialchars($site['latitude'] !== null ? (string)$site['latitude'] : '—')?></p></div>
            <div class="field"><label>Longitude</label><p><?=htmlspecialchars($site['longitude'] !== null ? (string)$site['longitude'] : '—')?></p></div>
            <div class="field"><label>Date de création</label><p><?= $site['created_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($site['created_at']))) : '—' ?></p></div>
            <div class="field"><label>Dernière mise à jour</label><p><?= $site['updated_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($site['updated_at']))) : '—' ?></p></div>
          </div>
        </section>

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Visites</p><h3><?= count($visites) ?> visite(s) associée(s)</h3></div>
            <?php if (can('visites.gerer')): ?>
            <a class="btn btn-secondary small" href="../visites/create.php">Nouvelle visite</a>
            <?php endif; ?>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date de visite</th>
                  <th>Type de visite</th>
                  <th>Technicien</th>
                  <th>Statut</th>
                  <th>Rapport associé</th>
                  <th>Date de création</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$visites): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-calendar-xmark"></i>Aucune visite associée à ce site.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($visites as $v): [$label, $badge] = $statutLabels[$v['statut']] ?? [$v['statut'], 'muted']; ?>
                <tr>
                  <td><?=htmlspecialchars(date('d/m/Y', strtotime($v['date_prevue'])))?></td>
                  <td><?=htmlspecialchars($v['type_visite'])?></td>
                  <td><?= $v['tech_nom'] ? htmlspecialchars($v['tech_prenom'] . ' ' . $v['tech_nom']) : '<span class="badge muted">Non assigné</span>' ?></td>
                  <td><span class="badge <?=$badge?>"><?=$label?></span></td>
                  <td>
                    <?php if ($v['dernier_rapport_id']): ?>
                    <a class="badge success" href="../rapports/view.php?id=<?=$v['dernier_rapport_id']?>" style="text-decoration:none;">Oui</a>
                    <?php else: ?>
                    <span class="badge muted">Non</span>
                    <?php endif; ?>
                  </td>
                  <td><?= $v['created_at'] ? htmlspecialchars(date('d/m/Y', strtotime($v['created_at']))) : '—' ?></td>
                  <td class="table-actions">
                    <a class="btn btn-secondary small" href="../visites/view.php?id=<?=$v['id']?>"><i class="fa-solid fa-eye"></i></a>
                    <?php if (can('visites.gerer')): ?>
                    <a class="btn btn-secondary small" href="../visites/edit.php?id=<?=$v['id']?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="../visites/delete.php" onsubmit="return confirm('Supprimer cette visite ?');" style="display:inline;">
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

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Journal</p><h3>Journal d'activité</h3></div>
          </div>

          <?php if (!$activites): ?>
          <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>Aucune activité enregistrée pour ce site.</div>
          <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date et heure</th>
                  <th>Utilisateur</th>
                  <th>Action effectuée</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activites as $a): ?>
                <tr>
                  <td><?=htmlspecialchars(date('d/m/Y H:i', strtotime($a['created_at'])))?></td>
                  <td><?=htmlspecialchars(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?: 'Système')?></td>
                  <td><span class="badge info"><?=htmlspecialchars($a['action'])?></span></td>
                  <td><?=htmlspecialchars($a['description'] ?? '')?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
