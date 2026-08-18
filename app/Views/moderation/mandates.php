<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-text"></i> Modération — Mandats</h1>
        <p class="page-description">Gestion des mandats professionnels</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/mandates/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nouveau mandat</a>
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/recurring-transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Virements récurrents</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-file-earmark-text"></i> Mandats</span>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="/moderation/savings-rate" class="btn btn-outline btn-sm"><i class="bi bi-percent"></i> Taux d'intérêt</a>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.8rem 1rem;">
        <div style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
            <select id="filter-type" class="form-control" style="width:auto;min-width:140px;">
                <option value="">Tous les types</option>
                <option value="one_time">Ponctuel</option>
                <option value="recurring">Récurrent</option>
            </select>
            <input type="text" id="filter-search" class="form-control" placeholder="Rechercher…"
                   style="width:auto;min-width:180px;flex:1;">
            <button type="button" id="btn-show-revoked" class="btn btn-outline btn-sm">
                <i class="bi bi-eye"></i> Afficher révoqués
            </button>
            <span id="mandate-count" style="font-size:0.85rem;color:var(--text-muted)"></span>
        </div>
    </div>
</div>

<?php if (empty($mandates)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> Aucun mandat enregistré.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table" id="mandates-table">
        <thead>
            <tr>
                <th>N° Mandat</th>
                <th>Émetteur (pro)</th>
                <th>Destinataire</th>
                <th>Montant</th>
                <th>Type</th>
                <th>Intervalle</th>
                <th>Prochaine exéc.</th>
                <th>Dernière exéc.</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($mandates as $m): ?>
            <tr class="mandate-row"
                data-status="<?= e($m['status']) ?>"
                data-type="<?= e($m['type']) ?>"
                data-search="<?= e(strtolower($m['number'] . ' ' . ($m['emitter_name'] ?? 'banque') . ' ' . ($m['emitter_owner'] ?? '') . ' ' . $m['recipient_name'] . ' ' . $m['recipient_owner'] . ' ' . $m['description'])) ?>">
                <td>
                    <strong><?= e($m['number']) ?></strong>
                    <?php if ($m['description']): ?>
                        <br><small class="text-muted"><?= e($m['description']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['emitter_account_id'] !== null): ?>
                        <a href="/accounts/<?= (int) $m['emitter_account_id'] ?>"><?= e($m['emitter_name']) ?></a>
                        <br><small class="text-muted"><?= e($m['emitter_owner']) ?></small>
                    <?php else: ?>
                        <span class="badge badge-secondary"><i class="bi bi-bank"></i> Banque</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/accounts/<?= (int) $m['recipient_account_id'] ?>"><?= e($m['recipient_name']) ?></a>
                    <br><small class="text-muted"><?= e($m['recipient_owner']) ?></small>
                </td>
                <td style="white-space:nowrap;"><?= fmt_amount_smart((float) $m['amount']) ?> €</td>
                <td>
                    <?php if ($m['type'] === 'recurring'): ?>
                        <span class="badge badge-info"><i class="bi bi-arrow-repeat"></i> Récurrent</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Ponctuel</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['type'] === 'recurring'): ?>
                        <?php if (!empty($m['execution_day'])): ?>
                            Le <?= (int) $m['execution_day'] ?> du mois
                        <?php elseif (!empty($m['interval_days'])): ?>
                            <?= (int) $m['interval_days'] ?> jour<?= (int) $m['interval_days'] > 1 ? 's' : '' ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;font-size:0.88rem;">
                    <?php if ($m['next_execution_at']): ?>
                        <?= e(date('d/m/Y H:i', strtotime($m['next_execution_at']))) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;font-size:0.88rem;">
                    <?php if ($m['last_executed_at']): ?>
                        <?= e(date('d/m/Y H:i', strtotime($m['last_executed_at']))) ?>
                    <?php else: ?>
                        <span class="text-muted">Jamais</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['status'] === 'active'): ?>
                        <span class="badge badge-success">Actif</span>
                    <?php elseif ($m['status'] === 'executed'): ?>
                        <span class="badge badge-info">Exécuté</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Révoqué</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['status'] === 'active'): ?>
                        <div style="display:flex;flex-direction:column;gap:0.3rem;align-items:flex-start;">
                            <form method="POST" action="/moderation/mandates/<?= (int) $m['id'] ?>/reschedule"
                                  style="display:flex;align-items:center;gap:0.3rem;">
                                <?= csrf_field() ?>
                                <input type="text" name="next_execution_at"
                                       value="<?= $m['next_execution_at'] ? e(date('d/m/Y H:i', strtotime($m['next_execution_at']))) : '' ?>"
                                       placeholder="jj/mm/aaaa hh:mm"
                                       title="Nouvelle date de prochaine exécution"
                                       style="font-size:0.76rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:130px">
                                <button type="submit" class="btn btn-outline btn-sm"
                                        style="padding:0.25rem 0.5rem;font-size:0.76rem;"
                                        title="Reprogrammer la prochaine exécution">
                                    <i class="bi bi-calendar2-event"></i>
                                </button>
                            </form>
                            <div style="display:flex;gap:0.3rem;">
                                <button type="button" class="btn btn-outline btn-sm btn-edit-mandate"
                                        style="padding:0.25rem 0.5rem;font-size:0.76rem;"
                                        title="Modifier le mandat"
                                        data-id="<?= (int) $m['id'] ?>"
                                        data-number="<?= e($m['number']) ?>"
                                        data-description="<?= e($m['description'] ?? '') ?>"
                                        data-amount="<?= e((string) (float) $m['amount']) ?>"
                                        data-type="<?= e($m['type']) ?>"
                                        data-interval="<?= (int) ($m['interval_days'] ?? 0) ?>"
                                        data-execday="<?= (int) ($m['execution_day'] ?? 0) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="/moderation/mandates/<?= (int) $m['id'] ?>/revoke"
                                      style="display:inline;"
                                      onsubmit="return confirm('Révoquer ce mandat ?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            style="padding:0.25rem 0.6rem;font-size:0.76rem;">
                                        <i class="bi bi-x-circle"></i> Révoquer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal d'édition de mandat -->
<div id="edit-mandate-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:var(--card-bg,#fff);border-radius:12px;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="margin:0;"><i class="bi bi-pencil"></i> Modifier le mandat <span id="edit-mandate-number"></span></h3>
            <button type="button" id="edit-mandate-close" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--gray);">&times;</button>
        </div>
        <form method="POST" id="edit-mandate-form" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Descriptif</label>
                <input type="text" name="description" id="edit-desc" class="form-control" maxlength="255"
                       placeholder="Ex : Abonnement mensuel SaaS">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Montant (€)</label>
                    <input type="number" name="amount" id="edit-amount" class="form-control"
                           min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" id="edit-type" class="form-control" required>
                        <?php foreach (\App\Models\Mandate::TYPES as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="edit-recurring-fields" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Mode de récurrence</label>
                    <div style="display:flex;gap:1rem;">
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                            <input type="radio" name="recurring_mode" id="edit-mode-interval" value="interval" checked>
                            Tous les N jours
                        </label>
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                            <input type="radio" name="recurring_mode" id="edit-mode-fixedday" value="fixed_day">
                            Jour fixe du mois
                        </label>
                    </div>
                </div>
                <div id="edit-interval-group" class="form-group">
                    <label class="form-label">Intervalle (jours)</label>
                    <input type="number" name="interval_days" id="edit-interval" class="form-control" min="1" step="1" placeholder="Ex : 30">
                </div>
                <div id="edit-fixedday-group" class="form-group" style="display:none;">
                    <label class="form-label">Jour fixe (1–31)</label>
                    <input type="number" name="execution_day" id="edit-execday" class="form-control" min="1" max="31" step="1" placeholder="Ex : 5">
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.5rem;">
                <button type="button" id="edit-mandate-cancel" class="btn btn-outline">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var rows        = document.querySelectorAll('.mandate-row');
    var typeSel     = document.getElementById('filter-type');
    var searchEl    = document.getElementById('filter-search');
    var countEl     = document.getElementById('mandate-count');
    var btnRevoked  = document.getElementById('btn-show-revoked');
    var showRevoked = false;

    function applyFilters() {
        var tp = typeSel.value;
        var q  = searchEl.value.toLowerCase().trim();
        var shown = 0;
        rows.forEach(function (row) {
            var isRevoked = row.dataset.status === 'revoked';
            if (isRevoked && !showRevoked) { row.style.display = 'none'; return; }
            var matchTp = !tp || row.dataset.type === tp;
            var matchQ  = !q  || row.dataset.search.indexOf(q) !== -1;
            var vis = matchTp && matchQ;
            row.style.display = vis ? '' : 'none';
            if (vis) shown++;
        });
        countEl.textContent = shown + '/' + rows.length;
    }

    btnRevoked.addEventListener('click', function () {
        showRevoked = !showRevoked;
        this.innerHTML = showRevoked
            ? '<i class="bi bi-eye-slash"></i> Masquer révoqués'
            : '<i class="bi bi-eye"></i> Afficher révoqués';
        applyFilters();
    });

    typeSel.addEventListener('change', applyFilters);
    searchEl.addEventListener('input', applyFilters);
    applyFilters();

    // ── Modal d'édition ──────────────────────────────────────────────────────
    var overlay      = document.getElementById('edit-mandate-overlay');
    var form         = document.getElementById('edit-mandate-form');
    var numEl        = document.getElementById('edit-mandate-number');
    var descEl       = document.getElementById('edit-desc');
    var amountEl     = document.getElementById('edit-amount');
    var typeEl       = document.getElementById('edit-type');
    var recurFields  = document.getElementById('edit-recurring-fields');
    var modeInterval = document.getElementById('edit-mode-interval');
    var modeFixed    = document.getElementById('edit-mode-fixedday');
    var intervalGrp  = document.getElementById('edit-interval-group');
    var fixedGrp     = document.getElementById('edit-fixedday-group');
    var intervalEl   = document.getElementById('edit-interval');
    var execdayEl    = document.getElementById('edit-execday');

    function toggleRecurring() {
        var isRecurring = typeEl.value === 'recurring';
        recurFields.style.display = isRecurring ? '' : 'none';
        if (isRecurring) toggleRecurringMode();
    }

    function toggleRecurringMode() {
        var isFixed = modeFixed.checked;
        intervalGrp.style.display = isFixed ? 'none' : '';
        fixedGrp.style.display    = isFixed ? '' : 'none';
        intervalEl.required  = !isFixed;
        execdayEl.required   = isFixed;
    }

    typeEl.addEventListener('change', toggleRecurring);
    modeInterval.addEventListener('change', toggleRecurringMode);
    modeFixed.addEventListener('change', toggleRecurringMode);

    function openModal(btn) {
        var id       = btn.dataset.id;
        var type     = btn.dataset.type;
        var interval = parseInt(btn.dataset.interval, 10) || 0;
        var execday  = parseInt(btn.dataset.execday, 10) || 0;

        form.action    = '/moderation/mandates/' + id + '/edit';
        numEl.textContent   = btn.dataset.number;
        descEl.value        = btn.dataset.description;
        amountEl.value      = btn.dataset.amount;
        typeEl.value        = type;

        if (execday > 0) {
            modeFixed.checked = true;
            execdayEl.value   = execday;
            intervalEl.value  = '';
        } else {
            modeInterval.checked = true;
            intervalEl.value     = interval > 0 ? interval : '';
            execdayEl.value      = '';
        }

        toggleRecurring();
        overlay.style.display = 'flex';
    }

    function closeModal() {
        overlay.style.display = 'none';
    }

    document.querySelectorAll('.btn-edit-mandate').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(this); });
    });

    document.getElementById('edit-mandate-close').addEventListener('click', closeModal);
    document.getElementById('edit-mandate-cancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
})();
</script>
