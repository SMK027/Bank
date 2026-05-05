<?php use App\Models\Account; ?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-plus-circle"></i> Créer un compte bancaire</h1>
        <p class="page-description">Créer un compte pour n'importe quel utilisateur, sans restriction de profil</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Comptes
        </a>
    </div>
</div>

<div class="card" style="max-width:580px;margin:0 auto;">
    <div class="card-body">
        <form method="POST" action="/moderation/accounts" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- ── Utilisateur cible ───────────────────────────── -->
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--gray);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-person-badge"></i> Titulaire du compte
            </h3>

            <div class="form-group">
                <label for="target_user_id_search" class="form-label">
                    Utilisateur <span style="color:var(--danger)">*</span>
                </label>
                <input type="hidden" id="target_user_id" name="target_user_id" value="">
                <div class="ac-wrap" style="position:relative;">
                    <input type="text" id="target_user_id_search" class="form-control"
                           placeholder="Rechercher par nom d'utilisateur…" autocomplete="off" autofocus>
                    <div id="target_user_id_results" class="ac-results"
                         style="display:none;position:absolute;z-index:200;width:100%;
                                background:var(--white);border:1px solid var(--gray-light);
                                border-radius:4px;max-height:200px;overflow-y:auto;
                                box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                    <div id="target_user_id_selected"
                         style="display:none;align-items:center;gap:0.4rem;margin-top:0.35rem;
                                background:rgba(67,97,238,0.08);border-radius:6px;
                                padding:0.3rem 0.7rem;font-size:0.83rem;">
                        <i class="bi bi-person-check" style="color:var(--primary);"></i>
                        <span id="target_user_id_label"></span>
                        <button type="button" onclick="clearUserAc('target_user_id')"
                                style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;
                                       color:var(--gray);font-size:1rem;line-height:1;margin-left:auto;">×</button>
                    </div>
                </div>
            </div>

            <hr style="margin:1.25rem 0;border-color:var(--gray-light);">

            <!-- ── Détails du compte ──────────────────────────── -->
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--gray);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-wallet2"></i> Détails du compte
            </h3>

            <div class="form-group">
                <label for="name" class="form-label">Nom du compte <span style="color:var(--danger)">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Ex : Compte courant principal" maxlength="255" required>
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
                       step="0.0001" placeholder="Ex : 3.00"
                       id="interest_rate_input">
                <span class="form-hint" id="interest-rate-hint">Laisser vide pour ne pas appliquer d'intérêts.</span>
            </div>

            <div class="form-group" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Créer le compte
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var USER_SEARCH_URL = '/moderation/users/search';
var _acTimers = {};
var MAX_RATES  = <?= json_encode($maxRates, JSON_HEX_TAG) ?>;

/* ── Autocomplete utilisateur ─────────────────────────────── */
function setupUserAc(prefix) {
    var input   = document.getElementById(prefix + '_search');
    var results = document.getElementById(prefix + '_results');
    if (!input || !results) return;

    input.addEventListener('input', function () {
        clearTimeout(_acTimers[prefix]);
        var q = this.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }

        _acTimers[prefix] = setTimeout(function () {
            fetch(USER_SEARCH_URL + '?q=' + encodeURIComponent(q) + '&type=all', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.length) {
                    results.innerHTML = '<div style="padding:0.6rem 0.9rem;color:var(--gray);font-size:0.82rem;">Aucun résultat</div>';
                } else {
                    var html = '';
                    data.forEach(function (item) {
                        html += '<div class="uac-item" data-id="' + item.id + '" data-username="' + _escAttr(item.username) + '" data-email="' + _escAttr(item.email) + '"'
                              + ' style="padding:0.5rem 0.9rem;cursor:pointer;font-size:0.83rem;border-bottom:1px solid var(--gray-light);">'
                              + '<strong>' + _esc(item.username) + '</strong>'
                              + ' <span style="color:var(--gray);font-size:0.78rem;">' + _esc(item.email) + '</span>'
                              + '</div>';
                    });
                    results.innerHTML = html;
                    results.querySelectorAll('.uac-item').forEach(function (el) {
                        el.addEventListener('mouseenter', function () { this.style.background = 'var(--light)'; });
                        el.addEventListener('mouseleave', function () { this.style.background = ''; });
                        el.addEventListener('click', function () {
                            selectUserAc(prefix, this.dataset.id, this.dataset.username, this.dataset.email);
                        });
                    });
                }
                results.style.display = 'block';
            })
            .catch(function () { results.style.display = 'none'; });
        }, 280);
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });
}

function selectUserAc(prefix, id, username, email) {
    document.getElementById(prefix).value                       = id;
    document.getElementById(prefix + '_label').textContent      = username + ' — ' + email;
    document.getElementById(prefix + '_selected').style.display = 'flex';
    document.getElementById(prefix + '_search').value           = '';
    document.getElementById(prefix + '_results').style.display  = 'none';
}

function clearUserAc(prefix) {
    document.getElementById(prefix).value                       = '';
    document.getElementById(prefix + '_label').textContent      = '';
    document.getElementById(prefix + '_selected').style.display = 'none';
}

function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function _escAttr(s) { return String(s).replace(/"/g, '&quot;'); }

/* ── Toggle champs selon le type sélectionné ─────────────── */
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
        var opt        = typeEl.options[typeEl.selectedIndex];
        var noOd       = opt.dataset.noOverdraft === '1';
        var hasCap     = opt.dataset.hasCap === '1';
        var hasInterest = opt.dataset.hasInterest === '1';

        odGrp.style.display    = noOd ? 'none' : '';
        odNotice.style.display = (noOd && !hasCap) ? 'block' : 'none';
        capGrp.style.display   = hasCap ? '' : 'none';
        rateGrp.style.display  = hasInterest ? '' : 'none';

        if (noOd) { odInput.value = '0'; }
        if (!hasCap && document.getElementById('cap')) { document.getElementById('cap').value = ''; }
        if (!hasInterest && rateInput) { rateInput.value = ''; }

        // Afficher le taux max autorisé dans le hint
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

/* ── Validation à la soumission ──────────────────────────── */
document.querySelector('form[action="/moderation/accounts"]').addEventListener('submit', function (e) {
    if (!document.getElementById('target_user_id').value) {
        e.preventDefault();
        alert('Veuillez sélectionner un utilisateur titulaire du compte.');
    }
});

setupUserAc('target_user_id');
</script>
