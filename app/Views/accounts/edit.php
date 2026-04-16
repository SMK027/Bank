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
                    <label class="form-label">Type de compte</label>
                    <input type="hidden" name="account_type" value="<?= e($account['type'] ?? 'standard') ?>">
                    <div class="form-control" style="background:var(--gray-lighter);color:var(--dark);cursor:default;">
                        <?php
                        use App\Models\Account as AccountModel;
                        echo e(AccountModel::TYPES[$account['type'] ?? 'standard']['label'] ?? $account['type']);
                        ?>
                    </div>
                    <span class="form-hint"><i class="bi bi-lock-fill"></i> Le type de compte ne peut pas être modifié après création.</span>
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
                           min="0" step="0.0001" placeholder="Ex : 3.00"
                           <?php if ($maxRate !== null): ?>
                           max="<?= htmlspecialchars(number_format($maxRate * 100, 4, '.', ''), ENT_QUOTES) ?>"
                           <?php endif; ?>>
                    <span class="form-hint" id="interest-rate-hint">
                        Taux appliqué lors du calcul annuel des intérêts.
                        <?php if ($maxRate !== null): ?>
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
    // Le type est fixe — initialisation unique des sections liées
    var noOd        = <?= json_encode(!AccountModel::typeAllowsOverdraft($account['type'] ?? 'standard')) ?>;
    var hasCap      = <?= json_encode(AccountModel::typeHasCap($account['type'] ?? 'standard')) ?>;
    var hasInterest = <?= json_encode(AccountModel::typeHasInterest($account['type'] ?? 'standard')) ?>;

    document.getElementById('overdraft-group').style.display          = noOd ? 'none' : '';
    document.getElementById('overdraft-blocked-notice').style.display = (noOd && !hasCap) ? 'block' : 'none';
    document.getElementById('cap-group').style.display                = hasCap ? '' : 'none';
    document.getElementById('interest-rate-group').style.display      = hasInterest ? '' : 'none';
    <?php endif; ?>
})();
</script>
