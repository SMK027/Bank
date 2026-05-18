<?php
/**
 * @var string  $month             Mois sélectionné (YYYY-MM)
 * @var string  $prevMonth         Mois précédent (YYYY-MM)
 * @var string  $nextMonth         Mois suivant (YYYY-MM)
 * @var bool    $isCurrentMonth    Vrai si $month == mois courant
 * @var array   $spentByCategory   [category => amount] — dépenses réelles du mois
 * @var array   $budgets           [category => monthly_limit] — budgets définis
 * @var array   $allCategories     Toutes les catégories à afficher
 * @var float   $totalSpent        Total dépensé ce mois
 * @var array   $expenseCategories [category => emoji] — toutes les catégories de dépense
 */

$__frMonths = [
    1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
    5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
    9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
];
$__dt       = new \DateTime($month . '-01');
$monthFull  = $__frMonths[(int) $__dt->format('n')] . ' ' . $__dt->format('Y');

// Palette de couleurs pour le graphique (17 catégories max)
$palette = [
    '#4361ee','#ef476f','#06d6a0','#ffd166','#118ab2',
    '#7209b7','#f4a261','#2ec4b6','#e63946','#457b9d',
    '#a8dadc','#e76f51','#52b788','#9b2226','#6d6875',
    '#b5838d','#e5989b',
];

$categoryColors = [];
$i = 0;
foreach (array_keys($expenseCategories) as $cat) {
    $categoryColors[$cat] = $palette[$i % count($palette)];
    $i++;
}

// Données pour le graphique donut (toutes les dépenses du mois)
$donutLabels = [];
$donutData   = [];
$donutColors = [];
arsort($spentByCategory);
foreach ($spentByCategory as $cat => $amount) {
    $emoji = $expenseCategories[$cat] ?? '';
    $donutLabels[] = $emoji . ' ' . $cat;
    $donutData[]   = round($amount, 2);
    $donutColors[] = $categoryColors[$cat] ?? '#ccc';
}

// Données pour le graphique barres horizontales (catégories avec budget défini)
$barLabels   = [];
$barSpent    = [];
$barLimit    = [];
$barColors   = [];
foreach ($allCategories as $cat) {
    if (!isset($budgets[$cat])) {
        continue;
    }
    $emoji       = $expenseCategories[$cat] ?? '';
    $barLabels[] = $emoji . ' ' . $cat;
    $barSpent[]  = round($spentByCategory[$cat] ?? 0, 2);
    $barLimit[]  = round($budgets[$cat], 2);
    $barColors[] = $categoryColors[$cat] ?? '#ccc';
}
?>

<!-- Navigation mois -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-pie-chart-fill"></i> Budgets mensuels</h1>
        <p class="page-description">Suivez vos dépenses par catégorie et définissez vos plafonds mensuels.</p>
    </div>
    <div class="d-flex gap-2 align-center">
        <a href="/budget?month=<?= urlencode($prevMonth) ?>" class="btn btn-sm btn-outline" title="Mois précédent">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="badge badge-info" style="font-size:0.95rem;padding:0.4rem 0.9rem;text-transform:capitalize;">
            <?= e($monthFull) ?>
        </span>
        <?php if (!$isCurrentMonth): ?>
        <a href="/budget?month=<?= urlencode($nextMonth) ?>" class="btn btn-sm btn-outline" title="Mois suivant">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php else: ?>
        <span class="btn btn-sm btn-outline" style="opacity:.35;cursor:default;" aria-disabled="true">
            <i class="bi bi-chevron-right"></i>
        </span>
        <?php endif; ?>
        <a href="/budget" class="btn btn-sm btn-outline" title="Mois courant">
            <i class="bi bi-calendar-check"></i>
        </a>
    </div>
</div>

<!-- Stats globales -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
    <div class="stat-card">
        <div class="stat-value <?= $totalSpent > 0 ? 'text-danger' : '' ?>">
            <?= fmt_amount_smart($totalSpent) ?> €
        </div>
        <div class="stat-label">Total dépensé</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($spentByCategory) ?></div>
        <div class="stat-label">Catégories actives</div>
    </div>
    <div class="stat-card">
        <?php
            $budgetTotal  = array_sum($budgets);
            $budgetOver   = 0;
            foreach ($budgets as $cat => $limit) {
                if (($spentByCategory[$cat] ?? 0) > $limit) {
                    $budgetOver++;
                }
            }
        ?>
        <div class="stat-value <?= $budgetOver > 0 ? 'text-danger' : 'text-success' ?>">
            <?= $budgetOver ?>
        </div>
        <div class="stat-label">Budget(s) dépassé(s)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($budgets) ?></div>
        <div class="stat-label">Plafonds définis</div>
    </div>
</div>

<?php if (empty($spentByCategory)): ?>
<div class="empty-state">
    <div class="empty-icon">📊</div>
    <p>Aucune dépense enregistrée pour <?= e($monthFull) ?>.</p>
    <p class="text-muted text-small">Vous pouvez tout de même définir vos budgets ci-dessous.</p>
</div>
<?php else: ?>

<!-- Graphiques -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;" class="budget-charts-grid">

    <!-- Graphique donut : répartition des dépenses -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin-bottom:1rem;font-size:1rem;">
                <i class="bi bi-pie-chart"></i> Répartition des dépenses
            </h3>
            <div style="position:relative;height:260px;">
                <canvas id="donutChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique barres : dépensé vs budget -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin-bottom:1rem;font-size:1rem;">
                <i class="bi bi-bar-chart-horizontal"></i> Dépenses vs budgets définis
            </h3>
            <?php if (empty($barLabels)): ?>
            <div class="empty-state" style="min-height:220px;">
                <div class="empty-icon" style="font-size:2rem;">🎯</div>
                <p class="text-muted text-small">Définissez des plafonds ci-dessous pour afficher ce graphique.</p>
            </div>
            <?php else: ?>
            <div style="position:relative;height:260px;">
                <canvas id="barChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php endif; ?>

<!-- Tableau des catégories avec barres de progression -->
<?php if (!empty($allCategories)): ?>
<h2 class="mb-2"><i class="bi bi-list-check"></i> Détail par catégorie</h2>
<div class="card" style="margin-bottom:2rem;">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:30%;">Catégorie</th>
                    <th style="width:20%;text-align:right;">Dépensé</th>
                    <th style="width:20%;text-align:right;">Plafond</th>
                    <th style="width:10%;text-align:center;">État</th>
                    <th style="width:20%;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCategories as $cat):
                    $spent  = $spentByCategory[$cat] ?? 0.0;
                    $limit  = $budgets[$cat] ?? 0.0;
                    $emoji  = $expenseCategories[$cat] ?? '📌';
                    $ratio  = ($limit > 0) ? min(100, round($spent / $limit * 100)) : null;
                    $over   = ($limit > 0 && $spent > $limit);
                    $near   = ($ratio !== null && $ratio >= 80 && !$over);
                    $color  = $categoryColors[$cat] ?? '#4361ee';
                ?>
                <tr>
                    <td>
                        <span style="font-size:1.1em;"><?= e($emoji) ?></span>
                        <strong><?= e($cat) ?></strong>
                    </td>
                    <td style="text-align:right;font-weight:600;" class="<?= $over ? 'text-danger' : ($near ? 'text-warning' : '') ?>">
                        <?= fmt_amount_smart($spent) ?> €
                    </td>
                    <td style="text-align:right;color:var(--gray);">
                        <?= $limit > 0 ? fmt_amount_smart($limit) . ' €' : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($ratio === null): ?>
                            <span class="badge" style="background:var(--gray-lighter);color:var(--gray);font-size:0.75em;">—</span>
                        <?php elseif ($over): ?>
                            <span class="badge" style="background:var(--danger);color:#fff;font-size:0.75em;"><i class="bi bi-exclamation-triangle"></i> Dépassé</span>
                        <?php elseif ($near): ?>
                            <span class="badge" style="background:var(--warning);color:#333;font-size:0.75em;"><i class="bi bi-exclamation-circle"></i> <?= $ratio ?>%</span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--success);color:#fff;font-size:0.75em;"><i class="bi bi-check-circle"></i> <?= $ratio ?>%</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($ratio !== null): ?>
                        <div style="background:var(--gray-lighter);border-radius:4px;height:8px;overflow:hidden;" title="<?= $ratio ?>% du budget utilisé">
                            <div style="
                                height:100%;
                                width:<?= $ratio ?>%;
                                background:<?= $over ? 'var(--danger)' : ($near ? 'var(--warning)' : $color) ?>;
                                border-radius:4px;
                                transition:width .4s ease;
                            "></div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Formulaire de définition / modification des budgets -->
<h2 class="mb-2"><i class="bi bi-pencil-square"></i> Définir / modifier un budget</h2>
<div class="card" style="margin-bottom:2rem;">
    <div class="card-body">
        <p class="text-muted text-small mb-2">
            Le plafond s'applique chaque mois. Entrez <strong>0</strong> pour supprimer un budget existant.
        </p>
        <form method="POST" action="/budget/save">
            <?= csrf_field() ?>
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:1rem;align-items:end;" class="budget-form-grid">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="budgetCategory">Catégorie</label>
                    <select class="form-control" id="budgetCategory" name="category" required>
                        <option value="">— Choisir une catégorie —</option>
                        <?php foreach ($expenseCategories as $cat => $emoji): ?>
                            <?php $current = $budgets[$cat] ?? null; ?>
                            <option value="<?= e($cat) ?>" data-current="<?= $current !== null ? fmt_amount_smart($current) : '' ?>">
                                <?= e($emoji . ' ' . $cat) ?><?= $current !== null ? ' — ' . fmt_amount_smart($current) . ' €/mois' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="budgetLimit">Plafond mensuel (€)</label>
                    <input
                        type="number"
                        class="form-control"
                        id="budgetLimit"
                        name="monthly_limit"
                        min="0"
                        step="0.01"
                        placeholder="ex : 300.00"
                        required
                    >
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy"></i> Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Chart.js + initialisation des graphiques -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ── Donut chart : répartition des dépenses ─────────────────────
    <?php if (!empty($donutData)): ?>
    var donutCtx = document.getElementById('donutChart');
    if (donutCtx) {
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($donutLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    data: <?= json_encode($donutData) ?>,
                    backgroundColor: <?= json_encode($donutColors) ?>,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 11 },
                            boxWidth: 12,
                            padding: 10,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct   = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                                return ' ' + ctx.parsed.toFixed(2).replace('.', ',') + ' € (' + pct + ' %)';
                            }
                        }
                    }
                },
                cutout: '60%',
            }
        });
    }
    <?php endif; ?>

    // ── Barres horizontales : dépensé vs budget ────────────────────
    <?php if (!empty($barLabels)): ?>
    var barCtx = document.getElementById('barChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($barLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    {
                        label: 'Dépensé',
                        data: <?= json_encode($barSpent) ?>,
                        backgroundColor: <?= json_encode(array_map(fn($c) => $c . 'cc', $barColors)) ?>,
                        borderColor: <?= json_encode($barColors) ?>,
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Plafond',
                        data: <?= json_encode($barLimit) ?>,
                        backgroundColor: 'rgba(108,117,125,0.15)',
                        borderColor: 'rgba(108,117,125,0.5)',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderDash: [4, 4],
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ' : ' + ctx.parsed.x.toFixed(2).replace('.', ',') + ' €';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) { return v + ' €'; },
                            font: { size: 11 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: { ticks: { font: { size: 11 } } }
                }
            }
        });
    }
    <?php endif; ?>

    // ── Pré-remplissage du champ budget lors du choix de catégorie ──
    var sel   = document.getElementById('budgetCategory');
    var input = document.getElementById('budgetLimit');
    if (sel && input) {
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            var cur = opt ? opt.getAttribute('data-current') : '';
            input.value = cur ? cur.replace(',', '.') : '';
        });
    }
})();
</script>

<style>
@media (max-width: 700px) {
    .budget-charts-grid { grid-template-columns: 1fr !important; }
    .budget-form-grid   { grid-template-columns: 1fr !important; }
}
</style>
