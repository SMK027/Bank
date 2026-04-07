<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-plus"></i> Nouveau prélèvement</h1>
        <p class="page-description">Mettre en place un prélèvement automatique sur un compte</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>
</div>

<div class="card" style="max-width:620px;margin:0 auto;">
    <div class="card-body">

        <form method="POST" action="/moderation/direct-debits" id="dd-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Numéro de mandat -->
            <div class="form-group">
                <label for="mandate_number" class="form-label">
                    <i class="bi bi-hash"></i> Numéro de mandat <span style="color:var(--danger)">*</span>
                </label>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <input type="text" id="mandate_number" name="mandate_number"
                           class="form-control" maxlength="35"
                           placeholder="Ex : MANDAT-2026-001" required>
                    <button type="button" onclick="generateMandate()"
                            class="btn btn-outline btn-sm" style="white-space:nowrap;">
                        <i class="bi bi-shuffle"></i> Générer
                    </button>
                </div>
                <div style="font-size:0.76rem;color:var(--text-muted);margin-top:2px;">35 caractères max (norme SEPA)</div>
            </div>

            <!-- Date d'exécution -->
            <div class="form-group">
                <label for="scheduled_at" class="form-label">
                    <i class="bi bi-calendar-event"></i> Date et heure d'exécution <span style="color:var(--danger)">*</span>
                </label>
                <input type="text" id="scheduled_at" name="scheduled_at"
                       class="form-control" required placeholder="jj/mm/aaaa hh:mm">
            </div>

            <!-- Montant -->
            <div class="form-group">
                <label for="amount" class="form-label">
                    <i class="bi bi-currency-euro"></i> Montant <span style="color:var(--danger)">*</span>
                </label>
                <input type="number" id="amount" name="amount"
                       class="form-control" min="0.01" step="0.01"
                       placeholder="0.00" required>
            </div>

            <!-- Motif -->
            <div class="form-group">
                <label for="motif" class="form-label">
                    <i class="bi bi-chat-text"></i> Motif <span style="color:var(--text-muted);font-size:0.8rem;">(facultatif)</span>
                </label>
                <input type="text" id="motif" name="motif"
                       class="form-control" maxlength="255"
                       placeholder="Ex : Abonnement mensuel, Facture EDF…">
            </div>

            <hr style="margin:1.2rem 0;border-color:var(--border-color);">

            <!-- Compte destinataire (débité) — avec recherche -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-arrow-down-circle text-danger"></i>
                    Compte destinataire <small style="color:var(--text-muted)">(compte débité)</small>
                    <span style="color:var(--danger)">*</span>
                </label>
                <div style="position:relative;">
                    <input type="text" id="to_search" class="form-control"
                           placeholder="Rechercher par nom de compte ou propriétaire…"
                           autocomplete="off">
                    <div id="to_results"
                         style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;
                                background:var(--bg-primary,#fff);border:1px solid var(--border-color);
                                border-radius:6px;max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);">
                    </div>
                </div>
                <div id="to_selected" style="display:none;margin-top:0.4rem;padding:0.4rem 0.6rem;
                     background:var(--bg-secondary);border-radius:4px;font-size:0.82rem;
                     display:flex;align-items:center;gap:0.5rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--success)"></i>
                    <span id="to_selected_label"></span>
                    <button type="button" onclick="clearToAccount()"
                            style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--danger);font-size:1rem;">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <input type="hidden" id="to_account_id" name="to_account_id" value="">
            </div>

            <!-- Compte émetteur (crédité) — avec recherche -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-arrow-up-circle text-success"></i>
                    Compte émetteur <small style="color:var(--text-muted)">(compte crédité — vide = Banque)</small>
                </label>
                <div style="position:relative;">
                    <input type="text" id="from_search" class="form-control"
                           placeholder="Laisser vide si l'origInaire est la banque…"
                           autocomplete="off">
                    <div id="from_results"
                         style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;
                                background:var(--bg-primary,#fff);border:1px solid var(--border-color);
                                border-radius:6px;max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);">
                    </div>
                </div>
                <div id="from_selected" style="display:none;margin-top:0.4rem;padding:0.4rem 0.6rem;
                     background:var(--bg-secondary);border-radius:4px;font-size:0.82rem;
                     align-items:center;gap:0.5rem;">
                    <i class="bi bi-check-circle-fill" style="color:var(--success)"></i>
                    <span id="from_selected_label"></span>
                    <button type="button" onclick="clearFromAccount()"
                            style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--danger);font-size:1rem;">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <input type="hidden" id="from_account_id" name="from_account_id" value="">
                <div style="font-size:0.76rem;color:var(--text-muted);margin-top:2px;">
                    Si aucun compte n'est sélectionné, le crédit ne sera pas enregistré (provenance : Banque).
                </div>
            </div>

            <!-- Récapitulatif -->
            <div id="dd-summary" style="display:none;margin:1rem 0;padding:0.75rem 1rem;
                 background:var(--bg-secondary);border-radius:6px;font-size:0.84rem;border-left:3px solid var(--primary);">
            </div>

            <div id="dd-error" class="alert alert-danger" style="display:none;margin-bottom:0.75rem;"></div>

            <button type="submit" id="dd-submit" class="btn btn-primary btn-block">
                <i class="bi bi-file-earmark-check"></i> Créer le prélèvement
            </button>
        </form>

    </div>
</div>

<script>
var SEARCH_URL = '/moderation/direct-debits/accounts/search';

// ── Génération mandat ──────────────────────────────────────────
function generateMandate() {
    var now  = new Date();
    var yr   = now.getFullYear();
    var mo   = String(now.getMonth() + 1).padStart(2, '0');
    var rnd  = Math.random().toString(36).substring(2, 8).toUpperCase();
    document.getElementById('mandate_number').value = 'MANDAT-' + yr + mo + '-' + rnd;
}

// ── Recherche autocomplete ────────────────────────────────────
var searchTimers = {};

function setupSearch(inputId, resultsId, hiddenId, selectedId, selectedLabelId) {
    var input   = document.getElementById(inputId);
    var results = document.getElementById(resultsId);

    input.addEventListener('input', function() {
        clearTimeout(searchTimers[inputId]);
        var q = this.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }

        searchTimers[inputId] = setTimeout(function() {
            fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) {
                    results.innerHTML = '<div style="padding:0.6rem 0.9rem;color:var(--text-muted);font-size:0.82rem;">Aucun résultat</div>';
                } else {
                    var html = '';
                    data.forEach(function(item) {
                        html += '<div class="dd-result-item" data-id="' + item.id + '" data-label="' + escAttr(item.label) + '"'
                              + ' style="padding:0.55rem 0.9rem;cursor:pointer;font-size:0.83rem;border-bottom:1px solid var(--border-color);">'
                              + '<strong>' + esc(item.name) + '</strong>'
                              + ' <span style="color:var(--text-muted)">(' + esc(item.owner) + ')</span>'
                              + ' <span style="font-size:0.76rem;color:var(--text-muted)">— ' + esc(item.currency) + '</span>'
                              + '</div>';
                    });
                    results.innerHTML = html;

                    // Hover
                    results.querySelectorAll('.dd-result-item').forEach(function(el) {
                        el.addEventListener('mouseenter', function() { this.style.background = 'var(--bg-secondary)'; });
                        el.addEventListener('mouseleave', function() { this.style.background = ''; });
                        el.addEventListener('click', function() {
                            selectAccount(inputId, resultsId, hiddenId, selectedId, selectedLabelId,
                                          this.dataset.id, this.dataset.label);
                        });
                    });
                }
                results.style.display = 'block';
            })
            .catch(function() { results.style.display = 'none'; });
        }, 300);
    });

    // Fermer si clic ailleurs
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });
}

function selectAccount(inputId, resultsId, hiddenId, selectedId, selectedLabelId, id, label) {
    document.getElementById(hiddenId).value          = id;
    document.getElementById(selectedLabelId).textContent = label;
    document.getElementById(selectedId).style.display  = 'flex';
    document.getElementById(inputId).value             = '';
    document.getElementById(resultsId).style.display   = 'none';
    updateSummary();
}

function clearToAccount() {
    document.getElementById('to_account_id').value    = '';
    document.getElementById('to_selected').style.display  = 'none';
    document.getElementById('to_selected_label').textContent = '';
    updateSummary();
}

function clearFromAccount() {
    document.getElementById('from_account_id').value   = '';
    document.getElementById('from_selected').style.display = 'none';
    document.getElementById('from_selected_label').textContent = '';
    updateSummary();
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
    return String(s).replace(/"/g, '&quot;');
}

setupSearch('to_search',   'to_results',   'to_account_id',   'to_selected',   'to_selected_label');
setupSearch('from_search', 'from_results', 'from_account_id', 'from_selected', 'from_selected_label');

// ── Récapitulatif dynamique ────────────────────────────────────
function updateSummary() {
    var amount  = parseFloat(document.getElementById('amount').value) || 0;
    var sched   = document.getElementById('scheduled_at').value;
    var toLabel = document.getElementById('to_selected_label').textContent;
    var fromLabel = document.getElementById('from_selected_label').textContent || 'Banque';
    var toId    = document.getElementById('to_account_id').value;

    var summary = document.getElementById('dd-summary');
    if (amount > 0 && sched && toId) {
        var dt = new Date(sched);
        var fmtDt = isNaN(dt) ? sched : dt.toLocaleDateString('fr-FR') + ' à '
                    + dt.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
        var amtFmt = amount.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';

        summary.innerHTML =
            '<strong>Récapitulatif :</strong><br>'
            + '&nbsp;• <b>' + esc(amtFmt) + '</b> débité de <b>' + esc(toLabel) + '</b> le <b>' + esc(fmtDt) + '</b><br>'
            + '&nbsp;• Crédit sur : <b>' + esc(fromLabel) + '</b>';
        summary.style.display = 'block';
    } else {
        summary.style.display = 'none';
    }
}

['amount', 'scheduled_at'].forEach(function(id) {
    document.getElementById(id).addEventListener('input', updateSummary);
});

// ── Validation avant envoi ─────────────────────────────────────
document.getElementById('dd-form').addEventListener('submit', function(e) {
    var err = document.getElementById('dd-error');
    err.style.display = 'none';
    var msgs = [];

    if (!document.getElementById('mandate_number').value.trim()) {
        msgs.push('Le numéro de mandat est obligatoire.');
    }
    if (!document.getElementById('scheduled_at').value) {
        msgs.push('La date d\'exécution est obligatoire.');
    }
    var amt = parseFloat(document.getElementById('amount').value);
    if (!amt || amt <= 0) {
        msgs.push('Le montant doit être strictement positif.');
    }
    if (!document.getElementById('to_account_id').value) {
        msgs.push('Le compte destinataire (débité) est obligatoire.');
    }

    if (msgs.length) {
        e.preventDefault();
        err.innerHTML = msgs.join('<br>');
        err.style.display = 'block';
        err.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
});
</script>
