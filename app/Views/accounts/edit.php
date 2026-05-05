<div class="auth-container" style="max-width:520px">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-pencil"></i> Modifier le compte</h2>

            <?php if (!empty($isGuardian)): ?>
            <div class="alert" style="background:rgba(var(--warning-rgb,245,158,11),0.1);border-left:4px solid var(--warning,#f59e0b);font-size:0.87rem;padding:0.75rem 1rem;margin-bottom:1rem;">
                <i class="bi bi-person-lock"></i>
                <strong>Mode responsable légal.</strong>
                Vous pouvez modifier le nom du compte, la devise et le seuil d'alerte de solde.
            </div>
            <?php endif; ?>

            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/edit">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="name" class="form-label">Nom du compte</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= e($account['name']) ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label" for="account_type">Type de compte</label>
                    <?php
                    use App\Models\Account as AccountModel;
                    $currentType = $account['type'] ?? 'standard';
                    ?>
                    <?php if (!empty($isInternal)): ?>
                        <select id="account_type" name="account_type" class="form-control" required>
                            <?php foreach ($accountTypes as $key => $info): ?>
                                <option value="<?= e($key) ?>"
                                        <?= $key === $currentType ? 'selected' : '' ?>
                                        data-no-overdraft="<?= $info['overdraft'] ? '0' : '1' ?>"
                                        data-has-cap="<?= !empty($info['cap']) ? '1' : '0' ?>"
                                        data-has-interest="<?= !empty($info['interest']) ? '1' : '0' ?>">
                                    <?= e($info['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">
                            <i class="bi bi-tools"></i> Compte interne de modération : le changement de type est autorisé.
                        </span>
                    <?php else: ?>
                        <input type="hidden" name="account_type" value="<?= e($currentType) ?>">
                        <div class="form-control" style="background:var(--gray-lighter);color:var(--dark);cursor:default;">
                            <?= e(AccountModel::TYPES[$currentType]['label'] ?? $currentType) ?>
                        </div>
                        <span class="form-hint"><i class="bi bi-lock-fill"></i> Le type de compte ne peut pas être modifié après création.</span>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="currency" class="form-label">Devise</label>
                        <select id="currency" name="currency" class="form-control" required>
                            <?php
                            $currencies = ['EUR' => 'EUR (€)', 'USD' => 'USD ($)', 'GBP' => 'GBP (£)', 'CHF' => 'CHF (Fr)', 'CAD' => 'CAD ($)', 'JPY' => 'JPY (¥)', 'XOF' => 'XOF (CFA)', 'MAD' => 'MAD (DH)'];
                            foreach ($currencies as $code => $label): ?>
                                <option value="<?= $code ?>" <?= $account['currency'] === $code ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (empty($isGuardian)): ?>
                    <div class="form-group" id="overdraft-group">
                        <label for="overdraft" class="form-label">Découvert autorisé</label>
                        <input type="number" id="overdraft" name="overdraft" class="form-control"
                               value="<?= e((string) ($account['overdraft'] ?? 0)) ?>" min="0" step="0.01">
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (empty($isGuardian)): ?>
                <div id="overdraft-blocked-notice" class="alert alert-warning" style="display:none;">
                    <i class="bi bi-slash-circle"></i>
                    Ce type de compte <strong>n'autorise pas le découvert</strong>.
                </div>
                <div class="form-group" id="cap-group" style="display:none;">
                    <label for="cap" class="form-label">Plafond d'épargne</label>
                    <input type="number" id="cap" name="cap" class="form-control"
                           value="<?= e((string) ($account['cap'] ?? '')) ?>"
                           min="0" step="0.01" placeholder="Ex : 50000.00">
                    <span class="form-hint">Solde maximum autorisé (0 ou vide = pas de plafond)</span>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="balance_alert_threshold" class="form-label">Seuil d'alerte de solde</label>
                    <input type="number" id="balance_alert_threshold" name="balance_alert_threshold" class="form-control"
                           value="<?= e((string) ($account['balance_alert_threshold'] ?? '')) ?>"
                           min="0" step="0.01" placeholder="Ex : 500.00">
                    <span class="form-hint">Notification envoyée lorsque le solde passe sous ce seuil. Laisser vide pour désactiver.</span>
                </div>

                <?php if (empty($isGuardian)): ?>
                <?php
                $currentInterestRatePct = $account['interest_rate'] !== null
                    ? number_format((float) $account['interest_rate'] * 100, 4, '.', '')
                    : '';
                ?>
                <div class="form-group" id="interest-rate-group" style="display:none;">
                    <label for="interest_rate" class="form-label">
                        <i class="bi bi-percent"></i> Taux d'intérêt annuel (%)
                    </label>
                    <input type="number" id="interest_rate" name="interest_rate" class="form-control"
                           value="<?= e($currentInterestRatePct) ?>"
                           step="0.0001" placeholder="Ex : 3.00"
                           <?php if (!empty($isInternal)): ?>
                           <?php elseif ($maxRate !== null): ?>
                           max="<?= htmlspecialchars(number_format($maxRate * 100, 4, '.', ''), ENT_QUOTES) ?>"
                           <?php endif; ?>>
                    <span class="form-hint" id="interest-rate-hint">
                        Taux appliqué lors du calcul annuel des intérêts.
                        <?php if (!empty($isInternal)): ?>
                            Aucune limite — compte interne de modération.
                        <?php elseif ($maxRate !== null): ?>
                            Taux maximum autorisé : <strong><?= number_format($maxRate * 100, 2, ',', ' ') ?> %</strong>.
                        <?php else: ?>
                            Aucun taux maximum configuré par la modération pour ce type.
                        <?php endif; ?>
                        Laisser vide pour ne pas percevoir d'intérêts.
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($account['deferred_debit_enabled'])): ?>
                <div class="form-group" id="deferred-debit-day-group">
                    <label for="deferred_debit_day" class="form-label">
                        <i class="bi bi-credit-card"></i> Jour de débit carte (mensuel)
                    </label>
                    <select id="deferred_debit_day" name="deferred_debit_day" class="form-control">
                        <option value="">— Non défini —</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>" <?= (int) ($account['deferred_debit_day'] ?? 0) === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="form-hint">
                        Jour du mois où tous vos encours carte seront débités.
                        Cette date sera pré-remplie automatiquement lors de vos prochaines opérations à débit différé.
                    </span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-check-lg"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                <a href="/accounts/<?= (int) $account['id'] ?>"><i class="bi bi-arrow-left"></i> Retour au compte</a>
            </p>
        </div>
    </div>
</div>
<script>
(function () {
    <?php if (empty($isGuardian)): ?>
    <?php if (!empty($isInternal)): ?>
    // Compte interne : le type est éditable, on bascule dynamiquement les sections liées.
    var MAX_RATES = <?= json_encode($maxRates ?? [], JSON_HEX_TAG) ?>;
    var typeEl    = document.getElementById('account_type');
    var odGrp     = document.getElementById('overdraft-group');
    var odNotice  = document.getElementById('overdraft-blocked-notice');
    var odInput   = document.getElementById('overdraft');
    var capGrp    = document.getElementById('cap-group');
    var rateGrp   = document.getElementById('interest-rate-group');
    var rateInput = document.getElementById('interest_rate');
    var rateHint  = document.getElementById('interest-rate-hint');

    function toggle() {
        var opt         = typeEl.options[typeEl.selectedIndex];
        var noOd        = opt.dataset.noOverdraft === '1';
        var hasCap      = opt.dataset.hasCap === '1';
        var hasInterest = opt.dataset.hasInterest === '1';

        if (odGrp)    odGrp.style.display    = noOd ? 'none' : '';
        if (odNotice) odNotice.style.display = (noOd && !hasCap) ? 'block' : 'none';
        if (capGrp)   capGrp.style.display   = hasCap ? '' : 'none';
        if (rateGrp)  rateGrp.style.display  = hasInterest ? '' : 'none';

        if (noOd && odInput) odInput.value = '0';
        if (!hasCap && document.getElementById('cap')) document.getElementById('cap').value = '';
        if (!hasInterest && rateInput) rateInput.value = '';

        if (hasInterest && rateHint) {
            rateHint.textContent = 'Aucune limite — compte interne de modération.';
            if (rateInput) {
                rateInput.removeAttribute('max');
                rateInput.setAttribute('step', 'any');
            }
        }
    }
    typeEl.addEventListener('change', toggle);
    toggle();
    <?php else: ?>
    // Le type est fixe — initialisation unique des sections liées
    var noOd        = <?= json_encode(!AccountModel::typeAllowsOverdraft($account['type'] ?? 'standard')) ?>;
    var hasCap      = <?= json_encode(AccountModel::typeHasCap($account['type'] ?? 'standard')) ?>;
    var hasInterest = <?= json_encode(AccountModel::typeHasInterest($account['type'] ?? 'standard')) ?>;

    document.getElementById('overdraft-group').style.display          = noOd ? 'none' : '';
    document.getElementById('overdraft-blocked-notice').style.display = (noOd && !hasCap) ? 'block' : 'none';
    document.getElementById('cap-group').style.display                = hasCap ? '' : 'none';
    document.getElementById('interest-rate-group').style.display      = hasInterest ? '' : 'none';
    <?php endif; ?>
    <?php endif; ?>
})();
</script>
