<?php
/**
 * includes/header.php
 * Attend les variables suivantes définies AVANT l'include :
 *   $pageTitle  (string) titre de la page
 *   $activeNav  (string) clé de nav active : accueil|utilisateurs|clients|sites|visites|rapports|analytics
 *   $user       (array)  utilisateur courant (issu de currentUser())
 * Nécessite includes/auth.php déjà inclus (requireLogin appelé en amont).
 */
$base = basePath();
$role = $user['role'] ?? '';
$prenom = $user['prenom'] ?? '';
$nom = $user['nom'] ?? '';
?><!doctype html>
<html lang="fr" data-theme="dark" class="theme-loading">
<head> 
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=htmlspecialchars($pageTitle ?? 'GVP')?> - Gestion des Visites Préventives</title>
  <script>
    // Anti-flicker: apply the saved theme preference synchronously, before
    // the stylesheet below paints, so there is no dark->light (or reverse)
    // flash on navigation. Kept deliberately tiny and dependency-free.
    (function () {
      try {
        var saved = localStorage.getItem('gvp-theme');
        if (saved === 'light' || saved === 'dark') {
          document.documentElement.setAttribute('data-theme', saved);
        }
      } catch (e) { /* localStorage unavailable — default theme stays */ }
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?=$base?>assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" type="image/png" href="<?=$base?>assets/img/optimus-telecom-logo.png">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark"><img src="<?=$base?>assets/img/optimus-telecom-logo.png" alt="Optimus Telecom" class="brand-logo"></div>
        <div>
          <h2>GVP</h2>
          <p>Visites Préventives</p>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Navigation principale">
        <a href="<?=$base?>dashboard.php" class="nav-link <?= $activeNav === 'accueil' ? 'active' : '' ?>">
          <i class="fa-solid fa-house"></i><span>Accueil</span>
        </a>

        <?php if (can('clients.gerer')): ?>
        <a href="<?=$base?>modules/clients/index.php" class="nav-link <?= $activeNav === 'clients' ? 'active' : '' ?>">
          <i class="fa-solid fa-building"></i><span>Clients</span>
        </a>
        <?php endif; ?>

        <?php if (can('sites.gerer')): ?>
        <a href="<?=$base?>modules/sites/index.php" class="nav-link <?= $activeNav === 'sites' ? 'active' : '' ?>">
          <i class="fa-solid fa-location-dot"></i><span>Sites</span>
        </a>
        <?php endif; ?>

        <a href="<?=$base?>modules/visites/index.php" class="nav-link <?= $activeNav === 'visites' ? 'active' : '' ?>">
          <i class="fa-solid fa-briefcase"></i><span>Visites</span>
        </a>

        <a href="<?=$base?>modules/rapports/index.php" class="nav-link <?= $activeNav === 'rapports' ? 'active' : '' ?>">
          <i class="fa-solid fa-file-lines"></i><span>Rapports</span>
        </a>

        <?php if (can('analytics.consulter')): ?>
        <a href="<?=$base?>dashboard_analytics.php" class="nav-link <?= $activeNav === 'analytics' ? 'active' : '' ?>">
          <i class="fa-solid fa-chart-line"></i><span>Analytics</span>
        </a>
        <?php endif; ?>

        <?php if ($role === 'administrateur'): ?>
        <a href="<?=$base?>modules/utilisateurs/index.php" class="nav-link <?= $activeNav === 'utilisateurs' ? 'active' : '' ?>">
          <i class="fa-solid fa-users-gear"></i><span>Utilisateurs</span>
        </a>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <div class="mini-card">
          <p>Sécurité</p>
          <strong>Connexion protégée</strong>
        </div>
      </div>
    </aside>

    <div class="main-panel">
      <header class="topbar">
        <button class="icon-btn" id="sidebarToggle" type="button" aria-label="Afficher la sidebar">
          <i class="fa-solid fa-bars"></i>
        </button>

        <div class="topbar-search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Rechercher..." aria-label="Rechercher">
        </div>

        <div class="topbar-actions">
          <button class="icon-btn" id="themeToggle" type="button" aria-label="Basculer le mode sombre">
            <i class="fa-solid fa-moon"></i>
          </button>
          <div class="user-pill">
            <div class="avatar" aria-hidden="true"><?php echo strtoupper(substr($prenom,0,1) . substr($nom,0,1)); ?></div>
            <div>
              <div class="user-name"><?=htmlspecialchars($prenom . ' ' . $nom)?></div>
              <div class="user-role"><?=htmlspecialchars(roleLabel($role))?></div>
            </div>
          </div>
          <a class="ghost-btn" href="<?=$base?>logout.php">Déconnexion</a>
        </div>
      </header>

      <main class="content-area">
        <?= getFlashHtml() ?>
