<div class="auth-container" style="max-width:560px">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-arrow-left-right"></i> Virement entre comptes</h2>

            <?php
            // Fusionner tous les comptes accessibles pour le destinataire
            $allAccounts = array_merge($ownAccounts, $sharedAccounts);
            ?>

            <?php if (count($allAccounts) < 2): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Vous devez avoir accès à au moins deux comptes pour effectuer un virement.
                </div>
                <p class="text-center text-muted text-small mt-2">
                    <a href="/dashboard"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a>
                </p>
            <?php else: ?>

            <form method="POST" action="/transfers/create" id="transfer-form">
                <?= csrf_field() ?>

                <!-- Compte émetteur -->
                <div class="form-group">
                    <label for="from_account_id" class="form-label">
                        <i class="bi bi-arrow-up-circle text-danger"></i> Compte émetteur
                    </label>
                    <select id="from_account_id" name="from_account_id" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php if (!empty($ownAccounts)): ?>
                            <optgroup label="Mes comptes">
                                <?php foreach ($ownAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>"
                                            data-balance="<?= (float) ($acc['balance'] ?? 0) ?>"
                                            data-overdraft="<?= (float) ($acc['overdraft'] ?? 0) ?>"
                                            data-no-overdraft="<?= \App\Models\Account::typeAllowsOverdraft($acc['type'] ?? 'standard') ? '0' : '1' ?>"
                                            data-currency="<?= e($acc['currency']) ?>"
                                            <?= $preselect === (int) $acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['name']) ?>
                                        (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($sharedAccounts)): ?>
                            <optgroup label="Comptes partagés">
                                <?php foreach ($sharedAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>"
                                            data-balance="<?= (float) ($acc['balance'] ?? 0) ?>"
                                            data-overdraft="<?= (float) ($acc['overdraft'] ?? 0) ?>"
                                            data-no-overdraft="<?= \App\Models\Account::typeAllowsOverdraft($acc['type'] ?? 'standard') ? '0' : '1' ?>"
                                            data-currency="<?= e($acc['currency']) ?>"
                                            <?= $preselect === (int) $acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['name']) ?>
                                        (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <!-- Solde disponible affiché dynamiquement -->
                    <div id="from-info" style="margin-top:0.4rem; font-size:0.82rem; display:none;">
                        Solde disponible : <strong id="from-balance"></strong>
                        <span id="from-overdraft-info" style="display:none"> · Découvert autorisé : <strong id="from-overdraft"></strong></span>
                    </div>
                </div>

                <!-- Compte destinataire -->
                <div class="form-group">
                    <label for="to_account_id" class="form-label">
                        <i class="bi bi-arrow-down-circle text-success"></i> Compte destinataire
                    </label>
                    <select id="to_account_id" name="to_account_id" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php if (!empty($ownAccounts)): ?>
                            <optgroup label="Mes comptes">
                                <?php foreach ($ownAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>">
                                        <?= e($acc['name']) ?>
                                        (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($sharedAccounts)): ?>
                            <optgroup label="Comptes partagés">
                                <?php foreach ($sharedAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>">
                                        <?= e($acc['name']) ?>
                                        (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Montant -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount" class="form-label">Montant</label>
                        <input type="number" id="amount" name="amount" class="form-control"
                               placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="motif" class="form-label">Motif <span class="text-muted">(optionnel)</span></label>
                        <input type="text" id="motif" name="motif" class="form-control"
                               placeholder="Ex : Remboursement loyer">
                    </div>
                </div>

                <!-- Avertissement dynamique -->
                <div id="transfer-warning" style="display:none; margin-bottom:0.75rem;">
                    <div id="transfer-warning-same" class="alert alert-warning" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Le compte émetteur et le compte destinataire doivent être différents.
                    </div>
                    <div id="transfer-warning-funds" class="alert alert-danger" style="display:none;">
                        <i class="bi bi-x-circle-fill"></i>
                        <span id="transfer-warning-funds-text"></span>
                    </div>
                </div>

                <button type="submit" id="transfer-submit" class="btn btn-primary btn-block">
                    <i class="bi bi-arrow-left-right"></i> Effectuer le virement
                </button>
            </form>

            <?php endif; ?>

            <p class="text-center text-muted text-small mt-2">
                <a href="/dashboard"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a>
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    var fromEl   = document.getElementById('from_account_id');
    var toEl     = document.getElementById('to_account_id');
    var amountEl = document.getElementById('amount');
    var fromInfo = document.getElementById('from-info');
    var fromBalEl= document.getElementById('from-balance');
    var fromOdInfo = document.getElementById('from-overdraft-info');
    var fromOdEl = document.getElementById('from-overdraft');
    var warnSame  = document.getElementById('transfer-warning-same');
    var warnFunds = document.getElementById('transfer-warning-funds');
    var warnFundsText = document.getElementById('transfer-warning-funds-text');
    var submitBtn = document.getElementById('transfer-submit');

    function fmt(n, cur) {
        return n.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '\u00a0' + (cur || '');
    }

    function getFromData() {
        var opt = fromEl.options[fromEl.selectedIndex];
        if (!opt || !opt.value) return null;
        return {
            balance:     parseFloat(opt.dataset.balance)    || 0,
            overdraft:   parseFloat(opt.dataset.overdraft)  || 0,
            noOverdraft: opt.dataset.noOverdraft === '1',
            currency:    opt.dataset.currency || ''
        };
    }

    function updateFromInfo() {
        var d = getFromData();
        if (!d) { fromInfo.style.display = 'none'; return; }
        fromInfo.style.display = 'block';
        fromBalEl.textContent  = fmt(d.balance, d.currency);
        fromBalEl.style.color  = d.balance >= 0 ? 'var(--success-dark)' : 'var(--danger)';
        if (d.overdraft > 0) {
            fromOdInfo.style.display = 'inline';
            fromOdEl.textContent = fmt(d.overdraft, d.currency);
        } else {
            fromOdInfo.style.display = 'none';
        }
    }

    function check() {
        var d       = getFromData();
        var fromId  = fromEl.value;
        var toId    = toEl.value;
        var amount  = parseFloat(amountEl.value) || 0;
        var sameAcc = fromId && toId && fromId === toId;
        var fundErr = false;

        warnSame.style.display  = 'none';
        warnFunds.style.display = 'none';

        if (sameAcc) {
            warnSame.style.display = 'block';
        }

        if (d && amount > 0) {
            var newBal  = d.balance - amount;
            var exceeds = newBal < -d.overdraft;
            if (exceeds) {
                fundErr = true;
                if (d.noOverdraft) {
                    warnFundsText.textContent = 'Impossible : ce compte ne permet pas le solde négatif. Solde disponible : ' + fmt(d.balance, d.currency) + '.';
                } else {
                    warnFundsText.textContent = 'Fonds insuffisants. Solde prévu après virement : ' + fmt(newBal, d.currency) + ' (dépassement du découvert de ' + fmt(d.overdraft, d.currency) + ').';
                }
                warnFunds.style.display = 'block';
            }
        }

        submitBtn.disabled = sameAcc || fundErr;
    }

    fromEl.addEventListener('change', function () { updateFromInfo(); check(); });
    toEl.addEventListener('change', check);
    amountEl.addEventListener('input', check);

    updateFromInfo();
    check();
})();
</script>
