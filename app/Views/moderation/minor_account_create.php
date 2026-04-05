<div class="page-header">
    <div>
        <h1><i class="bi bi-person-plus"></i> Nouveau compte mineur</h1>
        <p class="page-description">Créer un compte bancaire pour un utilisateur mineur et désigner ses responsables légaux</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Tutelles légales
        </a>
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-bank"></i> Comptes
        </a>
    </div>
</div>

<?php if (empty($minorUsers)): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;gap:0.75rem;">
    <i class="bi bi-exclamation-triangle" style="font-size:1.3rem;"></i>
    <div>
        <strong>Aucun utilisateur mineur disponible.</strong><br>
        Pour créer un compte mineur, un utilisateur avec une date de naissance indiquant moins de 18 ans doit exister.
        Vérifiez les profils utilisateurs dans la <a href="/moderation/users">gestion des utilisateurs</a>.
    </div>
</div>
<?php else: ?>

<div class="alert" style="background:rgba(var(--warning-rgb,245,158,11),0.1);border-left:4px solid var(--warning,#f59e0b);display:flex;align-items:flex-start;gap:0.75rem;padding:1rem 1.2rem;">
    <i class="bi bi-shield-lock" style="font-size:1.3rem;color:var(--warning,#f59e0b);flex-shrink:0;"></i>
    <div style="font-size:0.88rem;">
        <strong>Compte à supervision légale obligatoire.</strong><br>
        Un compte mineur est soumis à la tutelle d'un ou deux adultes désignés comme responsables légaux.
        Ces personnes auront automatiquement <strong>procuration sur tous les comptes du mineur</strong> tant qu'il n'est pas majeur.
        À ses 18 ans, toutes les procurations expirent et doivent être remises en place manuellement.
    </div>
</div>

<div class="card" style="max-width:620px;margin:1.5rem auto 0;">
    <div class="card-body">
        <form method="POST" action="/moderation/minor-accounts" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-person-badge"></i> Utilisateur mineur
            </h3>

            <div class="form-group">
                <label for="minor_user_id_search" class="form-label">
                    Sélectionner le mineur <span style="color:var(--danger)">*</span>
                </label>
                <input type="hidden" id="minor_user_id" name="minor_user_id" value="">
                <div class="ac-wrap" style="position:relative;">
                    <input type="text" id="minor_user_id_search" class="form-control"
                           placeholder="Rechercher par nom d'utilisateur…" autocomplete="off">
                    <div id="minor_user_id_results" class="ac-results" style="display:none;position:absolute;z-index:200;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                    <div id="minor_user_id_selected" style="display:none;align-items:center;gap:0.4rem;margin-top:0.35rem;background:rgba(59,130,246,0.08);border-radius:6px;padding:0.3rem 0.7rem;font-size:0.83rem;">
                        <i class="bi bi-person-badge" style="color:#3b82f6;"></i>
                        <span id="minor_user_id_label"></span>
                        <button type="button" onclick="clearUserAc('minor_user_id')" style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;color:var(--text-muted);font-size:1rem;line-height:1;margin-left:auto;">×</button>
                    </div>
                </div>
                <div style="font-size:0.76rem;color:var(--text-muted);margin-top:4px;">
                    Seuls les utilisateurs mineurs (moins de 18 ans) apparaissent dans les résultats.
                </div>
            </div>

            <hr style="margin:1.2rem 0;border-color:var(--border-color);">
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-wallet2"></i> Détails du compte
            </h3>

            <div class="form-group">
                <label for="name" class="form-label">
                    Nom du compte <span style="color:var(--danger)">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Ex : Compte mineur de Léa" maxlength="255" required autofocus>
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

            <hr style="margin:1.2rem 0;border-color:var(--border-color);">
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-people"></i> Responsables légaux
            </h3>

            <?php if (empty($adultUsers)): ?>
            <div class="alert alert-warning" style="font-size:0.88rem;">
                <i class="bi bi-exclamation-triangle"></i>
                Aucun utilisateur adulte disponible pour être désigné responsable légal.
            </div>
            <?php else: ?>

            <div class="form-group">
                <label for="guardian_1_id_search" class="form-label">
                    Responsable légal principal <span style="color:var(--danger)">*</span>
                </label>
                <input type="hidden" id="guardian_1_id" name="guardian_1_id" value="">
                <div class="ac-wrap" style="position:relative;">
                    <input type="text" id="guardian_1_id_search" class="form-control"
                           placeholder="Rechercher un adulte…" autocomplete="off">
                    <div id="guardian_1_id_results" class="ac-results" style="display:none;position:absolute;z-index:200;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                    <div id="guardian_1_id_selected" style="display:none;align-items:center;gap:0.4rem;margin-top:0.35rem;background:rgba(59,130,246,0.08);border-radius:6px;padding:0.3rem 0.7rem;font-size:0.83rem;">
                        <i class="bi bi-person-check" style="color:#3b82f6;"></i>
                        <span id="guardian_1_id_label"></span>
                        <button type="button" onclick="clearUserAc('guardian_1_id')" style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;color:var(--text-muted);font-size:1rem;line-height:1;margin-left:auto;">×</button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="guardian_2_id_search" class="form-label">
                    Second responsable légal
                    <span style="color:var(--text-muted);font-size:0.8rem;">(facultatif — max 2)</span>
                </label>
                <input type="hidden" id="guardian_2_id" name="guardian_2_id" value="">
                <div class="ac-wrap" style="position:relative;">
                    <input type="text" id="guardian_2_id_search" class="form-control"
                           placeholder="Rechercher un adulte… (laisser vide si aucun)" autocomplete="off">
                    <div id="guardian_2_id_results" class="ac-results" style="display:none;position:absolute;z-index:200;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                    <div id="guardian_2_id_selected" style="display:none;align-items:center;gap:0.4rem;margin-top:0.35rem;background:rgba(59,130,246,0.08);border-radius:6px;padding:0.3rem 0.7rem;font-size:0.83rem;">
                        <i class="bi bi-person-check" style="color:#3b82f6;"></i>
                        <span id="guardian_2_id_label"></span>
                        <button type="button" onclick="clearUserAc('guardian_2_id')" style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;color:var(--text-muted);font-size:1rem;line-height:1;margin-left:auto;">×</button>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <div class="form-group" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary btn-block"
                    <?= empty($adultUsers) ? 'disabled' : '' ?>>
                    <i class="bi bi-check-lg"></i> Créer le compte mineur
                </button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<script>
var USER_SEARCH_URL = '/moderation/users/search';
var _acTimers = {};

function setupUserAc(prefix, type) {
    var input   = document.getElementById(prefix + '_search');
    var results = document.getElementById(prefix + '_results');
    if (!input || !results) return;

    input.addEventListener('input', function() {
        clearTimeout(_acTimers[prefix]);
        var q = this.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }

        _acTimers[prefix] = setTimeout(function() {
            fetch(USER_SEARCH_URL + '?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) {
                    results.innerHTML = '<div style="padding:0.6rem 0.9rem;color:var(--text-muted);font-size:0.82rem;">Aucun résultat</div>';
                } else {
                    var html = '';
                    data.forEach(function(item) {
                        html += '<div class="uac-item" data-id="' + item.id + '" data-username="' + _escAttr(item.username) + '" data-email="' + _escAttr(item.email) + '"'
                              + ' style="padding:0.5rem 0.9rem;cursor:pointer;font-size:0.83rem;border-bottom:1px solid var(--border-color);">'
                              + '<strong>' + _esc(item.username) + '</strong>'
                              + ' <span style="color:var(--text-muted);font-size:0.78rem;">' + _esc(item.email) + '</span>'
                              + '</div>';
                    });
                    results.innerHTML = html;
                    results.querySelectorAll('.uac-item').forEach(function(el) {
                        el.addEventListener('mouseenter', function() { this.style.background = 'var(--bg-secondary)'; });
                        el.addEventListener('mouseleave', function() { this.style.background = ''; });
                        el.addEventListener('click', function() {
                            selectUserAc(prefix, this.dataset.id, this.dataset.username, this.dataset.email);
                        });
                    });
                }
                results.style.display = 'block';
            })
            .catch(function() { results.style.display = 'none'; });
        }, 280);
    });

    document.addEventListener('click', function(e) {
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
function _escAttr(s) { return String(s).replace(/"/g,'&quot;'); }

// Validation à la soumission
document.querySelector('form[action="/moderation/minor-accounts"]').addEventListener('submit', function(e) {
    if (!document.getElementById('minor_user_id').value) {
        e.preventDefault();
        alert('Veuillez sélectionner un mineur.');
        return;
    }
    if (!document.getElementById('guardian_1_id').value) {
        e.preventDefault();
        alert('Veuillez sélectionner le responsable légal principal.');
        return;
    }
});

setupUserAc('minor_user_id',  'minor');
setupUserAc('guardian_1_id',  'adult');
setupUserAc('guardian_2_id',  'adult');
</script>
