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

// Relation 1 visite -> 1 rapport : au plus un rapport par visite.
$rapportStmt = $pdo->prepare('SELECT r.*, u.nom, u.prenom FROM rapports r LEFT JOIN utilisateurs u ON u.id = r.redige_par WHERE r.visite_id = :id LIMIT 1');
$rapportStmt->execute([':id' => $id]);
$rapport = $rapportStmt->fetch() ?: null;

$statutLabels = [
    'planifiee' => ['Planifiée', 'info'], 'en_cours' => ['En cours', 'warning'],
    'realisee' => ['Réalisée', 'success'], 'annulee' => ['Annulée', 'danger'],
];
$rapportStatutLabels = [
    'brouillon' => ['Brouillon', 'muted'], 'soumis' => ['Soumis', 'warning'],
    'valide' => ['Validé', 'success'], 'rejete' => ['Rejeté', 'danger'],
];

// Mêmes règles RBAC que celles historiquement appliquées dans le module Rapports.
$peutCreerRapport = !$rapport && can('rapports.creer') && ($role !== 'technicien' || (int)$visite['technicien_id'] === (int)$user['id']);
$peutModifierRapport = $rapport && $role === 'technicien' && (int)$rapport['redige_par'] === (int)$user['id'] && in_array($rapport['statut'], ['brouillon', 'rejete'], true);
$estBrouillonProprietaire = $rapport && $role === 'technicien' && (int)$rapport['redige_par'] === (int)$user['id'] && $rapport['statut'] === 'brouillon';
$peutSupprimerRapport = $rapport && (can('rapports.valider') || $role === 'administrateur' || $estBrouillonProprietaire);
$peutValiderRapport = $rapport && can('rapports.valider') && $rapport['statut'] === 'soumis';
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
            <div><p class="eyebrow">Suivi</p><h3>Rapport</h3></div>
            <?php if ($peutCreerRapport): ?>
            <a class="btn btn-primary small" href="../rapports/create.php?visite_id=<?=$visite['id']?>"><i class="fa-solid fa-plus"></i> Créer le rapport</a>
            <?php endif; ?>
          </div>

          <?php if (!$rapport): ?>
          <div class="empty-state">
            <i class="fa-solid fa-file-circle-xmark"></i>
            Aucun rapport pour cette visite.
            <?php if (!$peutCreerRapport): ?><br><small>Le rapport ne peut être créé que par le technicien assigné à cette visite.</small><?php endif; ?>
          </div>
          <?php else: [$rl, $rb] = $rapportStatutLabels[$rapport['statut']] ?? [$rapport['statut'], 'muted']; ?>
          <div class="form-grid">
            <div class="field"><label>Titre</label><p><?=htmlspecialchars($rapport['titre'])?></p></div>
            <div class="field"><label>Statut</label><p><span class="badge <?=$rb?>"><?=$rl?></span></p></div>
            <div class="field"><label>Rédigé par</label><p><?=htmlspecialchars(trim(($rapport['prenom'] ?? '') . ' ' . ($rapport['nom'] ?? '')) ?: '—')?></p></div>
            <div class="field"><label>Date de création</label><p><?=htmlspecialchars(date('d/m/Y', strtotime($rapport['created_at'])))?></p></div>
            <div class="field full">
              <label>Document joint</label>
              <?php if (!empty($rapport['document_path'])): ?>
              <p>
                <i class="fa-solid fa-paperclip"></i> Document <?=strtoupper((string)$rapport['document_type'])?> joint
                &nbsp;—&nbsp;
                <a href="../rapports/download.php?id=<?=$rapport['id']?>&mode=view" target="_blank" rel="noopener">Ouvrir</a>
                &nbsp;|&nbsp;
                <a href="../rapports/download.php?id=<?=$rapport['id']?>&mode=download">Télécharger</a>
              </p>
              <?php else: ?>
              <p style="color:var(--text-muted);">Aucun document joint.</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="form-actions">
            <a class="btn btn-secondary small" href="../rapports/view.php?id=<?=$rapport['id']?>"><i class="fa-solid fa-eye"></i> Voir le rapport</a>
            <?php if ($peutModifierRapport): ?>
            <a class="btn btn-secondary small" href="../rapports/edit.php?id=<?=$rapport['id']?>"><i class="fa-solid fa-pen"></i> Modifier</a>
            <?php endif; ?>
            <?php if ($peutValiderRapport): ?>
            <a class="btn btn-primary small" href="../rapports/validate.php?id=<?=$rapport['id']?>&action=valider" onclick="return confirm('Valider ce rapport ?');">Valider</a>
            <a class="btn btn-danger small" href="../rapports/validate.php?id=<?=$rapport['id']?>&action=rejeter" onclick="return confirm('Rejeter ce rapport ?');">Rejeter</a>
            <?php endif; ?>
            <?php if ($peutSupprimerRapport): ?>
            <form method="post" action="../rapports/delete.php" onsubmit="return confirm('Supprimer ce rapport ?');" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?=$rapport['id']?>">
              <button class="btn btn-danger small" type="submit"><i class="fa-solid fa-trash"></i> Supprimer</button>
            </form>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>