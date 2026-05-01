<?php
/** @var array       $types */
/** @var array|null  $account */
/** @var string      $csrfToken */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-coin"></i> Octroyer un crédit</h1>
        <p class="page-description">Saisir les conditions du crédit pour un utilisateur</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation/loans" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Liste des crédits
        </a>
    </div>
</div>

<div class="card" style="max-width:680px;margin:0 auto;">
    <div class="card-body">
        <form method="POST" action="/moderation/loans" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- ── Compte cible ──────────────────────────────────────────── -->
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--gray);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-wallet2"></i> Compte bénéficiaire
            </h3>

            <div class="form-group">
                <label for="account_search" class="form-label">
                    Compte <span style="color:var(--danger)">*</span>
                </label>
                <input type="hidden" id="account_id" name="account_id"
                       value="<?= $account ? (int)$account['id'] : '' ?>">
                <div class="ac-wrap" style="position:relative;">
                    <input type="text" id="account_search" class="form-control"
                           placeholder="Rechercher par utilisateur ou nom de compte…"
                           autocomplete="off" autofocus
                           value="<?= $account ? htmlspecialchars($account['name']) : '' ?>">
                    <div id="account_results"
                         style="display:none;position:absolute;z-index:200;width:100%;
                                background:var(--white);border:1px solid var(--gray-light);
                                border-radius:4px;max-height:220px;overflow-y:auto;
                                box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                    <div id="account_selected"
                         style="<?= $account ? 'display:flex' : 'display:none' ?>;align-items:center;gap:0.4rem;margin-top:0.35rem;
                                background:rgba(67,97,238,0.08);border-radius:6px;
                                padding:0.3rem 0.7rem;font-size:0.83rem;">
                        <i class="bi bi-wallet2" style="color:var(--primary)"></i>
                        <span id="account_label"><?= $account ? htmlspecialchars($account['name']) : '' ?></span>
                        <button type="button" onclick="clearAccountAc()"
                                style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;
                                       color:var(--gray);font-size:1rem;line-height:1;margin-left:auto;">×</button>
                    </div>
                </div>
            </div>

            <hr style="margin:1.25rem 0;border-color:var(--gray-light);">

            <!-- ── Conditions du crédit ─────────────────────────────────── -->
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--gray);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-cash-coin"></i> Conditions
            </h3>

            <div class="form-group">
                <label class="form-label">Type de crédit <span style="color:var(--danger)">*</span></label>
                <div class="loan-types-grid" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:0.5rem;margin-top:0.5rem;">
                    <?php foreach ($types as $key => $type): ?>
                    <label class="loan-type-card" style="cursor:pointer;margin:0;">
                        <input type="radio" name="loan_type" value="<?= htmlspecialchars($key) ?>"
                               style="display:none" required
                               onchange="document.querySelectorAll('.loan-type-card').forEach(c=>c.classList.remove('loan-type-card--active'));this.closest('.loan-type-card').classList.add('loan-type-card--active');updateRate(<?= $type['rate'] ?>)">
                        <div class="loan-type-icon"><i class="bi <?= htmlspecialchars($type['icon']) ?>"></i></div>
                        <div class="loan-type-label"><?= htmlspecialchars($type['label']) ?></div>
                        <div class="loan-type-rate"><?= $type['rate'] ?> %</div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label for="amount" class="form-label">Montant (€) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="amount" name="amount" class="form-control"
                           min="100" step="0.01" placeholder="Ex : 5000" required>
                </div>
                <div class="form-group">
                    <label for="annual_rate" class="form-label">Taux annuel (%) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="annual_rate" name="annual_rate" class="form-control"
                           min="0" max="100" step="0.01" placeholder="Ex : 5.50" required>
                    <small class="form-text" style="color:var(--text-muted)">Modifiable après octroi pour appliquer une réduction commerciale.</small>
                </div>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Notes internes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"
                          placeholder="Conditions particulières, justification de l'octroi…"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
                <a href="/moderation/loans" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
                    <i class="bi bi-check-circle"></i> Octroyer le crédit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Autocomplete compte ──────────────────────────────────────────────────────
const accountInput    = document.getElementById('account_search');
const accountHidden   = document.getElementById('account_id');
const accountResults  = document.getElementById('account_results');
const accountSelected = document.getElementById('account_selected');
const accountLabel    = document.getElementById('account_label');
let   acTimer;

accountInput.addEventListener('input', function() {
    clearTimeout(acTimer);
    const q = this.value.trim();
    if (q.length < 2) { accountResults.style.display = 'none'; return; }
    acTimer = setTimeout(() => {
        fetch('/api/accounts/search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                accountResults.innerHTML = '';
                if (!data.length) {
                    accountResults.innerHTML = '<div style="padding:0.5rem 0.75rem;font-size:0.82rem;color:var(--text-muted)">Aucun résultat</div>';
                }
                data.forEach(item => {
                    const el = document.createElement('div');
                    el.style.cssText = 'padding:0.45rem 0.75rem;cursor:pointer;font-size:0.85rem;border-bottom:1px solid var(--gray-light)';
                    el.innerHTML = `<strong>${escHtml(item.username)}</strong> — ${escHtml(item.name)} <span style="color:var(--text-muted);font-size:0.78rem">(${escHtml(item.type || '')})</span>`;
                    el.addEventListener('mouseenter', () => el.style.background = 'rgba(67,97,238,0.06)');
                    el.addEventListener('mouseleave', () => el.style.background = '');
                    el.addEventListener('click', () => selectAccount(item));
                    accountResults.appendChild(el);
                });
                accountResults.style.display = 'block';
            });
    }, 250);
});

function selectAccount(item) {
    accountHidden.value    = item.id;
    accountLabel.textContent = item.name + ' (' + item.username + ')';
    accountInput.value     = item.name;
    accountResults.style.display = 'none';
    accountSelected.style.display = 'flex';
    checkSubmit();
}

function clearAccountAc() {
    accountHidden.value   = '';
    accountInput.value    = '';
    accountSelected.style.display = 'none';
    checkSubmit();
}

document.addEventListener('click', e => {
    if (!accountInput.contains(e.target) && !accountResults.contains(e.target)) {
        accountResults.style.display = 'none';
    }
});

// ── Taux par défaut selon le type sélectionné ────────────────────────────────
function updateRate(rate) {
    document.getElementById('annual_rate').value = rate.toFixed(2);
    checkSubmit();
}

// ── Activation du bouton submit ──────────────────────────────────────────────
function checkSubmit() {
    const hasAccount = accountHidden.value !== '';
    const hasType    = document.querySelector('input[name="loan_type"]:checked') !== null;
    const hasAmount  = parseFloat(document.getElementById('amount').value) > 0;
    document.getElementById('submit-btn').disabled = !(hasAccount && hasType && hasAmount);
}

document.getElementById('amount').addEventListener('input', checkSubmit);
document.getElementById('annual_rate').addEventListener('input', checkSubmit);

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
