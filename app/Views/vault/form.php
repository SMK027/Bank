<?php
/** @var array  $account */
/** @var float  $balance */
/** @var bool   $isModerator */
/** @var bool   $isOwner */
/** @var array  $incomeCategories  clé => emoji */
/** @var array  $expenseCategories clé => emoji */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-safe2"></i> <?= e($account['name']) ?></h1>
        <p class="page-description">Coffre d'entreprise — Opérations de caisse</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/accounts/<?= (int) $account['id'] ?>" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Retour au compte
        </a>
    </div>
</div>

<!-- Solde actuel -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:1.5rem;">
    <div class="stat-card" style="border-left:4px solid <?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
        <div class="stat-value <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= fmt_amount_smart($balance) ?>
        </div>
        <div class="stat-label">Solde en caisse (<?= e($account['currency']) ?>)</div>
    </div>
</div>

<div style="max-width:580px;margin:0 auto;">
    <div class="card">
        <div class="card-header">
            <h3 style="margin:0;"><i class="bi bi-cash-stack"></i> Nouvelle opération de caisse</h3>
        </div>
        <div class="card-body">

            <?php if (!empty($account['frozen'])): ?>
            <div class="alert alert-frozen" style="margin-bottom:1rem;">
                <i class="bi bi-snow"></i>
                <strong>Coffre gelé.</strong> Aucune opération n'est possible.
            </div>
            <?php else: ?>

            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/vault" id="vault-form">
                <?= csrf_field() ?>

                <!-- Type d'opération -->
                <div class="form-group">
                    <label class="form-label">Type d'opération</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                        <label id="vault-income-label"
                               style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;
                                      padding:1rem 0.75rem;border:2px solid var(--primary);
                                      border-radius:var(--border-radius-lg);cursor:pointer;
                                      background:rgba(67,97,238,0.06);transition:all .18s;">
                            <input type="radio" name="operation_type" value="income"
                                   id="vault-income" checked style="display:none;">
                            <i class="bi bi-arrow-down-circle-fill"
                               style="font-size:1.75rem;color:var(--success);"></i>
                            <span style="font-weight:700;font-size:0.95rem;color:var(--success);">
                                Encaissement
                            </span>
                            <span style="font-size:0.78rem;color:var(--gray);text-align:center;">
                                Réception d'espèces
                            </span>
                        </label>
                        <label id="vault-expense-label"
                               style="display:flex;flex-direction:column;align-items:center;gap:0.4rem;
                                      padding:1rem 0.75rem;border:2px solid var(--gray-light);
                                      border-radius:var(--border-radius-lg);cursor:pointer;
                                      transition:all .18s;">
                            <input type="radio" name="operation_type" value="expense"
                                   id="vault-expense" style="display:none;">
                            <i class="bi bi-arrow-up-circle-fill"
                               style="font-size:1.75rem;color:var(--danger);"></i>
                            <span style="font-weight:700;font-size:0.95rem;color:var(--danger);">
                                Décaissement
                            </span>
                            <span style="font-size:0.78rem;color:var(--gray);text-align:center;">
                                Sortie d'espèces
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Montant -->
                <div class="form-group">
                    <label for="vault-amount" class="form-label">
                        Montant (<?= e($account['currency']) ?>)
                    </label>
                    <input type="number" id="vault-amount" name="amount"
                           class="form-control" inputmode="decimal"
                           min="0.01" step="0.01" placeholder="0.00" required>
                    <!-- Indicateur solde disponible pour les décaissements -->
                    <span id="vault-balance-hint" class="form-hint" style="display:none;">
                        Solde disponible : <strong><?= fmt_amount_smart($balance) ?> <?= e($account['currency']) ?></strong>
                    </span>
                </div>

                <!-- Catégorie -->
                <div class="form-group">
                    <label for="vault-category" class="form-label">Catégorie</label>
                    <select id="vault-category" name="category" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <!-- Remplie dynamiquement par JS selon le type -->
                    </select>
                </div>

                <!-- Tiers (optionnel) -->
                <div class="form-group">
                    <label for="vault-tiers" class="form-label">
                        Tiers
                        <span class="text-muted" style="font-weight:400;font-size:0.85em;">(optionnel)</span>
                    </label>
                    <input type="text" id="vault-tiers" name="tiers"
                           class="form-control" maxlength="150"
                           placeholder="Nom du client, fournisseur…">
                </div>

                <!-- Commentaire (optionnel) -->
                <div class="form-group">
                    <label for="vault-comment" class="form-label">
                        Commentaire
                        <span class="text-muted" style="font-weight:400;font-size:0.85em;">(optionnel)</span>
                    </label>
                    <input type="text" id="vault-comment" name="comment"
                           class="form-control" maxlength="255"
                           placeholder="Référence, libellé…">
                </div>

                <!-- Date d'opération (optionnelle) -->
                <div class="form-group">
                    <label for="vault-date" class="form-label">
                        <i class="bi bi-clock"></i> Date de l'opération
                        <span class="text-muted" style="font-weight:400;font-size:0.85em;">
                            (optionnel — maintenant si vide<?= $isModerator ? ', antidatage autorisé' : '' ?>)
                        </span>
                    </label>
                    <input type="text" id="vault-date" name="operation_date"
                           class="form-control flatpickr-input"
                           placeholder="jj/mm/aaaa hh:mm">
                </div>

                <!-- Bouton submit -->
                <button type="submit" id="vault-submit" class="btn btn-primary btn-block" style="margin-top:0.5rem;">
                    <i class="bi bi-check-lg" id="vault-submit-icon"></i>
                    <span id="vault-submit-label">Enregistrer l'encaissement</span>
                </button>
            </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var incomeCategories = <?= json_encode(
        array_map(fn($emoji, $label) => ['value' => $label, 'label' => $emoji . ' ' . $label],
            array_values($incomeCategories), array_keys($incomeCategories)),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
    ) ?>;
    var expenseCategories = <?= json_encode(
        array_map(fn($emoji, $label) => ['value' => $label, 'label' => $emoji . ' ' . $label],
            array_values($expenseCategories), array_keys($expenseCategories)),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
    ) ?>;

    var incomeRadio   = document.getElementById('vault-income');
    var expenseRadio  = document.getElementById('vault-expense');
    var incomeLabel   = document.getElementById('vault-income-label');
    var expenseLabel  = document.getElementById('vault-expense-label');
    var categoryEl    = document.getElementById('vault-category');
    var submitIcon    = document.getElementById('vault-submit-icon');
    var submitLabel   = document.getElementById('vault-submit-label');
    var balanceHint   = document.getElementById('vault-balance-hint');

    function populateCategories(cats) {
        categoryEl.innerHTML = '<option value="">— Sélectionner —</option>';
        cats.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.value;
            opt.textContent = c.label;
            categoryEl.appendChild(opt);
        });
    }

    function applyType(isIncome) {
        if (isIncome) {
            incomeLabel.style.borderColor  = 'var(--success, #06d6a0)';
            incomeLabel.style.background   = 'rgba(6,214,160,0.07)';
            expenseLabel.style.borderColor = 'var(--gray-light)';
            expenseLabel.style.background  = '';
            submitIcon.className  = 'bi bi-arrow-down-circle';
            submitLabel.textContent = "Enregistrer l'encaissement";
            balanceHint.style.display = 'none';
            populateCategories(incomeCategories);
        } else {
            expenseLabel.style.borderColor = 'var(--danger, #ef476f)';
            expenseLabel.style.background  = 'rgba(239,71,111,0.06)';
            incomeLabel.style.borderColor  = 'var(--gray-light)';
            incomeLabel.style.background   = '';
            submitIcon.className  = 'bi bi-arrow-up-circle';
            submitLabel.textContent = 'Enregistrer le décaissement';
            balanceHint.style.display = '';
            populateCategories(expenseCategories);
        }
    }

    // Init
    populateCategories(incomeCategories);

    incomeRadio.addEventListener('change', function () { if (this.checked) applyType(true); });
    expenseRadio.addEventListener('change', function () { if (this.checked) applyType(false); });

    // Clic sur les labels card
    incomeLabel.addEventListener('click', function () {
        incomeRadio.checked = true;
        applyType(true);
    });
    expenseLabel.addEventListener('click', function () {
        expenseRadio.checked = true;
        applyType(false);
    });

    // Flatpickr sur le champ date
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#vault-date', {
            locale: 'fr',
            enableTime: true,
            dateFormat: 'd/m/Y H:i',
            time_24hr: true,
            maxDate: <?= $isModerator ? 'null' : 'new Date()' ?>,
        });
    }
})();
</script>
