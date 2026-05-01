<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-coin"></i> Modération — Crédits</h1>
        <p class="page-description">Gestion des crédits accordés aux utilisateurs</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/loans/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Octroyer un crédit</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-cash-coin"></i> Crédits</span>
    </div>
</div>

<?php if (empty($loans)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-cash-coin" style="font-size:2.5rem;color:var(--text-muted)"></i></div>
        <p>Aucun crédit enregistré.</p>
        <a href="/moderation/loans/create" class="btn btn-primary btn-sm mt-2">
            <i class="bi bi-plus-circle"></i> Octroyer le premier crédit
        </a>
    </div>
<?php else: ?>

<!-- Filtres -->
<div class="card mb-3">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0.5rem 0.75rem;">
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Utilisateur</label>
                <input type="text" id="lf-user" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" placeholder="Tous…">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Statut</label>
                <select id="lf-status" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                    <option value="">Tous</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Type</label>
                <select id="lf-type" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                    <option value="">Tous</option>
                    <?php foreach ($types as $key => $type): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($type['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button type="button" onclick="resetLoanFilters()" class="btn btn-outline btn-sm w-100">
                    <i class="bi bi-x-circle"></i> Réinitialiser
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover" id="loans-table">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Utilisateur</th>
                    <th>Compte</th>
                    <th>Type</th>
                    <th class="text-right">Montant</th>
                    <th class="text-right">Remboursé</th>
                    <th class="text-center">Taux</th>
                    <th class="text-center">Statut</th>
                    <th class="text-center">Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $loan): ?>
                <tr
                    data-user="<?= strtolower(htmlspecialchars($loan['owner_username'] ?? '')) ?>"
                    data-status="<?= htmlspecialchars($loan['status']) ?>"
                    data-type="<?= htmlspecialchars($loan['loan_type']) ?>"
                >
                    <td class="text-muted" style="font-size:0.8rem">#<?= $loan['id'] ?></td>
                    <td>
                        <span class="fw-medium"><?= htmlspecialchars($loan['owner_username'] ?? '—') ?></span>
                    </td>
                    <td style="font-size:0.85rem;color:var(--text-muted)"><?= htmlspecialchars($loan['account_name'] ?? '—') ?></td>
                    <td>
                        <?php $t = $types[$loan['loan_type']] ?? null; ?>
                        <?php if ($t): ?>
                            <i class="bi <?= htmlspecialchars($t['icon']) ?>"></i>
                            <?= htmlspecialchars($t['label']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($loan['loan_type']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-right fw-medium"><?= number_format((float)$loan['amount'], 2, ',', ' ') ?> €</td>
                    <td class="text-right" style="font-size:0.85rem;color:var(--text-muted)">
                        <?= number_format((float)$loan['amount_repaid'], 2, ',', ' ') ?> €
                    </td>
                    <td class="text-center" style="font-size:0.85rem">
                        <?= number_format((float)$loan['annual_rate'], 2, ',', ' ') ?> %
                    </td>
                    <td class="text-center">
                        <span class="badge <?= htmlspecialchars($statusBadge[$loan['status']] ?? 'badge-secondary') ?>">
                            <?= htmlspecialchars($statusLabels[$loan['status']] ?? $loan['status']) ?>
                        </span>
                    </td>
                    <td class="text-center" style="font-size:0.82rem;white-space:nowrap">
                        <?= $loan['granted_at'] ? date('d/m/Y', strtotime($loan['granted_at'])) : '—' ?>
                    </td>
                    <td>
                        <a href="/moderation/loans/<?= $loan['id'] ?>" class="btn btn-outline btn-sm" title="Voir le détail">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function resetLoanFilters(){
    document.getElementById('lf-user').value='';
    document.getElementById('lf-status').value='';
    document.getElementById('lf-type').value='';
    filterLoans();
}

function filterLoans(){
    const user   = document.getElementById('lf-user').value.toLowerCase().trim();
    const status = document.getElementById('lf-status').value;
    const type   = document.getElementById('lf-type').value;
    let   visible = 0;

    document.querySelectorAll('#loans-table tbody tr').forEach(row => {
        const matchUser   = !user   || row.dataset.user.includes(user);
        const matchStatus = !status || row.dataset.status === status;
        const matchType   = !type   || row.dataset.type   === type;
        const show = matchUser && matchStatus && matchType;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
}

['lf-user','lf-status','lf-type'].forEach(id =>
    document.getElementById(id).addEventListener('input', filterLoans)
);
</script>

<?php endif; ?>
