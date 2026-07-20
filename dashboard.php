<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$pdo = getPDO();
$user = currentUser($pdo);
$role = $user['role'];

// ----------------------------------------------------------------
// Statistiques générales (adaptées au rôle)
// ----------------------------------------------------------------
$stats = [
    'clients'  => (int)$pdo->query('SELECT COUNT(*) c FROM clients')->fetch()['c'],
    'sites'    => (int)$pdo->query('SELECT COUNT(*) c FROM sites')->fetch()['c'],
    'visites'  => (int)$pdo->query('SELECT COUNT(*) c FROM visites')->fetch()['c'],
    'rapports' => (int)$pdo->query('SELECT COUNT(*) c FROM rapports')->fetch()['c'],
];

$usersCount = null;
if ($role === 'administrateur') {
    $usersCount = (int)$pdo->query('SELECT COUNT(*) c FROM utilisateurs')->fetch()['c'];
}

$tauxRealisees = 0.0;
$totalVisites = (int)$pdo->query("SELECT COUNT(*) c FROM visites")->fetch()['c'];
$visitesRealisees = (int)$pdo->query("SELECT COUNT(*) c FROM visites WHERE statut = 'realisee'")->fetch()['c'];
if ($totalVisites > 0) {
    $tauxRealisees = round(($visitesRealisees / $totalVisites) * 100, 1);
}

// Rapports déjà créés : COUNT(DISTINCT visite_id) car une visite peut avoir
// plusieurs lignes dans `rapports` (ex. brouillon rejeté puis resoumis) —
// sans le DISTINCT, ces cas seraient comptés en double.
$rapportsCrees = (int)$pdo->query('SELECT COUNT(DISTINCT visite_id) c FROM rapports')->fetch()['c'];

// Dernières visites (filtrées pour un technicien : uniquement les siennes)
if ($role === 'technicien') {
    $stmtVisites = $pdo->prepare(
        'SELECT v.id, v.type_visite, v.statut, v.date_prevue, s.nom_site, c.nom_entreprise
         FROM visites v
         JOIN sites s ON s.id = v.site_id
         JOIN clients c ON c.id = s.client_id
         WHERE v.technicien_id = :uid
         ORDER BY v.date_prevue DESC LIMIT 6'
    );
    $stmtVisites->execute([':uid' => $user['id']]);
} else {
    $stmtVisites = $pdo->query(
        'SELECT v.id, v.type_visite, v.statut, v.date_prevue, s.nom_site, c.nom_entreprise
         FROM visites v
         JOIN sites s ON s.id = v.site_id
         JOIN clients c ON c.id = s.client_id
         ORDER BY v.date_prevue DESC LIMIT 6'
    );
}
$dernieresVisites = $stmtVisites->fetchAll();

// Journal d'activité récent (admin / superviseur uniquement)
$activites = [];
if ($role !== 'technicien') {
    $activites = $pdo->query(
        "SELECT j.action, j.description, j.created_at, u.nom, u.prenom
         FROM journal_activite j
         LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
         ORDER BY j.created_at DESC LIMIT 6"
    )->fetchAll();
}

$statutLabels = [
    'planifiee' => ['Planifiée', 'info'],
    'en_cours'  => ['En cours', 'warning'],
    'realisee'  => ['Réalisée', 'success'],
    'annulee'   => ['Annulée', 'danger'],
];

$pageTitle = 'Tableau de bord';
$activeNav = 'accueil';
require __DIR__ . '/includes/header.php';
?>
        <section class="hero-panel">
          <div>
            <p class="eyebrow">Tableau de bord</p>
            <h1>Bienvenue, <?=htmlspecialchars($user['prenom'] . ' ' . $user['nom'])?></h1>
            <p class="hero-copy">Vous êtes connecté en tant que <strong><?=htmlspecialchars(roleLabel($role))?></strong> et pouvez accéder aux outils adaptés à votre profil.</p>
          </div>
          <div class="hero-actions">
            <?php if (can('visites.gerer')): ?>
            <a class="btn btn-primary" href="modules/visites/create.php">Nouvelle visite</a>
            <?php endif; ?>
            <?php if (can('analytics.consulter')): ?>
            <a class="btn btn-secondary" href="dashboard_analytics.php">Voir analytics</a>
            <?php endif; ?>
          </div>
        </section>

        <section class="stats-grid">
          <?php if ($usersCount !== null): ?>
          <article class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div>
              <p>Utilisateurs</p>
              <strong><?= htmlspecialchars((string)$usersCount) ?></strong>
            </div>
          </article>
          <?php endif; ?>
          <article class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
            <div>
              <p>Clients</p>
              <strong><?= $stats['clients'] ?></strong>
            </div>
          </article>
          <article class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div>
              <p>Sites</p>
              <strong><?= $stats['sites'] ?></strong>
            </div>
          </article>
          <article class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-briefcase"></i></div>
            <div>
              <p>Visites</p>
              <strong><?= $visitesRealisees ?>/<?= $totalVisites ?></strong>
            </div>
          </article>
          <article class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-chart-pie"></i></div>
            <div>
              <p>Taux de réalisation</p>
              <strong><?= $tauxRealisees ?>%</strong>
            </div>
          </article>
        </section>

        <section class="dashboard-grid">
          <div class="panel panel-large">
            <div class="panel-header">
              <div>
                <p class="eyebrow">Vue générale</p>
                <h3><?= $role === 'technicien' ? 'Mes prochaines visites' : 'Dernières visites' ?></h3>
              </div>
              <a class="btn btn-secondary small" href="modules/visites/index.php">Voir tout</a>
            </div>

            <div class="activity-list">
              <?php if (!$dernieresVisites): ?>
                <div class="empty-state"><i class="fa-solid fa-inbox"></i>Aucune visite pour le moment.</div>
              <?php endif; ?>
              <?php foreach ($dernieresVisites as $v): [$label, $badge] = $statutLabels[$v['statut']] ?? [$v['statut'], 'muted']; ?>
              <div class="activity-item">
                <div class="activity-icon blue"><i class="fa-solid fa-briefcase"></i></div>
                <div>
                  <strong><?=htmlspecialchars($v['nom_entreprise'])?> — <?=htmlspecialchars($v['nom_site'])?></strong>
                  <p><?=htmlspecialchars($v['type_visite'])?> · <span class="badge <?=$badge?>"><?=$label?></span></p>
                </div>
                <span><?=htmlspecialchars(date('d/m/Y', strtotime($v['date_prevue'])))?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="panel">
            <div class="panel-header">
              <div>
                <p class="eyebrow">Accès</p>
                <h3>Permissions</h3>
              </div>
            </div>
            <div class="pill-list">
              <span class="pill">Visites</span>
              <span class="pill">Rapports</span>
              <?php if (can('clients.gerer')): ?><span class="pill">Clients</span><?php endif; ?>
              <?php if (can('sites.gerer')): ?><span class="pill">Sites</span><?php endif; ?>
              <?php if ($role === 'administrateur'): ?><span class="pill">Administration</span><?php endif; ?>
              <?php if (can('analytics.consulter')): ?><span class="pill">Analytics</span><?php endif; ?>
            </div>
          </div>
        </section>

        <?php if ($activites): ?>
        <section class="panel table-panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Journal</p>
              <h3>Activité récente</h3>
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Utilisateur</th>
                  <th>Action</th>
                  <th>Description</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activites as $a): ?>
                <tr>
                  <td><?=htmlspecialchars(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?: 'Système')?></td>
                  <td><span class="badge info"><?=htmlspecialchars($a['action'])?></span></td>
                  <td><?=htmlspecialchars($a['description'] ?? '')?></td>
                  <td><?=htmlspecialchars(date('d/m/Y H:i', strtotime($a['created_at'])))?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
        <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
