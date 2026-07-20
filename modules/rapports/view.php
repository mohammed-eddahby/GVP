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
    "SELECT r.*, s.nom_site, c.nom_entreprise,
            au.nom AS auteur_nom, au.prenom AS auteur_prenom,
            va.nom AS valideur_nom, va.prenom AS valideur_prenom
     FROM rapports r
     JOIN visites v ON v.id = r.visite_id
     JOIN sites s ON s.id = v.site_id
     JOIN clients c ON c.id = s.client_id
     LEFT JOIN utilisateurs au ON au.id = r.redige_par
     LEFT JOIN utilisateurs va ON va.id = r.valide_par
     WHERE r.id = :id"
);
$stmt->execute([':id' => $id]);
$rapport = $stmt->fetch();

if (!$rapport) {
    setFlash('error', 'Rapport introuvable.');
    header('Location: index.php');
    exit;
}

$statutLabels = [
    'brouillon' => ['Brouillon', 'muted'], 'soumis' => ['Soumis', 'warning'],
    'valide' => ['Validé', 'success'], 'rejete' => ['Rejeté', 'danger'],
];
[$label, $badge] = $statutLabels[$rapport['statut']] ?? [$rapport['statut'], 'muted'];

$pageTitle = 'Détail rapport';
$activeNav = 'rapports';
require __DIR__ . '/../../includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Suivi</p><h1><?=htmlspecialchars($rapport['titre'])?></h1></div>
          <a class="btn btn-secondary" href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </div>

        <section class="panel">
          <div class="form-grid">
            <div class="field"><label>Site / Client</label><p><?=htmlspecialchars($rapport['nom_entreprise'])?> — <?=htmlspecialchars($rapport['nom_site'])?></p></div>
            <div class="field"><label>Statut</label><p><span class="badge <?=$badge?>"><?=$label?></span></p></div>
            <div class="field"><label>Rédigé par</label><p><?=htmlspecialchars(trim(($rapport['auteur_prenom'] ?? '') . ' ' . ($rapport['auteur_nom'] ?? '')) ?: '—')?></p></div>
            <div class="field"><label>Validé par</label><p><?= $rapport['valideur_nom'] ? htmlspecialchars($rapport['valideur_prenom'] . ' ' . $rapport['valideur_nom']) : '—' ?></p></div>
            <div class="field"><label>Date de soumission</label><p><?= $rapport['date_soumission'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($rapport['date_soumission']))) : '—' ?></p></div>
            <div class="field"><label>Date de validation</label><p><?= $rapport['date_validation'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($rapport['date_validation']))) : '—' ?></p></div>
            <div class="field full">
              <label>Document joint</label>
              <?php if (!empty($rapport['document_path'])): ?>
              <p>
                <i class="fa-solid fa-paperclip"></i> Document <?=strtoupper((string)$rapport['document_type'])?> joint
                &nbsp;—&nbsp;
                <a href="download.php?id=<?=$rapport['id']?>&mode=view" target="_blank" rel="noopener">Ouvrir</a>
                &nbsp;|&nbsp;
                <a href="download.php?id=<?=$rapport['id']?>&mode=download">Télécharger</a>
              </p>
              <?php else: ?>
              <p style="color:var(--text-muted);">Aucun document joint.</p>
              <?php endif; ?>
            </div>
            <div class="field full"><label>Contenu</label><p><?=nl2br(htmlspecialchars($rapport['contenu'] ?? '—'))?></p></div>
          </div>

          <?php if (can('rapports.valider') && $rapport['statut'] === 'soumis'): ?>
          <div class="form-actions">
            <a class="btn btn-primary" href="validate.php?id=<?=$rapport['id']?>&action=valider" onclick="return confirm('Valider ce rapport ?');">Valider</a>
            <a class="btn btn-danger" href="validate.php?id=<?=$rapport['id']?>&action=rejeter" onclick="return confirm('Rejeter ce rapport ?');">Rejeter</a>
          </div>
          <?php endif; ?>
        </section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
