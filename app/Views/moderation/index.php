<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-check"></i> Modération — Comptes</h1>
        <p class="page-description">Vue globale de tous les comptes bancaires</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-shield-check"></i> Comptes</span>
        <a href="/moderation/accounts/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Créer un compte</a>
        <a href="/moderation/internal-accounts/create" class="btn btn-warning btn-sm" title="Compte de modération réservé aux tests, non partageable"><i class="bi bi-tools"></i> Compte interne</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/recurring-transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Virements récurrents</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="/moderation/savings-rate" class="btn btn-outline btn-sm"><i class="bi bi-percent"></i> Taux d'intérêt</a>
        <a href="/moderation/features" class="btn btn-outline btn-sm"><i class="bi bi-toggles"></i> Fonctionnalités</a>
    </div>
</div>

<?php if (!empty($pendingDeferredDebitCount)): ?>
<div class="card mb-2" style="border-left:4px solid var(--warning);">
    <div class="card-body" style="padding:0.85rem 1.1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
            <strong><i class="bi bi-calendar-event"></i> Débits différés — fin de période</strong>
            <div class="text-muted text-small" style="margin-top:0.2rem;">
                <?= (int) $pendingDeferredDebitCount ?> débit(s) différé(s) échu(s) en attente d'exécution
                par le CRON. Vous pouvez forcer l'exécution immédiate sans attendre la prochaine minute.
            </div>
        </div>
        <form method="POST" action="/moderation/deferred-debits/force-process" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-warning btn-sm"
                    onclick="return confirm('Forcer l\'exécution immédiate de <?= (int) $pendingDeferredDebitCount ?> débit(s) différé(s) échu(s) ?\n\nLes transactions de dépense correspondantes seront créées et les opérations marquées comme exécutées.');">
                <i class="bi bi-lightning-charge"></i> Forcer les débits différés (<?= (int) $pendingDeferredDebitCount ?>)
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($pendingClosureCount)): ?>
<div class="card mb-2" style="border-left:4px solid var(--danger);">
    <div class="card-body" style="padding:0.85rem 1.1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
            <strong><i class="bi bi-slash-circle"></i> Clôture définitive en fin de mois</strong>
            <div class="text-muted text-small" style="margin-top:0.2rem;">
                <?= (int) $pendingClosureCount ?> compte(s) désactivé(s) en attente de suppression définitive
                par le CRON mensuel. Vous pouvez forcer l'exécution immédiate sans attendre la fin du mois.
            </div>
        </div>
        <form method="POST" action="/moderation/accounts/force-closures" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Forcer la clôture définitive de <?= (int) $pendingClosureCount ?> compte(s) désactivé(s) ?\n\nCette opération est irréversible : transactions, accès partagés, mandats, virements récurrents et prélèvements planifiés liés seront supprimés ou annulés.');">
                <i class="bi bi-fast-forward-circle"></i> Forcer la clôture (<?= (int) $pendingClosureCount ?>)
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

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
                                <?php if (!empty($acc['internal'])): ?>
                                    <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;font-size:0.7rem;margin-left:0.3rem;" title="Compte interne de modération (test) — non partageable">
                                        <i class="bi bi-tools"></i> Interne
                                    </span>
                                <?php endif; ?>
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
                                <?= fmt_amount_smart($acc['balance']) ?>
                            </td>
                            <td>
                                <?php if ($frozen): ?>
                                    <span class="badge badge-frozen"><i class="bi bi-snow"></i> Gelé</span>
                                    <?php if (!empty($acc['frozen_until'])): ?>
                                        <span class="badge" style="background:rgba(59,130,246,0.12);color:#1d4ed8;font-size:0.72em;" title="Dégel automatique">
                                            <i class="bi bi-clock"></i> jusqu'au <?= e((new DateTime($acc['frozen_until']))->format('d/m/Y')) ?>
                                        </span>
                                    <?php endif; ?>
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
                                        <button type="button" class="btn btn-sm btn-freeze"
                                                title="Geler ce compte"
                                                onclick="openFreezeModal(<?= (int) $acc['id'] ?>, <?= htmlspecialchars(json_encode($acc['name']), ENT_QUOTES) ?>)">
                                            <i class="bi bi-snow"></i> Geler
                                        </button>
                                    <?php endif; ?>

                                    <?php if (\App\Models\Account::typeAllowsDeferredDebit($acc['type'] ?? 'standard')): ?>
                                        <form method="POST" action="/moderation/accounts/<?= (int) $acc['id'] ?>/toggle-deferred-debit" style="display:inline">
                                            <?= csrf_field() ?>
                                            <?php if (!empty($acc['deferred_debit_enabled'])): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Désactiver le débit différé"
                                                        onclick="return confirm('Désactiver le débit différé pour « <?= e(addslashes($acc['name'])) ?> » ?')">
                                                    <i class="bi bi-credit-card"></i> DD ✗
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline"
                                                        title="Activer le débit différé"
                                                        onclick="return confirm('Activer le débit différé pour « <?= e(addslashes($acc['name'])) ?> » ?')">
                                                    <i class="bi bi-credit-card"></i> DD ✓
                                                </button>
                                            <?php endif; ?>
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

<!-- ── Modal Gel de compte ──────────────────────────────────────── -->
<div id="freezeModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--card-bg,#fff);border-radius:var(--border-radius);box-shadow:0 8px 32px rgba(0,0,0,0.18);padding:1.5rem 1.75rem;width:100%;max-width:460px;margin:1rem;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.15rem;gap:0.5rem;">
            <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:0.45rem;flex:1;min-width:0;">
                <i class="bi bi-snow" style="color:#3b82f6;flex-shrink:0;"></i>
                <span>Geler &laquo;&nbsp;<span id="freezeModalName" style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;display:inline-block;vertical-align:bottom;"></span>&nbsp;&raquo;</span>
            </h3>
            <button type="button" onclick="closeFreezeModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.3rem;line-height:1;padding:0 0.2rem;flex-shrink:0;" title="Fermer">&times;</button>
        </div>
        <p style="font-size:0.82rem;color:var(--text-muted);margin:0.1rem 0 1.1rem;">Les opérations sortantes et les virements débiteurs seront bloqués.</p>

        <form id="freezeModalForm" method="POST" action="">
            <?= csrf_field() ?>

            <!-- Motif -->
            <div style="margin-bottom:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.35rem;">
                    <label style="font-size:0.83rem;font-weight:600;">
                        Motif <span style="font-weight:400;color:var(--text-muted)">(facultatif)</span>
                    </label>
                    <span id="modalCharCounter" style="font-size:0.76rem;color:var(--text-muted);">0 / 500</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.45rem;">
                    <button type="button" class="freeze-reason-chip" data-reason="Fraude suspectée"><i class="bi bi-exclamation-triangle"></i> Fraude</button>
                    <button type="button" class="freeze-reason-chip" data-reason="Demande judiciaire"><i class="bi bi-bank"></i> Judiciaire</button>
                    <button type="button" class="freeze-reason-chip" data-reason="Vérification en cours"><i class="bi bi-search"></i> Vérification</button>
                    <button type="button" class="freeze-reason-chip" data-reason="Blocage préventif"><i class="bi bi-shield-lock"></i> Préventif</button>
                </div>
                <textarea id="modalReason" name="reason" rows="2" maxlength="500"
                    placeholder="Ou saisissez un motif personnalisé…"
                    style="width:100%;font-size:0.84rem;padding:0.4rem 0.6rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);resize:vertical;box-sizing:border-box;"></textarea>
            </div>

            <!-- Durée -->
            <div style="margin-bottom:1.2rem;">
                <label style="display:block;font-size:0.83rem;font-weight:600;margin-bottom:0.4rem;">Durée du gel</label>
                <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.5rem;">
                    <button type="button" class="freeze-chip active" data-days="">Indéfini</button>
                    <button type="button" class="freeze-chip" data-days="1">1 jour</button>
                    <button type="button" class="freeze-chip" data-days="3">3 jours</button>
                    <button type="button" class="freeze-chip" data-days="7">7 jours</button>
                    <button type="button" class="freeze-chip" data-days="30">1 mois</button>
                    <button type="button" class="freeze-chip" data-days="90">3 mois</button>
                    <button type="button" class="freeze-chip" data-days="custom"><i class="bi bi-calendar3"></i> Personnalisé</button>
                </div>
                <input type="datetime-local" id="modalFrozenUntil" name="frozen_until"
                    style="display:none;font-size:0.84rem;padding:0.4rem 0.6rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:100%;box-sizing:border-box;margin-bottom:0.4rem;">
                <div id="modalFreezePreview" class="freeze-preview">
                    <i class="bi bi-infinity"></i> Gel indéfini — jusqu'à révocation manuelle.
                </div>
            </div>

            <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
                <button type="button" onclick="closeFreezeModal()" class="btn btn-outline btn-sm">Annuler</button>
                <button type="submit" class="btn btn-freeze btn-sm"><i class="bi bi-snow"></i> Confirmer le gel</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function formatLocal(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function friendlyDate(iso) {
        if (!iso) return null;
        var d = new Date(iso);
        if (isNaN(d)) return null;
        var months = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear()
            + ' à ' + pad(d.getHours()) + 'h' + pad(d.getMinutes());
    }

    function initFreezeForm(opts) {
        var chips       = opts.form.querySelectorAll('.freeze-chip[data-days]');
        var customInput = document.getElementById(opts.inputId);
        var preview     = document.getElementById(opts.previewId);
        var textarea    = document.getElementById(opts.reasonId);
        var counter     = document.getElementById(opts.counterId);
        var reasonChips = opts.form.querySelectorAll('.freeze-reason-chip');

        function updatePreview() {
            var val = customInput.value;
            if (!val) {
                preview.innerHTML = '<i class="bi bi-infinity"></i> Gel indéfini — jusqu\'à révocation manuelle.';
                preview.style.color = '';
            } else {
                var fd = friendlyDate(val);
                preview.innerHTML = '<i class="bi bi-calendar-check" style="color:#3b82f6"></i> Dégel automatique le <strong>' + fd + '</strong>.';
                preview.style.color = '#1d4ed8';
            }
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                var days = chip.dataset.days;
                if (days === '') {
                    customInput.value = '';
                    customInput.style.display = 'none';
                } else if (days === 'custom') {
                    customInput.style.display = 'block';
                    customInput.focus();
                } else {
                    var d = new Date();
                    d.setDate(d.getDate() + parseInt(days, 10));
                    customInput.value = formatLocal(d);
                    customInput.style.display = 'none';
                }
                updatePreview();
            });
        });

        customInput.addEventListener('input', updatePreview);

        if (textarea && counter) {
            textarea.addEventListener('input', function () {
                counter.textContent = textarea.value.length + ' / 500';
            });
        }

        reasonChips.forEach(function (rc) {
            rc.addEventListener('click', function () {
                if (textarea) {
                    textarea.value = rc.dataset.reason;
                    if (counter) counter.textContent = textarea.value.length + ' / 500';
                    textarea.focus();
                }
            });
        });

        updatePreview();
        return { reset: function () {
            chips.forEach(function (c) { c.classList.remove('active'); });
            var first = opts.form.querySelector('.freeze-chip[data-days=""]');
            if (first) first.classList.add('active');
            customInput.value = '';
            customInput.style.display = 'none';
            if (textarea) textarea.value = '';
            if (counter)  counter.textContent = '0 / 500';
            updatePreview();
        }};
    }

    var modalForm = document.getElementById('freezeModalForm');
    var modalCtrl = initFreezeForm({
        form:      modalForm,
        inputId:   'modalFrozenUntil',
        previewId: 'modalFreezePreview',
        reasonId:  'modalReason',
        counterId: 'modalCharCounter',
    });

    window.openFreezeModal = function (accountId, accountName) {
        document.getElementById('freezeModalName').textContent = accountName;
        modalForm.action = '/moderation/accounts/' + accountId + '/freeze';
        modalCtrl.reset();
        document.getElementById('freezeModalOverlay').style.display = 'flex';
    };

    window.closeFreezeModal = function () {
        document.getElementById('freezeModalOverlay').style.display = 'none';
    };

    document.getElementById('freezeModalOverlay').addEventListener('click', function (e) {
        if (e.target === this) window.closeFreezeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeFreezeModal();
    });
}());
</script>

<?php endif; ?>
