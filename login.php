<?php
require_once __DIR__ . '/includes/auth.php'; // session_start() + csrfToken()/csrfField()

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$csrfToken = csrfToken(); // pre-generate so it's ready for the hidden field below

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Connexion - Gestion Visites</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" type="image/png" href="assets/img/optimus-telecom-logo.png">
  <meta name="theme-color" content="#242424">
</head>
<body>
  <div class="page-shell">
    <div class="ambient ambient-one"></div>
    <div class="ambient ambient-two"></div>
    <div class="ambient ambient-three"></div>

    <main class="auth-layout" aria-label="Connexion à la plateforme">
      <section class="hero-panel" aria-hidden="true">
        <div class="hero-content">
          <div class="hero-badge">Gestion Visites</div>
          <h1>Accédez à votre espace de gestion.</h1>
          <p>Une expérience d’authentification moderne, fluide et sécurisée pour votre équipe.</p>
          <ul class="hero-points">
            <li><i class="fa-solid fa-shield-halved"></i> Sécurisé</li>
            <li><i class="fa-solid fa-bolt"></i> Rapide</li>
            <li><i class="fa-solid fa-sparkles"></i> Élégant</li>
          </ul>
        </div>
      </section>

      <section class="login-panel">
        <div class="glass-card">
          <div class="brand-row">
            <div class="brand-mark"><img src="assets/img/optimus-telecom-logo.png" alt="Optimus Telecom" class="brand-logo"></div>
            <div>
              <h2>Bienvenue</h2>
              <p>Connectez-vous à votre compte</p>
            </div>
          </div>

          <?php if ($error): ?>
            <div class="alert" role="alert"><?=htmlspecialchars($error)?></div>
          <?php endif; ?>

          <form id="loginForm" action="login_process.php" method="post" novalidate>
            <?= csrfField() ?>
            <div class="field">
              <label for="email">Email</label>
              <div class="input-shell">
                <i class="fa-solid fa-envelope"></i>
                <input id="email" name="email" type="email" required placeholder="votre@email.com">
              </div>
              <small class="error">Adresse e-mail valide requise</small>
            </div>

            <div class="field">
              <label for="password">Mot de passe</label>
              <div class="input-shell">
                <i class="fa-solid fa-lock"></i>
                <input id="password" name="password" type="password" required placeholder="Mot de passe">
                <button type="button" id="togglePassword" class="icon-btn" aria-label="Afficher le mot de passe" aria-pressed="false">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <small class="error">Mot de passe requis</small>
            </div>

            <button type="submit" class="btn primary">Se connecter</button>
          </form>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/js/script.js"></script>
</body>
</html>
