<?php use App\Models\Account; ?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-tools"></i> Créer un compte interne</h1>
        <p class="page-description">
            Compte de modération réservé aux tests. Vous en êtes le propriétaire et il
            <strong>ne peut pas être partagé</strong> à des utilisateurs normaux.
        </p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Comptes
        </a>
    </div>
</div>

<div class="card" style="max-width:580px;margin:0 auto;">
    <div class="card-body">
        <div class="alert alert-warning" style="margin-bottom:1.25rem;">
            <i class="bi bi-info-circle"></i>
            Les comptes internes autorisent <strong>toutes les opérations bancaires</strong>
            sans restriction de profil (utiles pour effectuer des tests : virements, prélèvements,
            mandats, prêts, etc.). Ils n'apparaissent jamais dans les recherches de partage.
        </div>

        <form method="POST" action="/moderation/internal-accounts" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--gray);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-wallet2"></i> Détails du compte
            </h3>

            <div class="form-group">
                <label for="name" class="form-label">Nom du compte <span style="color:var(--danger)">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Ex : Compte test virements" maxlength="255" required autofocus>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="account_type" class="form-label">Type de compte <span style="color:var(--danger)">*</span></label>
                    <select id="account_type" name="account_type" class="form-control" required>
                        <?php foreach ($accountTypes as $key => $info): ?>
                            <option value="<?= e($key) ?>"
                                    data-no-overdraft="<?= $info['overdraft'] ? '0' : '1' ?>"
                                    data-has-cap="<?= !empty($info['cap']) ? '1' : '0' ?>"
                                    data-has-interest="<?= !empty($info['interest']) ? '1' : '0' ?>">
                                <?= e($info['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="currency" class="form-label">Devise</label>
                    <select id="currency" name="currency" class="form-control">
                        <option value="EUR">EUR (€)</option>
                        <option value="USD">USD ($)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="CHF">CHF (Fr)</option>
                        <option value="CAD">CAD ($)</option>
                        <option value="JPY">JPY (¥)</option>
                        <option value="XOF">XOF (CFA)</option>
                        <option value="MAD">MAD (DH)</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="overdraft-group">
                <label for="overdraft" class="form-label">Découvert autorisé</label>
                <input type="number" id="overdraft" name="overdraft" class="form-control"
                       value="0" min="0" step="0.01">
            </div>

            <div id="overdraft-blocked-notice" class="alert alert-warning" style="display:none;">
                <i class="bi bi-slash-circle"></i>
                Ce type de compte <strong>n'autorise pas le découvert</strong>.
            </div>

            <div class="form-group" id="cap-group" style="display:none;">
                <label for="cap" class="form-label">Plafond d'épargne</label>
                <input type="number" id="cap" name="cap" class="form-control"
                       min="0" step="0.01" placeholder="Ex : 50000.00">
                <span class="form-hint">Solde maximum autorisé (0 ou vide = pas de plafond)</span>
            </div>

            <div class="form-group" id="interest-rate-group" style="display:none;">
                <label for="interest_rate" class="form-label">
                    <i class="bi bi-percent"></i> Taux d'intérêt annuel (%)
                </label>
                <input type="number" id="interest_rate" name="interest_rate" class="form-control"
                       min="0" step="0.0001" placeholder="Ex : 3.00">
                <span class="form-hint" id="interest-rate-hint">Laisser vide pour ne pas appliquer d'intérêts.</span>
            </div>

            <div class="form-group" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Créer le compte interne
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var MAX_RATES = <?= json_encode($maxRates, JSON_HEX_TAG) ?>;

(function () {
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

        odGrp.style.display    = noOd ? 'none' : '';
        odNotice.style.display = (noOd && !hasCap) ? 'block' : 'none';
        capGrp.style.display   = hasCap ? '' : 'none';
        rateGrp.style.display  = hasInterest ? '' : 'none';

        if (noOd) { odInput.value = '0'; }
        if (!hasCap && document.getElementById('cap')) { document.getElementById('cap').value = ''; }
        if (!hasInterest && rateInput) { rateInput.value = ''; }

        if (hasInterest && rateHint) {
            var mr = MAX_RATES[opt.value];
            if (mr !== null && mr !== undefined) {
                var pct = (parseFloat(mr) * 100).toFixed(2).replace('.', ',');
                rateHint.innerHTML = 'Taux maximum autorisé : <strong>' + pct + ' %</strong>. Laisser vide pour ne pas appliquer d\'intérêts.';
                if (rateInput) { rateInput.max = (parseFloat(mr) * 100).toFixed(4); }
            } else {
                rateHint.textContent = 'Aucun taux maximum configuré pour ce type. Laisser vide pour ne pas appliquer d\'intérêts.';
                if (rateInput) { rateInput.removeAttribute('max'); }
            }
        }
    }

    typeEl.addEventListener('change', toggle);
    toggle();
})();
</script>
