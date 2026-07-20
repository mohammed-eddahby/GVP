<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$pdo = getPDO();
$user = currentUser($pdo);
$role = $user['role'];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT v.*, s.nom_site, s.adresse AS site_adresse, c.nom_entreprise, u.nom AS tech_nom, u.prenom AS tech_prenom
     FROM visites v
     JOIN sites s ON s.id = v.site_id
     JOIN clients c ON c.id = s.client_id
     LEFT JOIN utilisateurs u ON u.id = v.technicien_id
     WHERE v.id = :id"
);
$stmt->execute([':id' => $id]);
$visite = $stmt->fetch();

if (!$visite) {
    setFlash('error', 'Visite introuvable.');
    header('Location: index.php');
    exit;
}
if ($role === 'technicien' && (int)$visite['technicien_id'] !== (int)$user['id']) {
    requireRole(['__forbidden__']);
}

$rapports = $pdo->prepare('SELECT r.*, u.nom, u.prenom FROM rapports r LEFT JOIN utilisateurs u ON u.id = r.redige_par WHERE r.visite_id = :id ORDER BY r.created_at DESC');
$rapports->execute([':id' => $id]);
$rapports = $rapports->fetchAll();

$statutLabels = [
    'planifiee' => ['Planifiée', 'info'], 'en_cours' => ['En cours', 'warning'],
    'realisee' => ['Réalisée', 'success'], 'annulee' => ['Annulée', 'danger'],
];
$rapportStatutLabels = [
    'brouillon' => ['Brouillon', 'muted'], 'soumis' => ['Soumis', 'warning'],
    'valide' => ['Validé', 'success'], 'rejete' => ['Rejeté', 'danger'],
];
[$label, $badge] = $statutLabels[$visite['statut']] ?? [$visite['statut'], 'muted'];

$pageTitle = 'Détail visite';
$activeNav = 'visites';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1><?=htmlspecialchars($visite['nom_site'])?></h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <div class="form-grid">
            <div class="field"><label>Client</label><p><?=htmlspecialchars($visite['nom_entreprise'])?></p></div>
            <div class="field"><label>Adresse du site</label><p><?=htmlspecialchars($visite['site_adresse'] ?? '—')?></p></div>
            <div class="field"><label>Type de visite</label><p><?=htmlspecialchars($visite['type_visite'])?></p></div>
            <div class="field"><label>Statut</label><p><span class="badge <?=$badge?>"><?=$label?></span></p></div>
            <div class="field"><label>Technicien</label><p><?= $visite['tech_nom'] ? htmlspecialchars($visite['tech_prenom'] . ' ' . $visite['tech_nom']) : 'Non assigné' ?></p></div>
            <div class="field"><label>Date prévue</label><p><?=htmlspecialchars(date('d/m/Y', strtotime($visite['date_prevue'])))?></p></div>
            <div class="field"><label>Date réalisée</label><p><?= $visite['date_realisee'] ? htmlspecialchars(date('d/m/Y', strtotime($visite['date_realisee']))) : '—' ?></p></div>
            <div class="field full"><label>Notes</label><p><?=nl2br(htmlspecialchars($visite['notes'] ?? '—'))?></p></div>
          </div>
        </section>

        <section class="panel table-panel">
          <div class="panel-header">
            <div><p class="eyebrow">Rapports</p><h3><?= count($rapports) ?> rapport(s) lié(s)</h3></div>
            <?php if (can('rapports.creer') && ($role !== 'technicien' || (int)$visite['technicien_id'] === (int)$user['id'])): ?>
            <a class="btn btn-secondary small" href="../rapports/create.php?visite_id=<?=$visite['id']?>">Nouveau rapport</a>
            <?php endif; ?>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Titre</th><th>Rédigé par</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (!$rapports): ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-file-circle-xmark"></i>Aucun rapport pour cette visite.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($rapports as $r): [$rl, $rb] = $rapportStatutLabels[$r['statut']] ?? [$r['statut'], 'muted']; ?>
                <tr>
                  <td><?=htmlspecialchars($r['titre'])?></td>
                  <td><?=htmlspecialchars(trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')) ?: '—')?></td>
                  <td><span class="badge <?=$rb?>"><?=$rl?></span></td>
                  <td><?=htmlspecialchars(date('d/m/Y', strtotime($r['created_at'])))?></td>
                  <td><a class="btn btn-secondary small" href="../rapports/view.php?id=<?=$r['id']?>"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
