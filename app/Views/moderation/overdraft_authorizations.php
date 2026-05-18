<?php
/**
 * Modération — Autorisations de dépassement de découvert.
 *
 * Variables : $authorizations, $allAccounts
 */
use App\Models\Account;

$statusLabels = [
    'active'  => ['label' => 'Active',    'class' => 'badge-success'],
    'pending' => ['label' => 'En attente','class' => 'badge-info'],
    'expired' => ['label' => 'Expirée',   'class' => 'badge-secondary'],
    'revoked' => ['label' => 'Révoquée',  'class' => 'badge-danger'],
];

// Construire la liste des utilisateurs uniques (pour le filtre)
$__owners = [];
foreach ($allAccounts as $__acc) {
    $uid = (int) $__acc['user_id'];
    if (!isset($__owners[$uid])) {
        $__owners[$uid] = $__acc['owner_name'];
    }
}
asort($__owners);

// Préparer le JSON des comptes pour le JS
$__accountsJson = json_encode(array_values(array_map(function ($a) {
    return [
        'id'        => (int) $a['id'],
        'name'      => $a['name'],
        'owner'     => $a['owner_name'],
        'user_id'   => (int) $a['user_id'],
        'type'      => $a['type'] ?? 'standard',
        'currency'  => $a['currency'],
        'overdraft' => (float) ($a['overdraft'] ?? 0),
    ];
}, $allAccounts)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-plus"></i> Autorisations de dépassement</h1>
        <p class="page-description">Gérez les autorisations permettant à des comptes de dépasser leur découvert habituel.</p>
    </div>
</div>

<!-- ── Formulaire de création ────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="bi bi-plus-circle"></i> Nouvelle autorisation</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/moderation/overdraft-authorizations/create" id="oa-form">
            <?= csrf_field() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">

                <!-- Filtre utilisateur (autocomplete) -->
                <div class="form-group" style="margin:0;position:relative;">
                    <label class="form-label"><i class="bi bi-person"></i> Filtrer par utilisateur</label>
                    <input type="text" id="oa-user-input" class="form-control"
                           placeholder="Rechercher un utilisateur…"
                           autocomplete="off">
                    <div id="oa-user-dropdown"
                         style="display:none;position:absolute;z-index:200;background:#fff;border:1px solid var(--gray-light);border-radius:var(--border-radius-sm);width:100%;box-shadow:var(--shadow);max-height:200px;overflow-y:auto;"></div>
                    <!-- Champ caché portant l'id sélectionné -->
                    <input type="hidden" id="oa-filter-user" value="">
                </div>

                <!-- Filtre type de compte -->
                <div class="form-group" style="margin:0;">
                    <label class="form-label"><i class="bi bi-tag"></i> Filtrer par type</label>
                    <select id="oa-filter-type" class="form-control">
                        <option value="">— Tous les types —</option>
                        <?php foreach (Account::TYPES as $key => $def): ?>
                            <option value="<?= e($key) ?>"><?= e($def['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sélecteur de compte (filtré dynamiquement) -->
                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <label class="form-label">Compte cible <span class="text-danger">*</span></label>
                    <select name="account_id" id="oa-account-select" class="form-control" required>
                        <option value="">— Choisir un compte —</option>
                    </select>
                    <span class="form-hint" id="oa-account-hint" style="display:none;color:var(--text-muted);"></span>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Limite supplémentaire <span class="text-danger">*</span></label>
                    <input type="number" name="extra_limit" class="form-control"
                           min="0.01" step="0.01" placeholder="Ex : 500.00" required>
                    <span class="form-hint">Montant ajouté par-dessus le découvert existant</span>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Date de début <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Date de fin</label>
                    <input type="date" name="end_date" class="form-control">
                    <span class="form-hint">Laisser vide = valable jusqu'à révocation</span>
                </div>

                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <label class="form-label">Motif</label>
                    <input type="text" name="reason" class="form-control" maxlength="500"
                           placeholder="Ex : situation exceptionnelle validée le …">
                </div>
            </div>

            <div style="margin-top:1.1rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Créer l'autorisation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var accounts   = <?= $__accountsJson ?>;
    var owners     = <?= json_encode(array_map(fn($uid, $uname) => ['id' => (int)$uid, 'name' => $uname], array_keys($__owners), array_values($__owners)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var typeLabels = <?= json_encode(array_map(fn($d) => $d['label'], Account::TYPES), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    var filterUserHidden = document.getElementById('oa-filter-user');
    var userInput        = document.getElementById('oa-user-input');
    var userDropdown     = document.getElementById('oa-user-dropdown');
    var filterType       = document.getElementById('oa-filter-type');
    var select           = document.getElementById('oa-account-select');
    var hint             = document.getElementById('oa-account-hint');

    // ── Autocomplete utilisateur ──────────────────────────────────────────

    function showUserDropdown(items) {
        userDropdown.innerHTML = '';
        if (!items.length) { userDropdown.style.display = 'none'; return; }

        // Option "Tous" en tête
        var allItem = document.createElement('div');
        allItem.style.cssText = 'padding:0.4rem 0.75rem;cursor:pointer;color:var(--text-muted);font-style:italic;';
        allItem.textContent = '— Tous les utilisateurs —';
        allItem.addEventListener('mousedown', function (e) {
            e.preventDefault();
            filterUserHidden.value = '';
            userInput.value = '';
            userDropdown.style.display = 'none';
            rebuildSelect();
        });
        userDropdown.appendChild(allItem);

        items.forEach(function (u) {
            var item = document.createElement('div');
            item.style.cssText = 'padding:0.4rem 0.75rem;cursor:pointer;display:flex;align-items:center;gap:0.5rem;';
            item.innerHTML = '<i class="bi bi-person" style="color:var(--primary);flex-shrink:0;"></i><span>' + u.name + '</span>';
            item.addEventListener('mouseenter', function () { item.style.background = 'var(--gray-lighter,#f3f4f6)'; });
            item.addEventListener('mouseleave', function () { item.style.background = ''; });
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                filterUserHidden.value = u.id;
                userInput.value = u.name;
                userDropdown.style.display = 'none';
                rebuildSelect();
            });
            userDropdown.appendChild(item);
        });
        userDropdown.style.display = 'block';
    }

    userInput.addEventListener('input', function () {
        var q = userInput.value.trim().toLowerCase();
        // Réinitialiser le filtre si le champ est vidé
        if (!q) {
            filterUserHidden.value = '';
            rebuildSelect();
        }
        var matches = owners.filter(function (u) {
            return u.name.toLowerCase().includes(q);
        });
        showUserDropdown(matches);
    });

    userInput.addEventListener('focus', function () {
        var q = userInput.value.trim().toLowerCase();
        var matches = q
            ? owners.filter(function (u) { return u.name.toLowerCase().includes(q); })
            : owners;
        showUserDropdown(matches);
    });

    userInput.addEventListener('blur', function () {
        setTimeout(function () { userDropdown.style.display = 'none'; }, 150);
    });

    // ── Construction du select compte ────────────────────────────────────

    function rebuildSelect() {
        var uid  = filterUserHidden.value ? parseInt(filterUserHidden.value, 10) : null;
        var type = filterType.value || null;

        var filtered = accounts.filter(function (a) {
            if (uid  !== null && a.user_id !== uid)  return false;
            if (type !== null && a.type    !== type) return false;
            return true;
        });

        var prev = select.value;
        select.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = filtered.length
            ? '— Choisir un compte (' + filtered.length + ') —'
            : '— Aucun compte correspondant —';
        select.appendChild(placeholder);

        filtered.forEach(function (a) {
            var opt = document.createElement('option');
            opt.value = a.id;
            var label = a.name + ' · ' + a.owner;
            label += ' · ' + (typeLabels[a.type] || a.type);
            label += ' · ' + a.currency;
            if (a.overdraft > 0) {
                label += ' (découvert : ' + a.overdraft.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' ' + a.currency + ')';
            }
            opt.textContent = label;
            if (String(a.id) === prev) opt.selected = true;
            select.appendChild(opt);
        });

        if (filtered.length === 0 && (uid !== null || type !== null)) {
            hint.textContent = 'Aucun compte ne correspond aux filtres sélectionnés.';
            hint.style.color = 'var(--danger)';
            hint.style.display = '';
        } else if (filtered.length > 0 && (uid !== null || type !== null)) {
            hint.textContent = filtered.length + ' compte' + (filtered.length > 1 ? 's' : '') + ' affiché' + (filtered.length > 1 ? 's' : '') + '.';
            hint.style.color = 'var(--text-muted)';
            hint.style.display = '';
        } else {
            hint.style.display = 'none';
        }
    }

    filterType.addEventListener('change', rebuildSelect);

    // Initialisation
    rebuildSelect();
})();
</script>

<!-- ── Liste des autorisations ───────────────────────────────────────────── -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <h3 style="margin:0;"><i class="bi bi-list-ul"></i> Toutes les autorisations</h3>
        <span class="text-muted" style="font-size:0.85rem;"><?= count($authorizations) ?> entrée(s)</span>
    </div>

    <?php if (empty($authorizations)): ?>
        <div class="card-body" style="text-align:center;color:var(--text-muted);padding:2rem;">
            <i class="bi bi-shield-check" style="font-size:2rem;"></i>
            <p style="margin-top:0.5rem;">Aucune autorisation enregistrée.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Compte</th>
                        <th>Limite supplémentaire</th>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Modérateur</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authorizations as $a): ?>
                        <?php
                            $st     = $a['status'];
                            $badge  = $statusLabels[$st] ?? ['label' => $st, 'class' => 'badge-secondary'];
                            $isActive = $st === 'active' || $st === 'pending';
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.8rem;">#<?= (int) $a['id'] ?></td>
                            <td>
                                <a href="/accounts/<?= (int) $a['account_id'] ?>">
                                    <?= e($a['account_name'] ?? 'Compte #' . $a['account_id']) ?>
                                </a>
                                <?php if (!empty($a['account_overdraft']) && (float) $a['account_overdraft'] > 0): ?>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">
                                        Découvert de base : <?= fmt_amount_smart((float) $a['account_overdraft']) ?> <?= e($a['account_currency'] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--primary);">
                                    + <?= fmt_amount_smart((float) $a['extra_limit']) ?> <?= e($a['account_currency'] ?? '') ?>
                                </strong>
                            </td>
                            <td style="white-space:nowrap;">
                                <?= e(date('d/m/Y', strtotime($a['start_date']))) ?>
                                →
                                <?= $a['end_date'] ? e(date('d/m/Y', strtotime($a['end_date']))) : '<span style="color:var(--text-muted);">Sans fin</span>' ?>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                title="<?= e($a['reason']) ?>">
                                <?= e($a['reason'] ?: '—') ?>
                            </td>
                            <td><?= e($a['moderator_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                                <?php if ($st === 'revoked'): ?>
                                    <div style="font-size:0.73rem;color:var(--text-muted);">
                                        <?= e(date('d/m/Y H:i', strtotime($a['revoked_at']))) ?>
                                        <?= !empty($a['revoker_name']) ? 'par ' . e($a['revoker_name']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($st === 'active' || $st === 'pending'): ?>
                                    <form method="POST"
                                          action="/moderation/overdraft-authorizations/<?= (int) $a['id'] ?>/revoke"
                                          onsubmit="return confirm('Révoquer cette autorisation ?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-x-circle"></i> Révoquer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.82rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
