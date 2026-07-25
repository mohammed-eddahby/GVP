<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Basic validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!csrfCheck()) {
    $_SESSION['flash_error'] = 'Session expirée, veuillez réessayer.';
    header('Location: login.php');
    exit;
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = trim($_POST['password'] ?? '');

if (!$email || $password === '') {
    $_SESSION['flash_error'] = 'Veuillez remplir tous les champs correctement.';
    header('Location: login.php');
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, nom, prenom, email, mot_de_passe, ville, role, actif FROM utilisateurs WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['actif'] !== 1) {
        $_SESSION['flash_error'] = 'Identifiants invalides.';
        header('Location: login.php');
        exit;
    }

    if (!password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['flash_error'] = 'Identifiants invalides.';
        header('Location: login.php');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['nom'] = $user['nom'];
    $_SESSION['prenom'] = $user['prenom'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['ville'] = $user['ville'];
    $_SESSION['role'] = $user['role'];

    logActivity($pdo, (int)$user['id'], 'connexion', 'Connexion réussie de ' . $user['prenom'] . ' ' . $user['nom'], null, null, 'utilisateur', (int)$user['id']);

    header('Location: dashboard.php');
    exit;

} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Erreur serveur. Réessayez plus tard.';
    header('Location: login.php');
    exit;
}