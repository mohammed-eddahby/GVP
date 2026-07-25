<?php
/**
 * includes/auth.php
 * Fonctions communes : session, authentification, RBAC, CSRF, journalisation.
 * A inclure APRES config/database.php dans chaque page protégée.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Oblige l'utilisateur à être connecté. Redirige vers login.php sinon.
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . basePath() . 'login.php');
        exit;
    }
}

/**
 * Calcule le chemin relatif vers la racine du projet en fonction
 * de la profondeur du script courant (utile depuis /modules/xxx/).
 */
function basePath(): string
{
    // Nombre de sous-dossiers entre la racine du projet et le script courant
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/modules/') !== false) {
        return '../../';
    }
    return './';
}

/**
 * Vérifie que l'utilisateur connecté possède l'un des rôles autorisés.
 * Les administrateurs ont toujours accès à tout.
 *
 * @param string[] $rolesAutorises
 */
function requireRole(array $rolesAutorises): void
{
    requireLogin();
    $role = $_SESSION['role'] ?? '';
    if ($role === 'administrateur') {
        return;
    }
    if (!in_array($role, $rolesAutorises, true)) {
        http_response_code(403);
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
           . '<title>Accès refusé</title></head><body style="font-family:sans-serif;padding:40px;">'
           . '<h1>403 — Accès refusé</h1>'
           . '<p>Vous n\'avez pas les droits nécessaires pour accéder à cette page.</p>'
           . '<p><a href="' . basePath() . 'dashboard.php">Retour au tableau de bord</a></p>'
           . '</body></html>';
        exit;
    }
}

/**
 * RBAC simple : est-ce que le rôle de l'utilisateur courant a le droit "$permission" ?
 * L'administrateur a toujours tous les droits.
 */
function can(string $permission): bool
{
    $role = $_SESSION['role'] ?? '';
    if ($role === 'administrateur') {
        return true;
    }

    $matrix = [
        // permission            => rôles autorisés en plus de l'administrateur
        'utilisateurs.gerer'     => [],
        'clients.gerer'          => ['superviseur'],
        'sites.gerer'            => ['superviseur'],
        'visites.gerer'          => ['superviseur'],
        'visites.consulter'      => ['superviseur', 'technicien'],
        'visites.realiser'       => ['technicien'],
        'rapports.creer'         => ['technicien'],
        'rapports.valider'       => ['superviseur'],
        'rapports.consulter'     => ['superviseur', 'technicien'],
        'analytics.consulter'    => ['superviseur'],
    ];

    return in_array($role, $matrix[$permission] ?? [], true);
}

/** Génère (ou réutilise) un jeton CSRF pour la session courante. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Vérifie le jeton CSRF envoyé par un formulaire POST. */
function csrfCheck(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Champ caché à insérer dans chaque formulaire. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Enregistre une action dans journal_activite.
 * $clientId et $siteId (optionnels) permettent de lier l'action à un client
 * et/ou un site précis, afin d'alimenter les journaux d'activité affichés
 * sur les fiches client et site.
 */
function logActivity(
    PDO $pdo,
    ?int $userId,
    string $action,
    string $description = '',
    ?int $clientId = null,
    ?int $siteId = null,
    ?string $entiteType = null,
    ?int $entiteId = null
): void {
    try {
        // Si l'appelant n'a pas explicitement précisé entite_type/entite_id, on les
        // déduit de client_id/site_id quand c'est possible (compatibilité totale
        // avec les appels existants, qui profitent quand même du nouveau système
        // générique sans avoir besoin d'être modifiés).
        if ($entiteType === null) {
            if ($siteId !== null) {
                $entiteType = 'site';
                $entiteId = $entiteId ?? $siteId;
            } elseif ($clientId !== null) {
                $entiteType = 'client';
                $entiteId = $entiteId ?? $clientId;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO journal_activite (utilisateur_id, client_id, site_id, entite_type, entite_id, action, description, ip_address)
             VALUES (:uid, :cid, :sid, :etype, :eid, :action, :description, :ip)'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':cid' => $clientId,
            ':sid' => $siteId,
            ':etype' => $entiteType,
            ':eid' => $entiteId,
            ':action' => $action,
            ':description' => $description,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Le journal ne doit jamais casser une page ; on ignore silencieusement.
    }
}

/** Message flash de succès / erreur, affiché puis effacé automatiquement. */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function getFlashHtml(): string
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $type => $message) {
        $cssClass = $type === 'success' ? 'alert alert-success' : 'alert alert-error';
        $html .= '<div class="' . $cssClass . '" role="alert">' . htmlspecialchars((string)$message) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/**
 * Récupère l'utilisateur connecté depuis la base (source de vérité),
 * et déconnecte automatiquement si le compte n'existe plus / est désactivé.
 */
function currentUser(PDO $pdo): array
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, nom, prenom, email, telephone, ville, role, actif, created_at FROM utilisateurs WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['actif'] !== 1) {
        header('Location: ' . basePath() . 'logout.php');
        exit;
    }

    return $user;
}

/** Retourne le nom lisible d'un rôle. */
function roleLabel(string $role): string
{
    return match ($role) {
        'administrateur' => 'Administrateur',
        'superviseur'    => 'Superviseur',
        'technicien'     => 'Technicien',
        default          => ucfirst($role),
    };
}