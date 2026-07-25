<?php
declare(strict_types=1);
session_start();

// Journaliser la déconnexion avant de détruire la session, si possible.
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/config/database.php';
        require_once __DIR__ . '/includes/auth.php';
        $pdo = getPDO();
        logActivity($pdo, (int)$_SESSION['user_id'], 'deconnexion', 'Déconnexion utilisateur', null, null, 'utilisateur', (int)$_SESSION['user_id']);
    } catch (Throwable $e) {
        // On ignore silencieusement toute erreur de journalisation au logout.
    }
}

// Unset all session variables
$_SESSION = [];
// Destroy session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();
header('Location: login.php');
exit;