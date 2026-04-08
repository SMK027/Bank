<div class="page-header">
    <h1><i class="bi bi-plus-circle"></i> Créer un mandat</h1>
    <a href="/moderation/mandates" class="btn btn-outline btn-sm">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="auth-container" style="max-width:580px">
    <div class="card">
        <div class="card-body">
            <form method="POST" action="/moderation/mandates">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="number" class="form-label">Numéro de mandat</label>
                    <input type="text" id="number" name="number" class="form-control"
                           placeholder="Ex : MAND-2026-001" required autofocus maxlength="35">
                </div>

                <!-- Toggle mandat bancaire -->
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" id="bank_mandate" name="bank_mandate" value="1" style="width:1rem;height:1rem;">
                        <span>Mandat émis par la banque <small class="text-muted">(aucun compte émetteur — seul le destinataire est débité)</small></span>
                    </label>
                </div>

                <!-- Compte émetteur (professionnel) — caché si mandat bancaire -->
                <div class="form-group" id="emitter-group" style="position:relative;">
                    <label for="emitter-search" class="form-label">Compte émetteur (professionnel — à créditer)</label>
                    <input type="text" id="emitter-search" class="form-control"
                           placeholder="Rechercher un compte pro…" autocomplete="off">
                    <input type="hidden" id="emitter_account_id" name="emitter_account_id" required>
                    <div id="emitter-suggestions"
                         style="display:none;position:absolute;z-index:400;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);top:calc(100% + 2px);left:0;"></div>
                </div>

                <!-- Compte destinataire -->
                <div class="form-group" style="position:relative;">
                    <label for="recipient-search" class="form-label">Compte destinataire (à débiter)</label>
                    <input type="text" id="recipient-search" class="form-control"
                           placeholder="Rechercher un compte…" autocomplete="off">
                    <input type="hidden" id="recipient_account_id" name="recipient_account_id" required>
                    <div id="recipient-suggestions"
                         style="display:none;position:absolute;z-index:400;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);top:calc(100% + 2px);left:0;"></div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Descriptif</label>
                    <input type="text" id="description" name="description" class="form-control"
                           placeholder="Ex : Abonnement mensuel SaaS" maxlength="255">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount" class="form-label">Montant (€)</label>
                        <input type="number" id="amount" name="amount" class="form-control"
                               min="0.01" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="type" class="form-label">Type</label>
                        <select id="type" name="type" class="form-control" required>
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="interval-group" style="display:none;">
                    <label for="interval_days" class="form-label">Intervalle de prélèvement (jours)</label>
                    <input type="number" id="interval_days" name="interval_days" class="form-control"
                           min="1" step="1" placeholder="Ex : 30">
                    <span class="form-hint">Nombre de jours entre chaque prélèvement</span>
                </div>

                <div class="form-group">
                    <label for="first_execution_at" class="form-label">
                        Date de première exécution
                        <span class="text-muted" style="font-weight:normal;font-size:0.88em;">(optionnelle — immédiate si vide)</span>
                    </label>
                    <input type="text" id="first_execution_at" name="first_execution_at"
                           class="form-control"
                           placeholder="jj/mm/aaaa hh:mm">
                    <span class="form-hint">Laissez vide pour une exécution dès le prochain traitement.</span>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-check-lg"></i> Créer le mandat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    // Toggle intervalle selon le type
    var typeEl       = document.getElementById('type');
    var intervalGrp  = document.getElementById('interval-group');
    var intervalInp  = document.getElementById('interval_days');
    function toggleInterval() {
        var show = typeEl.value === 'recurring';
        intervalGrp.style.display = show ? '' : 'none';
        if (!show) intervalInp.value = '';
    }
    typeEl.addEventListener('change', toggleInterval);
    toggleInterval();

    // Toggle mandat bancaire
    var bankCheckbox   = document.getElementById('bank_mandate');
    var emitterGroup   = document.getElementById('emitter-group');
    var emitterHidden  = document.getElementById('emitter_account_id');
    var emitterSearch  = document.getElementById('emitter-search');
    function toggleBankMandate() {
        var isBank = bankCheckbox.checked;
        emitterGroup.style.display = isBank ? 'none' : '';
        emitterHidden.required = !isBank;
        if (isBank) {
            emitterHidden.value = '';
            emitterSearch.value = '';
        }
    }
    bankCheckbox.addEventListener('change', toggleBankMandate);
    toggleBankMandate();

    // Autocomplet comptes
    function setupAccountSearch(inputId, hiddenId, suggestionsId, filterType) {
        var input   = document.getElementById(inputId);
        var hidden  = document.getElementById(hiddenId);
        var suggest = document.getElementById(suggestionsId);
        var timer   = null;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { suggest.style.display = 'none'; return; }
            timer = setTimeout(function () {
                var url = '/moderation/direct-debits/accounts/search?q=' + encodeURIComponent(q);
                if (filterType) url += '&type=' + filterType;
                fetch(url).then(function (r) { return r.json(); }).then(function (data) {
                    if (!data.length) { suggest.style.display = 'none'; return; }
                    suggest.innerHTML = '';
                    data.forEach(function (acc) {
                        var div = document.createElement('div');
                        div.style.cssText = 'padding:0.5rem 0.8rem;cursor:pointer;border-bottom:1px solid var(--border-color);font-size:0.92rem;';
                        div.textContent = acc.name + ' (' + acc.currency + ') — ' + (acc.owner || '');
                        div.addEventListener('click', function () {
                            hidden.value = acc.id;
                            input.value = acc.name + ' — ' + (acc.owner || '');
                            suggest.style.display = 'none';
                        });
                        div.addEventListener('mouseenter', function () { div.style.background = 'var(--hover-bg, #f0f0f0)'; });
                        div.addEventListener('mouseleave', function () { div.style.background = ''; });
                        suggest.appendChild(div);
                    });
                    suggest.style.display = 'block';
                });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!suggest.contains(e.target) && e.target !== input) {
                suggest.style.display = 'none';
            }
        });
    }

    // Émetteur : filtre sur comptes pro uniquement
    setupAccountSearch('emitter-search', 'emitter_account_id', 'emitter-suggestions', 'pro');
    // Destinataire : tous les comptes
    setupAccountSearch('recipient-search', 'recipient_account_id', 'recipient-suggestions', null);
})();
</script>
