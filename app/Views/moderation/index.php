<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-check"></i> Modération — Comptes</h1>
        <p class="page-description">Vue globale de tous les comptes bancaires</p>
    </div>
    <a href="/moderation/users" class="btn btn-outline btn-sm">
        <i class="bi bi-people"></i> Gérer les utilisateurs
    </a>
</div>

<?php if (empty($allAccounts)): ?>
    <div class="empty-state"><div class="empty-icon">🏦</div><p>Aucun compte enregistré.</p></div>
<?php else: ?>

<?php
    // Listes dédupliquées pour les filtres
    $ownerNames = array_values(array_unique(array_column($allAccounts, 'owner_name')));
    sort($ownerNames);
?>

<!-- Barre de filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;flex:1;min-width:180px;">
                <label class="form-label" for="filter-owner" style="font-size:0.78rem;margin-bottom:0.3rem;">
                    <i class="bi bi-person"></i> Propriétaire
                </label>
                <select id="filter-owner" class="form-control" style="padding:0.35rem 0.6rem;font-size:0.84rem;">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($ownerNames as $name): ?>
                        <option value="<?= e($name) ?>"><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label class="form-label" for="filter-status" style="font-size:0.78rem;margin-bottom:0.3rem;">
                    <i class="bi bi-circle-half"></i> Statut
                </label>
                <select id="filter-status" class="form-control" style="padding:0.35rem 0.6rem;font-size:0.84rem;">
                    <option value="">Tous les statuts</option>
                    <option value="active">Actif</option>
                    <option value="frozen">Gelé</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:2;min-width:200px;">
                <label class="form-label" for="filter-search" style="font-size:0.78rem;margin-bottom:0.3rem;">
                    <i class="bi bi-search"></i> Recherche
                </label>
                <input type="text" id="filter-search" class="form-control"
                       placeholder="Nom du compte…"
                       style="padding:0.35rem 0.6rem;font-size:0.84rem;">
            </div>
            <div style="padding-bottom:0.05rem;">
                <button id="filter-reset" class="btn btn-outline btn-sm">
                    <i class="bi bi-x-circle"></i> Réinitialiser
                </button>
            </div>
        </div>
        <div id="filter-count" style="margin-top:0.55rem;font-size:0.78rem;color:var(--text-muted);"></div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="table" id="accounts-table" style="margin:0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Propriétaire</th>
                        <th>Type</th>
                        <th>Devise</th>
                        <th class="text-right">Solde</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAccounts as $acc): ?>
                        <?php $frozen = !empty($acc['frozen']); ?>
                        <tr class="<?= $frozen ? 'row-frozen' : '' ?>"
                            data-owner="<?= e($acc['owner_name']) ?>"
                            data-status="<?= $frozen ? 'frozen' : 'active' ?>"
                            data-name="<?= e(strtolower($acc['name'])) ?>">
                            <td class="text-muted text-small">#<?= (int) $acc['id'] ?></td>
                            <td>
                                <a href="/accounts/<?= (int) $acc['id'] ?>" class="font-bold">
                                    <?= e($acc['name']) ?>
                                </a>
                            </td>
                            <td><?= e($acc['owner_name']) ?></td>
                            <td>
                                <?php $typeLabel = \App\Models\Account::TYPES[$acc['type'] ?? '']['label'] ?? '—'; ?>
                                <span class="text-small"><?= e($typeLabel) ?></span>
                            </td>
                            <td><?= e($acc['currency']) ?></td>
                            <td class="text-right font-bold <?= $acc['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($acc['balance'], 2, ',', ' ') ?>
                            </td>
                            <td>
                                <?php if ($frozen): ?>
                                    <span class="badge badge-frozen"><i class="bi bi-snow"></i> Gelé</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                                    <a href="/accounts/<?= (int) $acc['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($frozen): ?>
                                        <form method="POST" action="/moderation/accounts/<?= (int) $acc['id'] ?>/unfreeze" style="display:inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success btn-sm"
                                                    title="Dégeler"
                                                    onclick="return confirm('Dégeler le compte « <?= e(addslashes($acc['name'])) ?> » ?')">
                                                <i class="bi bi-sun"></i> Dégeler
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/moderation/accounts/<?= (int) $acc['id'] ?>/freeze" style="display:inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-freeze"
                                                    title="Geler"
                                                    onclick="return confirm('Geler le compte « <?= e(addslashes($acc['name'])) ?> » ? Les opérations sortantes seront bloquées.')">
                                                <i class="bi bi-snow"></i> Geler
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="filter-empty" style="display:none;padding:2rem;text-align:center;color:var(--text-muted);">
                <i class="bi bi-search" style="font-size:1.5rem;"></i>
                <p style="margin-top:0.5rem;">Aucun compte ne correspond aux filtres.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var ownerEl  = document.getElementById('filter-owner');
    var statusEl = document.getElementById('filter-status');
    var searchEl = document.getElementById('filter-search');
    var resetBtn = document.getElementById('filter-reset');
    var countEl  = document.getElementById('filter-count');
    var emptyEl  = document.getElementById('filter-empty');
    var rows     = document.querySelectorAll('#accounts-table tbody tr');
    var total    = rows.length;

    function applyFilters() {
        var owner  = ownerEl.value;
        var status = statusEl.value;
        var search = searchEl.value.toLowerCase().trim();
        var visible = 0;

        rows.forEach(function (row) {
            var matchOwner  = !owner  || row.dataset.owner  === owner;
            var matchStatus = !status || row.dataset.status === status;
            var matchSearch = !search || row.dataset.name.indexOf(search) !== -1;

            if (matchOwner && matchStatus && matchSearch) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        emptyEl.style.display = visible === 0 ? 'block' : 'none';
        countEl.textContent   = visible + ' / ' + total + ' compte' + (total > 1 ? 's' : '') + ' affiché' + (visible > 1 ? 's' : '');
    }

    ownerEl.addEventListener('change', applyFilters);
    statusEl.addEventListener('change', applyFilters);
    searchEl.addEventListener('input', applyFilters);

    resetBtn.addEventListener('click', function () {
        ownerEl.value  = '';
        statusEl.value = '';
        searchEl.value = '';
        applyFilters();
    });

    applyFilters();
})();
</script>

<?php endif; ?>
