<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['superviseur']); // administrateur + superviseur (voir includes/auth.php::can())
$pdo = getPDO();
$user = currentUser($pdo);

// ----------------------------------------------------------------
// Bouton "Actualiser" : relance le script Python generate_analytics.py
// Ce script recalcule TOUT depuis MySQL et réécrit analytics_data.json.
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh']) && csrfCheck()) {
    $projectDir = __DIR__ . DIRECTORY_SEPARATOR . 'python_analytics';
    $scriptFile = $projectDir . DIRECTORY_SEPARATOR . 'generate_analytics.py';

    $candidatePythons = [
        __DIR__ . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
        'C:\\xampp\\htdocs\\pro_gvp\\.venv\\Scripts\\python.exe',
        'python',
        'python3',
        'py',
    ];

    $usedPython = null;
    $output = [];
    $returnCode = 1;

    if (!function_exists('exec')) {
        setFlash('error', "La fonction exec() est désactivée dans php.ini (disable_functions). Retire 'exec' de cette liste et redémarre Apache.");
        header('Location: dashboard_analytics.php');
        exit;
    }

    foreach ($candidatePythons as $candidate) {
        if ($candidate === 'python' || $candidate === 'python3' || $candidate === 'py') {
            $command = '"' . $candidate . '" "' . $scriptFile . '" 2>&1';
        } else {
            if (!is_file($candidate)) {
                continue;
            }
            $command = '"' . $candidate . '" "' . $scriptFile . '" 2>&1';
        }

        $usedPython = $candidate;
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            break;
        }
    }

    if ($returnCode === 0) {
        $jsonPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'analytics' . DIRECTORY_SEPARATOR . 'analytics_data.json';
        if (!is_file($jsonPath)) {
            setFlash('error', 'Le script Python a terminé sans erreur, mais le fichier analytics_data.json n\'a pas été produit.');
        } else {
            setFlash('success', 'Analytics actualisées avec succès ');
            logActivity($pdo, (int)$user['id'], 'refresh_analytics', 'Relance manuelle du module Python Analytics', null, null, 'analytics', null);
        }
    } else {
        $detail = implode("\n", array_slice($output, -12));
        setFlash('error', 'Échec de l\'actualisation (python: ' . htmlspecialchars((string)$usedPython) . '). Détail : ' . htmlspecialchars($detail));
    }
    header('Location: dashboard_analytics.php');
    exit;
}

// ----------------------------------------------------------------
// Source de vérité UNIQUE : le JSON généré par python_analytics/generate_analytics.py
// Aucune valeur affichée ci-dessous ne provient d'une autre logique / d'un autre calcul.
// ----------------------------------------------------------------
$jsonPath = __DIR__ . '/assets/analytics/analytics_data.json';
$analytics = null;
if (is_file($jsonPath)) {
    $raw = file_get_contents($jsonPath);
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && isset($decoded['kpis'])) {
        $analytics = $decoded;
    }
}

$hasAnalytics = $analytics !== null;

// Chemins des graphiques générés par le script Python (assets/analytics/)
$charts = $hasAnalytics ? $analytics['charts'] : [];
$chartsExist = [];
foreach ([
    'visites_par_mois_html', 'visites_par_technicien_html', 'top_clients_html',
    'taux_realisation_png', 'visites_par_mois_png',
] as $key) {
    $chartsExist[$key] = isset($charts[$key]) && is_file(__DIR__ . '/' . $charts[$key]);
}
$anyChartExists = in_array(true, $chartsExist, true);

$pageTitle = 'Analytics';
$activeNav = 'analytics';
require __DIR__ . '/includes/header.php';
?>
        <div class="page-header">
          <div><p class="eyebrow">Pilotage</p><h1>Dashboard Analytics</h1></div>
          <form method="post">
            <?= csrfField() ?>
            <button class="btn btn-primary" type="submit" name="refresh" value="1">
              <i class="fa-solid fa-arrows-rotate"></i> Actualiser les analytics
            </button>
          </form>
        </div>

        <?php if (!$hasAnalytics): ?>
        <div class="analytics-note">
          <i class="fa-solid fa-circle-info"></i>
          <div>
            Les statistiques n'ont pas encore été calculées. Cliquez sur « Actualiser les analytics »
            ou exécutez manuellement <code>python python_analytics/generate_analytics.py</code>
            pour générer <code>assets/analytics/analytics_data.json</code> depuis les données MySQL actuelles.
          </div>
        </div>
        <?php endif; ?>

        <section class="kpi-grid">
          <div class="kpi-card">
            <p>Taux de visites réalisées</p>
            <strong><?= $hasAnalytics ? htmlspecialchars((string)$analytics['kpis']['taux_visites_realisees']['valeur']) : '—' ?>%</strong>
            <span class="kpi-sub"><?= $hasAnalytics ? 'Calculé par Python le ' . htmlspecialchars(date('d/m/Y H:i', strtotime($analytics['generated_at']))) : 'Analytics non générées' ?></span>
          </div>
          <div class="kpi-card">
            <p>Taux de rapports validés</p>
            <strong><?= $hasAnalytics ? htmlspecialchars((string)$analytics['kpis']['taux_rapports_valides']['valeur']) : '—' ?>%</strong>
            <span class="kpi-sub"><?= $hasAnalytics ? 'Calculé par Python le ' . htmlspecialchars(date('d/m/Y H:i', strtotime($analytics['generated_at']))) : 'Analytics non générées' ?></span>
          </div>
          <div class="kpi-card">
            <p>Total visites</p>
            <strong><?= $hasAnalytics ? (int)$analytics['kpis']['total_visites'] : '—' ?></strong>
            <span class="kpi-sub">Toutes visites confondues</span>
          </div>
          <div class="kpi-card">
            <p>Total clients actifs</p>
            <strong><?= $hasAnalytics ? (int)$analytics['kpis']['total_clients_actifs'] : '—' ?></strong>
            <span class="kpi-sub">Portefeuille clients</span>
          </div>
        </section>

        <section class="chart-grid">
          <div class="chart-card">
            <h3>Visites par mois</h3>
            <?php if ($chartsExist['visites_par_mois_html']): ?>
              <iframe src="<?=htmlspecialchars($charts['visites_par_mois_html'])?>" title="Visites par mois"></iframe>
            <?php elseif ($hasAnalytics): ?>
              <div class="table-wrap"><table><thead><tr><th>Mois</th><th>Visites</th></tr></thead><tbody>
                <?php foreach ($analytics['visites_par_mois'] as $row): ?>
                <tr><td><?=htmlspecialchars($row['mois'])?></td><td><?=(int)$row['nb_visites']?></td></tr>
                <?php endforeach; ?>
              </tbody></table></div>
            <?php else: ?>
              <p class="kpi-sub">Aucune donnée disponible.</p>
            <?php endif; ?>
          </div>

          <div class="chart-card">
            <h3>Visites par technicien</h3>
            <?php if ($chartsExist['visites_par_technicien_html']): ?>
              <iframe src="<?=htmlspecialchars($charts['visites_par_technicien_html'])?>" title="Visites par technicien"></iframe>
            <?php elseif ($hasAnalytics): ?>
              <div class="table-wrap"><table><thead><tr><th>Technicien</th><th>Visites</th></tr></thead><tbody>
                <?php foreach ($analytics['visites_par_technicien'] as $row): ?>
                <tr><td><?=htmlspecialchars($row['technicien'])?></td><td><?=(int)$row['nb_visites']?></td></tr>
                <?php endforeach; ?>
              </tbody></table></div>
            <?php else: ?>
              <p class="kpi-sub">Aucune donnée disponible.</p>
            <?php endif; ?>
          </div>

          <div class="chart-card">
            <h3>Top clients</h3>
            <?php if ($chartsExist['top_clients_html']): ?>
              <iframe src="<?=htmlspecialchars($charts['top_clients_html'])?>" title="Top clients"></iframe>
            <?php elseif ($hasAnalytics): ?>
              <div class="table-wrap"><table><thead><tr><th>Client</th><th>Visites</th></tr></thead><tbody>
                <?php foreach ($analytics['top_clients'] as $row): ?>
                <tr><td><?=htmlspecialchars($row['client'])?></td><td><?=(int)$row['nb_visites']?></td></tr>
                <?php endforeach; ?>
              </tbody></table></div>
            <?php else: ?>
              <p class="kpi-sub">Aucune donnée disponible.</p>
            <?php endif; ?>
          </div>

          <?php if ($chartsExist['taux_realisation_png']): ?>
          <div class="chart-card">
            <h3>Taux de réalisation (Matplotlib)</h3>
            <img src="<?=htmlspecialchars($charts['taux_realisation_png'])?>" alt="Taux de réalisation">
          </div>
          <?php endif; ?>

          <?php if ($chartsExist['visites_par_mois_png']): ?>
          <div class="chart-card">
            <h3>Visites par mois (Matplotlib)</h3>
            <img src="<?=htmlspecialchars($charts['visites_par_mois_png'])?>" alt="Visites par mois">
          </div>
          <?php endif; ?>
        </section>
<?php require __DIR__ . '/includes/footer.php'; ?>