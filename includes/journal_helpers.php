<?php
declare(strict_types=1);

/**
 * Libellé humain (français) du "type d'action" à partir du slug technique
 * stocké dans journal_activite.action (ex: 'creation_client' -> 'Création').
 * Purement dérivé de la convention de nommage déjà suivie par tous les appels
 * logActivity() existants : aucune nouvelle colonne n'est nécessaire pour ça.
 */
function libelleTypeAction(string $action): string
{
    $correspondances = [
        'connexion'         => 'Connexion',
        'deconnexion'       => 'Déconnexion',
        'refresh_analytics' => 'Consultation',
    ];
    if (isset($correspondances[$action])) {
        return $correspondances[$action];
    }

    $prefixes = [
        'creation_'       => 'Création',
        'modification_'   => 'Modification',
        'changement_'     => 'Modification',
        'suppression_'    => 'Suppression',
        'validation_'     => 'Validation',
        'rejet_'          => 'Rejet',
        'telechargement_' => 'Téléchargement',
        'importation_'    => 'Importation',
        'exportation_'    => 'Exportation',
        'consultation_'   => 'Consultation',
    ];
    foreach ($prefixes as $prefixe => $label) {
        if (strpos($action, $prefixe) === 0) {
            return $label;
        }
    }

    return 'Autre';
}

/** Classe de badge (couleur) associée à un type d'action, pour un rendu cohérent. */
function badgeTypeAction(string $action): string
{
    $label = libelleTypeAction($action);
    $classes = [
        'Création'       => 'success',
        'Validation'     => 'success',
        'Modification'   => 'warning',
        'Rejet'          => 'danger',
        'Suppression'    => 'danger',
        'Connexion'      => 'info',
        'Déconnexion'    => 'muted',
        'Téléchargement' => 'info',
        'Importation'    => 'info',
        'Exportation'    => 'info',
        'Consultation'   => 'info',
    ];
    return $classes[$label] ?? 'muted';
}

/** Libellé humain (français) du type d'entité concernée par l'action. */
function libelleEntiteType(?string $entiteType): string
{
    $correspondances = [
        'utilisateur' => 'Utilisateur',
        'client'      => 'Client',
        'site'        => 'Site',
        'visite'      => 'Visite',
        'rapport'     => 'Rapport',
        'analytics'   => 'Analytics',
    ];
    if ($entiteType === null || $entiteType === '') {
        return '—';
    }
    return $correspondances[$entiteType] ?? ucfirst($entiteType);
}

/**
 * Affiche un tableau de journal d'activité de façon cohérente sur toutes les
 * pages qui en ont besoin (Dashboard, Clients, Sites, Visites, Rapports).
 *
 * @param array  $activites     Lignes issues de journal_activite (avec action, description,
 *                               created_at, nom, prenom, entite_type, entite_id).
 * @param string $emptyMessage  Message affiché quand $activites est vide.
 * @param bool   $showEntite    Affiche la colonne "Concerne" (Type + ID).
 */
function renderJournalTable(array $activites, string $emptyMessage, bool $showEntite = true): void
{
    if (!$activites) {
        echo '<div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>' . htmlspecialchars($emptyMessage) . '</div>';
        return;
    }
    ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date et heure</th>
            <th>Utilisateur</th>
            <th>Action</th>
            <?php if ($showEntite): ?><th>Concerne</th><?php endif; ?>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activites as $a): ?>
          <tr>
            <td><?=htmlspecialchars(date('d/m/Y H:i', strtotime($a['created_at'])))?></td>
            <td><?=htmlspecialchars(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?: 'Système')?></td>
            <td><span class="badge <?=badgeTypeAction($a['action'])?>"><?=htmlspecialchars(libelleTypeAction($a['action']))?></span></td>
            <?php if ($showEntite): ?>
            <td>
              <?php if (!empty($a['entite_type'])): ?>
              <span class="badge info"><?=htmlspecialchars(libelleEntiteType($a['entite_type']))?><?= $a['entite_id'] ? ' #' . (int)$a['entite_id'] : '' ?></span>
              <?php else: ?>
              <span class="badge muted">—</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td><?=htmlspecialchars($a['description'] ?? '')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}
