<?php
/**
 * @var array       $types      Types de crédits disponibles (LoanSimulation::getTypes())
 * @var array|null  $simulation Résultat du calcul (null si pas encore simulé)
 * @var array       $history    Historique des simulations de l'utilisateur
 */

$sim          = $simulation ?? null;
$loanTypeKeys = array_keys($types);
$typesJson    = json_encode($types, JSON_HEX_QUOT | JSON_HEX_TAG);
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-calculator-fill"></i> Simulateur de crédits</h1>
        <p class="page-description">Estimez vos mensualités selon le type de crédit, le montant et la durée.</p>
    </div>
</div>

<div class="loan-layout">

    <!-- ══════════════════════════════════════════════
         Colonne gauche : sélection + formulaire
    ══════════════════════════════════════════════ -->
    <div class="loan-sidebar">

        <!-- Types de crédits -->
        <div class="loan-types-grid">
            <?php foreach ($types as $key => $t): ?>
            <button type="button"
                    class="loan-type-card <?= ($sim && $sim['loan_type'] === $key) ? 'loan-type-card--active' : '' ?>"
                    data-type="<?= e($key) ?>"
                    onclick="selectLoanType('<?= e($key) ?>')">
                <span class="loan-type-icon"><i class="bi <?= e($t['icon']) ?>"></i></span>
                <span class="loan-type-label"><?= e($t['label']) ?></span>
                <span class="loan-type-rate"><?= number_format($t['rate'], 2, ',', ' ') ?>&nbsp;%/an</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Formulaire -->
        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-sliders"></i> Paramètres</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="/loans/simulator" id="loan-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="loan_type" id="loan_type"
                           value="<?= e($sim['loan_type'] ?? $loanTypeKeys[0]) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="amount">Montant emprunté (€)</label>
                            <input type="number" id="amount" name="amount" class="form-control"
                                   step="100" min="1" inputmode="decimal"
                                   value="<?= e((string) ($sim['amount'] ?? '')) ?>"
                                   placeholder="Ex : 10 000" required>
                            <small id="amount-hint" class="form-hint"></small>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="months">Durée (mois)</label>
                            <input type="number" id="months" name="months" class="form-control"
                                   step="1" min="1" inputmode="numeric"
                                   value="<?= e((string) ($sim['months'] ?? '')) ?>"
                                   placeholder="Ex : 60" required>
                            <small id="months-hint" class="form-hint"></small>
                        </div>
                    </div>

                    <div id="rate-info" class="loan-rate-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <span id="rate-info-text">—</span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-calculator"></i> Calculer les mensualités
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         Colonne droite : résultats
    ══════════════════════════════════════════════ -->
    <div class="loan-main">

        <?php if ($sim): ?>
        <!-- Résultat -->
        <div class="card loan-result-card" id="result-card">
            <div class="card-header loan-result-header">
                <div>
                    <h3><i class="bi bi-graph-up-arrow"></i> Résultat</h3>
                    <p class="page-description" style="margin:0"><?= e($sim['type_label']) ?> — <?= $sim['months'] ?> mois à <?= number_format($sim['annual_rate'], 2, ',', ' ') ?>&nbsp;%/an</p>
                </div>
            </div>
            <div class="card-body">

                <!-- KPIs -->
                <div class="loan-kpis">
                    <div class="loan-kpi loan-kpi--primary">
                        <span class="loan-kpi-value"><?= number_format($sim['monthly_payment'], 2, ',', ' ') ?>&nbsp;€</span>
                        <span class="loan-kpi-label"><i class="bi bi-calendar3"></i> Mensualité</span>
                    </div>
                    <div class="loan-kpi">
                        <span class="loan-kpi-value"><?= number_format($sim['total_cost'], 2, ',', ' ') ?>&nbsp;€</span>
                        <span class="loan-kpi-label"><i class="bi bi-receipt"></i> Coût total</span>
                    </div>
                    <div class="loan-kpi loan-kpi--danger">
                        <span class="loan-kpi-value"><?= number_format($sim['total_interest'], 2, ',', ' ') ?>&nbsp;€</span>
                        <span class="loan-kpi-label"><i class="bi bi-percent"></i> Intérêts totaux</span>
                    </div>
                    <div class="loan-kpi">
                        <span class="loan-kpi-value"><?= number_format($sim['amount'], 2, ',', ' ') ?>&nbsp;€</span>
                        <span class="loan-kpi-label"><i class="bi bi-bank"></i> Capital emprunté</span>
                    </div>
                </div>

                <!-- Barre capital / intérêts -->
                <?php
                    $pctInterest  = $sim['total_cost'] > 0
                        ? round($sim['total_interest'] / $sim['total_cost'] * 100, 1)
                        : 0;
                    $pctPrincipal = 100 - $pctInterest;
                ?>
                <div class="loan-breakdown">
                    <div class="loan-breakdown-labels">
                        <span class="loan-breakdown-principal">
                            <span class="loan-breakdown-dot loan-breakdown-dot--primary"></span>
                            Capital remboursé&nbsp;: <strong><?= $pctPrincipal ?>&nbsp;%</strong>
                        </span>
                        <span class="loan-breakdown-interest">
                            <span class="loan-breakdown-dot loan-breakdown-dot--danger"></span>
                            Intérêts&nbsp;: <strong><?= $pctInterest ?>&nbsp;%</strong>
                        </span>
                    </div>
                    <div class="loan-breakdown-bar">
                        <div class="loan-breakdown-fill loan-breakdown-fill--principal"
                             style="width:<?= $pctPrincipal ?>%"></div>
                        <div class="loan-breakdown-fill loan-breakdown-fill--interest"
                             style="width:<?= $pctInterest ?>%"></div>
                    </div>
                </div>

                <!-- Tableau d'amortissement (accordéon) -->
                <details class="loan-details">
                    <summary class="loan-details-summary">
                        <i class="bi bi-table"></i>
                        Tableau d'amortissement
                        <span class="badge badge-secondary"><?= count($sim['amortization']) ?> échéances</span>
                        <i class="bi bi-chevron-down loan-details-chevron"></i>
                    </summary>
                    <div class="loan-details-body">
                        <table class="table loan-amort-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Mensualité</th>
                                    <th>Capital</th>
                                    <th>Intérêts</th>
                                    <th>Restant dû</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sim['amortization'] as $row): ?>
                                <tr>
                                    <td class="loan-amort-month"><?= (int) $row['month'] ?></td>
                                    <td><?= number_format($row['payment'],   2, ',', ' ') ?> €</td>
                                    <td class="text-success"><?= number_format($row['principal'], 2, ',', ' ') ?> €</td>
                                    <td class="text-danger"><?= number_format($row['interest'],  2, ',', ' ') ?> €</td>
                                    <td class="text-muted"><?= number_format($row['remaining'],  2, ',', ' ') ?> €</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>

            </div>
        </div>
        <?php else: ?>

        <!-- État vide -->
        <div class="loan-empty">
            <div class="loan-empty-icon"><i class="bi bi-calculator"></i></div>
            <h3>Lancez votre simulation</h3>
            <p>Sélectionnez un type de crédit, saisissez le montant et la durée, puis cliquez sur <em>Calculer les mensualités</em>.</p>
        </div>

        <?php endif; ?>

        <!-- Historique -->
        <?php if (!empty($history)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-clock-history"></i> Mes dernières simulations</h3>
            </div>
            <div class="card-body loan-history-body">
                <table class="table loan-history-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Durée</th>
                            <th>Taux</th>
                            <th>Mensualité</th>
                            <th>Coût total</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h):
                            $hType  = $types[$h['loan_type']] ?? null;
                            $hLabel = $hType ? $hType['label'] : e($h['loan_type']);
                            $hIcon  = $hType ? $hType['icon'] : 'bi-cash';
                        ?>
                        <tr>
                            <td>
                                <span class="loan-history-type">
                                    <i class="bi <?= e($hIcon) ?>"></i> <?= e($hLabel) ?>
                                </span>
                            </td>
                            <td><?= number_format((float) $h['amount'], 2, ',', ' ') ?> €</td>
                            <td><?= (int) $h['months'] ?> mois</td>
                            <td><?= number_format((float) $h['annual_rate'], 2, ',', ' ') ?> %</td>
                            <td><strong class="text-primary"><?= number_format((float) $h['monthly_payment'], 2, ',', ' ') ?> €</strong></td>
                            <td><?= number_format((float) $h['total_cost'], 2, ',', ' ') ?> €</td>
                            <td class="text-muted loan-history-date">
                                <?= (new \DateTime($h['created_at']))->format('d/m/Y') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.loan-main -->

</div><!-- /.loan-layout -->

<script>
const LOAN_TYPES = <?= $typesJson ?>;

function selectLoanType(key) {
    document.getElementById('loan_type').value = key;

    document.querySelectorAll('.loan-type-card').forEach(el => {
        el.classList.toggle('loan-type-card--active', el.dataset.type === key);
    });

    updateHints(key);
}

function updateHints(key) {
    const t = LOAN_TYPES[key];
    if (!t) return;

    const amountEl = document.getElementById('amount');
    const monthsEl = document.getElementById('months');

    amountEl.min = t.min_amount;
    amountEl.max = t.max_amount;
    monthsEl.min = t.min_months;
    monthsEl.max = t.max_months;

    document.getElementById('amount-hint').textContent =
        `Min. ${t.min_amount.toLocaleString('fr-FR')} € — Max. ${t.max_amount.toLocaleString('fr-FR')} €`;
    document.getElementById('months-hint').textContent =
        `De ${t.min_months} à ${t.max_months} mois`;
    document.getElementById('rate-info-text').textContent =
        `Taux fixe ${t.rate.toLocaleString('fr-FR', {minimumFractionDigits: 2})} %/an — ${t.description}`;
}

(function () {
    const currentType = document.getElementById('loan_type').value
        || Object.keys(LOAN_TYPES)[0];
    selectLoanType(currentType);

    // Scroll vers le résultat si présent
    const result = document.getElementById('result-card');
    if (result) {
        setTimeout(() => result.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 150);
    }

    // Chevron accordéon
    document.querySelectorAll('.loan-details').forEach(d => {
        d.addEventListener('toggle', () => {
            d.querySelector('.loan-details-chevron')
             .classList.toggle('loan-details-chevron--open', d.open);
        });
    });
})();
</script>
