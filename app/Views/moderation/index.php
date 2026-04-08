<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-check"></i> Modération — Comptes</h1>
        <p class="page-description">Vue globale de tous les comptes bancaires</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-shield-check"></i> Comptes</span>
        <a href="/moderation/accounts/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Créer un compte</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/recurring-transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Virements récurrents</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="/moderation/savings-rate" class="btn btn-outline btn-sm"><i class="bi bi-percent"></i> Taux d'intérêt</a>
    </div>
</div>

<?php if (empty($allAccounts)): ?>
    <div class="empty-state"><div class="empty-icon">🏦</div><p>Aucun compte enregistré.</p></div>
<?php else: ?>

<?php
    // Listes dédupliquées pour les filtres : propriétaires + utilisateurs partagés
    $ownerNames = array_unique(array_column($allAccounts, 'owner_name'));
    foreach ($allAccounts as $__acc) {
        foreach ($__acc['shared_users'] ?? [] as $__su) {
            $ownerNames[] = $__su;
        }
    }
    $ownerNames = array_values(array_unique($ownerNames));
    sort($ownerNames);
?>

<!-- Barre de filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;flex:1;min-width:180px;position:relative;">
                <label class="form-label" for="filter-owner" style="font-size:0.78rem;margin-bottom:0.3rem;">
                    <i class="bi bi-person"></i> Propriétaire
                </label>
                <input type="text" id="filter-owner" class="form-control" autocomplete="off"
                       placeholder="Tous les utilisateurs…"
                       style="padding:0.35rem 0.6rem;font-size:0.84rem;">
                <ul id="owner-suggestions" style="
                    display:none;position:absolute;z-index:100;left:0;right:0;top:100%;
                    background:var(--bg-card,#fff);border:1px solid var(--gray-light,#e5e7eb);
                    border-top:none;border-radius:0 0 var(--border-radius,6px) var(--border-radius,6px);
                    list-style:none;margin:0;padding:0;max-height:200px;overflow-y:auto;
                    box-shadow:0 4px 12px rgba(0,0,0,.08);
                "></ul>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label class="form-label" for="filter-status" style="font-size:0.78rem;margin-bottom:0.3rem;">
                    <i class="bi bi-circle-half"></i> Statut
                </label>
                <select id="filter-status" class="form-control" style="padding:0.35rem 0.6rem;font-size:0.84rem;">
                    <option value="">Tous les statuts</option>
                    <option value="active">Actif</option>
                    <option value="frozen">Gelé</option>
                    <option value="disabled">Résiliation prévue</option>
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
                        <th>Propriétaire / Accès</th>
                        <th>Type</th>
                        <th>Devise</th>
                        <th class="text-right">Solde</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAccounts as $acc): ?>
                        <?php $frozen = !empty($acc['frozen']); $disabled = !empty($acc['disabled_at']); ?>
                        <tr class="<?= $frozen ? 'row-frozen' : ($disabled ? 'row-disabled' : '') ?>"
                            data-owner="<?= e($acc['owner_name']) ?>"
                            data-shared-users="<?= e(implode('|', $acc['shared_users'] ?? [])) ?>"
                            data-status="<?= $frozen ? 'frozen' : ($disabled ? 'disabled' : 'active') ?>"
                            data-name="<?= e(strtolower($acc['name'])) ?>">
                            <td class="text-muted text-small">#<?= (int) $acc['id'] ?></td>
                            <td>
                                <a href="/accounts/<?= (int) $acc['id'] ?>" class="font-bold">
                                    <?= e($acc['name']) ?>
                                </a>
                            </td>
                            <td>
                                <span><?= e($acc['owner_name']) ?></span>
                                <?php if (!empty($acc['shared_users'])): ?>
                                    <div style="margin-top:0.25rem;display:flex;flex-wrap:wrap;gap:0.25rem;">
                                        <?php foreach ($acc['shared_users'] as $su): ?>
                                            <span class="badge badge-secondary" style="font-size:0.7rem;">
                                                <i class="bi bi-share"></i> <?= e($su) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
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
                                <?php elseif ($disabled): ?>
                                    <span class="badge" style="background:var(--danger);color:#fff;"><i class="bi bi-slash-circle"></i> Résiliation prévue</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php endif; ?>
                                <?php if ($frozen && $disabled): ?>
                                    <span class="badge" style="background:var(--danger);color:#fff;font-size:0.75em;"><i class="bi bi-slash-circle"></i> Résiliation prévue</span>
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
    var ownerInput   = document.getElementById('filter-owner');
    var suggestions  = document.getElementById('owner-suggestions');
    var statusEl     = document.getElementById('filter-status');
    var searchEl     = document.getElementById('filter-search');
    var resetBtn     = document.getElementById('filter-reset');
    var countEl      = document.getElementById('filter-count');
    var emptyEl      = document.getElementById('filter-empty');
    var rows         = document.querySelectorAll('#accounts-table tbody tr');
    var total        = rows.length;

    // Valeur validée du filtre propriétaire (vide = pas de filtre)
    var activeOwner  = '';

    var allOwners = <?= json_encode($ownerNames, JSON_UNESCAPED_UNICODE) ?>;

    /* --- Autocomplétion --- */
    function showSuggestions(query) {
        var q = query.toLowerCase().trim();
        var matches = q === ''
            ? allOwners
            : allOwners.filter(function (n) { return n.toLowerCase().indexOf(q) !== -1; });

        suggestions.innerHTML = '';
        if (matches.length === 0) { suggestions.style.display = 'none'; return; }

        matches.forEach(function (name) {
            var li = document.createElement('li');
            li.textContent = name;
            li.style.cssText = 'padding:0.45rem 0.75rem;cursor:pointer;font-size:0.84rem;';
            li.addEventListener('mousedown', function (e) {
                e.preventDefault(); // Empêche le blur avant le click
                ownerInput.value = name;
                activeOwner = name;
                suggestions.style.display = 'none';
                applyFilters();
            });
            li.addEventListener('mouseenter', function () { this.style.background = 'var(--gray-lighter,#f3f4f6)'; });
            li.addEventListener('mouseleave', function () { this.style.background = ''; });
            suggestions.appendChild(li);
        });
        suggestions.style.display = 'block';
    }

    ownerInput.addEventListener('input', function () {
        activeOwner = ''; // L'utilisateur tape → invalider la sélection précédente
        showSuggestions(this.value);
        applyFilters();
    });

    ownerInput.addEventListener('focus', function () { showSuggestions(this.value); });
    ownerInput.addEventListener('blur',  function () { setTimeout(function () { suggestions.style.display = 'none'; }, 150); });

    ownerInput.addEventListener('keydown', function (e) {
        var items = suggestions.querySelectorAll('li');
        var active = suggestions.querySelector('li.active');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            var next = active ? active.nextElementSibling : items[0];
            if (active) active.classList.remove('active');
            if (next) { next.classList.add('active'); next.style.background = 'var(--gray-lighter,#f3f4f6)'; ownerInput.value = next.textContent; }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var prev = active ? active.previousElementSibling : items[items.length - 1];
            if (active) active.classList.remove('active');
            if (prev) { prev.classList.add('active'); prev.style.background = 'var(--gray-lighter,#f3f4f6)'; ownerInput.value = prev.textContent; }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (active) { activeOwner = active.textContent; ownerInput.value = activeOwner; suggestions.style.display = 'none'; applyFilters(); }
        } else if (e.key === 'Escape') {
            suggestions.style.display = 'none';
        }
    });

    /* --- Filtres --- */
    function applyFilters() {
        var status = statusEl.value;
        var search = searchEl.value.toLowerCase().trim();
        var visible = 0;

        // Le filtre propriétaire est actif uniquement si activeOwner est défini
        // OU si le texte saisi correspond exactement à un nom connu
        var ownerFilter = activeOwner;
        if (!ownerFilter) {
            var typed = ownerInput.value.trim();
            if (allOwners.indexOf(typed) !== -1) ownerFilter = typed;
        }

        rows.forEach(function (row) {
            var sharedUsers = row.dataset.sharedUsers ? row.dataset.sharedUsers.split('|') : [];
            var matchOwner  = !ownerFilter
                || row.dataset.owner === ownerFilter
                || sharedUsers.indexOf(ownerFilter) !== -1;
            var matchStatus = !status      || row.dataset.status === status;
            var matchSearch = !search      || row.dataset.name.indexOf(search) !== -1;

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

    statusEl.addEventListener('change', applyFilters);
    searchEl.addEventListener('input',  applyFilters);

    resetBtn.addEventListener('click', function () {
        ownerInput.value = '';
        activeOwner      = '';
        statusEl.value   = '';
        searchEl.value   = '';
        suggestions.style.display = 'none';
        applyFilters();
    });

    applyFilters();
})();
</script>

<?php endif; ?>
