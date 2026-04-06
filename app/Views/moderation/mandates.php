<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-text"></i> Modération — Mandats</h1>
        <p class="page-description">Gestion des mandats professionnels</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/mandates/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nouveau mandat</a>
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-file-earmark-text"></i> Mandats</span>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.8rem 1rem;">
        <div style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
            <select id="filter-status" class="form-control" style="width:auto;min-width:140px;">
                <option value="">Tous les statuts</option>
                <option value="active">Actif</option>
                <option value="executed">Exécuté</option>
                <option value="revoked">Révoqué</option>
            </select>
            <select id="filter-type" class="form-control" style="width:auto;min-width:140px;">
                <option value="">Tous les types</option>
                <option value="one_time">Ponctuel</option>
                <option value="recurring">Récurrent</option>
            </select>
            <input type="text" id="filter-search" class="form-control" placeholder="Rechercher…"
                   style="width:auto;min-width:180px;flex:1;">
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
                data-search="<?= e(strtolower($m['number'] . ' ' . $m['emitter_name'] . ' ' . $m['emitter_owner'] . ' ' . $m['recipient_name'] . ' ' . $m['recipient_owner'] . ' ' . $m['description'])) ?>">
                <td>
                    <strong><?= e($m['number']) ?></strong>
                    <?php if ($m['description']): ?>
                        <br><small class="text-muted"><?= e($m['description']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/accounts/<?= (int) $m['emitter_account_id'] ?>"><?= e($m['emitter_name']) ?></a>
                    <br><small class="text-muted"><?= e($m['emitter_owner']) ?></small>
                </td>
                <td>
                    <a href="/accounts/<?= (int) $m['recipient_account_id'] ?>"><?= e($m['recipient_name']) ?></a>
                    <br><small class="text-muted"><?= e($m['recipient_owner']) ?></small>
                </td>
                <td style="white-space:nowrap;"><?= number_format((float) $m['amount'], 2, ',', ' ') ?> €</td>
                <td>
                    <?php if ($m['type'] === 'recurring'): ?>
                        <span class="badge badge-info"><i class="bi bi-arrow-repeat"></i> Récurrent</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Ponctuel</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($m['type'] === 'recurring' && $m['interval_days']): ?>
                        <?= (int) $m['interval_days'] ?> jour<?= (int) $m['interval_days'] > 1 ? 's' : '' ?>
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
                        <form method="POST" action="/moderation/mandates/<?= (int) $m['id'] ?>/revoke"
                              style="display:inline;"
                              onsubmit="return confirm('Révoquer ce mandat ?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="bi bi-x-circle"></i> Révoquer
                            </button>
                        </form>
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

<script>
(function () {
    var rows       = document.querySelectorAll('.mandate-row');
    var statusSel  = document.getElementById('filter-status');
    var typeSel    = document.getElementById('filter-type');
    var searchEl   = document.getElementById('filter-search');
    var countEl    = document.getElementById('mandate-count');

    function applyFilters() {
        var st = statusSel.value;
        var tp = typeSel.value;
        var q  = searchEl.value.toLowerCase().trim();
        var shown = 0;
        rows.forEach(function (row) {
            var matchSt = !st || row.dataset.status === st;
            var matchTp = !tp || row.dataset.type === tp;
            var matchQ  = !q  || row.dataset.search.indexOf(q) !== -1;
            var vis = matchSt && matchTp && matchQ;
            row.style.display = vis ? '' : 'none';
            if (vis) shown++;
        });
        countEl.textContent = shown + '/' + rows.length;
    }

    statusSel.addEventListener('change', applyFilters);
    typeSel.addEventListener('change', applyFilters);
    searchEl.addEventListener('input', applyFilters);
    applyFilters();
})();
</script>
