<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/journal_helpers.php';

requireRole(['superviseur']); // même accès que le reste du module Clients
$pdo = getPDO();
$user = currentUser($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id');
$stmt->execute([':id' => $id]);
$client = $stmt->fetch();

if (!$client) {
    setFlash('error', 'Client introuvable.');
    header('Location: index.php');
    exit;
}

// Uniquement les sites appartenant à CE client (filtré par son id).
$stmtSites = $pdo->prepare(
    "SELECT s.*, (SELECT COUNT(*) FROM visites v WHERE v.site_id = s.id) AS nb_visites
     FROM sites s
     WHERE s.client_id = :id
     ORDER BY s.created_at DESC"
);
$stmtSites->execute([':id' => $id]);
$sites = $stmtSites->fetchAll();

$aUnContrat = !empty($client['contrat_maintenance_path']);

// Journal d'activité : uniquement les entrées liées à CE client (filtré par
// entite_type='client' + entite_id), via le système de journalisation
// existant (journal_activite / logActivity()).
$stmtJournal = $pdo->prepare(
    "SELECT j.action, j.description, j.created_at, j.entite_type, j.entite_id, u.nom, u.prenom
     FROM journal_activite j
     LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
     WHERE j.entite_type = 'client' AND j.entite_id = :id
     ORDER BY j.created_at DESC"
);
$stmtJournal->execute([':id' => $id]);
$activites = $stmtJournal->fetchAll();

$pageTitle = 'Détail client';
$activeNav = 'clients';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Gestion</p><h1><?=htmlspecialchars($client['nom_entreprise'])?></h1></div>
          <div class="hero-actions">
            <?php if (can('clients.gerer')): ?>
            <a class="btn btn-primary" href="edit.php?id=<?=$client['id']?>"><i class="fa-solid fa-pen"></i> Modifier</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
          </div>
        </div>

        <section class="panel">
          <div class="form-grid">
            <div class="field"><label>Nom de l'entreprise</label><p><?=htmlspecialchars($client['nom_entreprise'])?></p></div>
            <div class="field"><label>Contact</label><p><?=htmlspecialchars($client['contact_nom'] ?? '—')?></p></div>
            <div class="field"><label>Téléphone</label><p><?=htmlspecialchars($client['telephone'] ?? '—')?></p></div>
            <div class="field"><label>Email</label><p><?=htmlspecialchars($client['email'] ?? '—')?></p></div>
            <div class="field"><label>Ville</label><p><?=htmlspecialchars($client['ville'] ?? '—')?></p></div>
            <div class="field"><label>Statut</label><p><span class="badge <?= $client['actif'] ? 'success' : 'muted' ?>"><?= $client['actif'] ? 'Actif' : 'Inactif' ?></span></p></div>
            <div class="field full"><label>Adresse</label><p><?=htmlspecialchars($client['adresse'] ?? '—')?></p></div>

            <div class="field full">
              <label>Contrat de maintenance</label>
              <?php if ($aUnContrat): ?>
              <p>
                <span class="badge success">Contrat disponible</span>
                &nbsp;—&nbsp;
                <a class="btn btn-secondary small" href="contrat_download.php?id=<?=$client['id']?>&mode=view" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> Voir le contrat</a>
                <a class="btn btn-secondary small" href="contrat_download.php?id=<?=$client['id']?>&mode=download"><i class="fa-solid fa-download"></i> Télécharger le contrat</a>
              </p>
              <?php else: ?>
              <p><span class="badge muted">Aucun contrat</span></p>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Sites</p><h3><?= count($sites) ?> site(s) associé(s)</h3></div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Nom du site</th>
                  <th>Ville</th>
                  <th>Adresse</th>
                  <th>Statut</th>
                  <th>Visites</th>
                  <th>Date de création</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$sites): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-map-location-dot"></i>Aucun site associé à ce client.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($sites as $s): ?>
                <tr>
                  <td><strong><?=htmlspecialchars($s['nom_site'])?></strong></td>
                  <td><?=htmlspecialchars($s['ville'] ?? '—')?></td>
                  <td><?=htmlspecialchars($s['adresse'] ?? '—')?></td>
                  <td><span class="badge <?= $s['actif'] ? 'success' : 'muted' ?>"><?= $s['actif'] ? 'Actif' : 'Inactif' ?></span></td>
                  <td><span class="badge info"><?=$s['nb_visites']?></span></td>
                  <td><?= $s['created_at'] ? htmlspecialchars(date('d/m/Y', strtotime($s['created_at']))) : '—' ?></td>
                  <td class="table-actions">
                    <?php if (can('sites.gerer')): ?>
                    <a class="btn btn-secondary small" href="../sites/edit.php?id=<?=$s['id']?>"><i class="fa-solid fa-pen"></i></a>
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

          <?php renderJournalTable($activites, "Aucune activité enregistrée pour ce client."); ?>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>