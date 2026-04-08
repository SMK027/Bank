<div class="page-header">
    <div>
        <h1><i class="bi bi-percent"></i> Taux d'intérêt maximum — Épargne</h1>
        <p class="page-description">Définissez le taux annuel brut <strong>maximum</strong> autorisé par type de compte. Les utilisateurs fixent leur propre taux dans la configuration de leur compte, dans la limite de ce plafond.</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/recurring-transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Virements récurrents</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-percent"></i> Taux d'intérêt</span>
    </div>
</div>

<?php
use App\Models\Account;
$typeLabels = array_column(Account::TYPES, 'label', null);
// $typeLabels est indexé par clé de tableau, on refait la map correctement
$typeMap = [];
foreach (Account::TYPES as $key => $def) { $typeMap[$key] = $def['label']; }
?>

<!-- Taux actuels par type -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-gear"></i> Taux actuels par type de compte</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="table">
            <thead>
                <tr>
                    <th>Type de compte</th>
                    <th>Taux maximum actif</th>
                    <th>Actif depuis</th>
                    <th>Par</th>
                    <th>Nouveau taux (%)</th>
                    <th style="width:150px;">Date d'effet</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eligibleTypes as $accountType): ?>
                <?php $current = $allRates[$accountType] ?? null; ?>
                <tr>
                    <td>
                        <strong><?= e($typeMap[$accountType] ?? $accountType) ?></strong>
                        <code style="font-size:0.75rem;margin-left:0.4rem;color:var(--text-muted,#6b7280);"><?= e($accountType) ?></code>
                    </td>
                    <td>
                        <?php if ($current): ?>
                            <span style="font-size:1.1rem;font-weight:700;color:var(--success,#16a34a);">
                                <?= number_format((float) $current['rate'] * 100, 2, ',', ' ') ?> %
                            </span>
                        <?php else: ?>
                            <span class="text-muted"><i class="bi bi-dash"></i> Non configuré (aucune limite)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= $current ? date('d/m/Y à H\hi', strtotime($current['created_at'])) : '—' ?>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= $current ? e($current['set_by_username'] ?? '—') : '—' ?>
                    </td>
                    <td colspan="3">
                        <form method="POST" action="/moderation/savings-rate"
                              style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_type" value="<?= e($accountType) ?>">
                            <input type="number" name="rate" class="form-control"
                                   style="padding:0.3rem 0.5rem;font-size:0.85rem;width:100px;"
                                   min="0" max="100" step="0.01" required
                                   placeholder="Ex : 3.00"
                                   value="<?= $current ? htmlspecialchars(number_format((float) $current['rate'] * 100, 2, '.', ''), ENT_QUOTES) : '' ?>">
                            <input type="date" name="effective_date" class="form-control"
                                   style="padding:0.3rem 0.5rem;font-size:0.85rem;width:145px;"
                                   min="<?= date('Y') . '-01-01' ?>"
                                   max="<?= date('Y') . '-12-31' ?>"
                                   title="Laisser vide pour appliquer immédiatement">
                            <span class="form-hint" style="font-size:0.72rem;color:var(--text-muted,#6b7280);white-space:nowrap;">
                                date vide = maintenant
                            </span>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-check-lg"></i> Enregistrer
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Intérêts en cours — aperçu indicatif -->
<div class="card" style="margin-bottom:1.5rem;border-left:4px solid #10b981;">
    <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
        <i class="bi bi-graph-up-arrow" style="color:#10b981;font-size:1.1rem;"></i>
        <h3 style="margin:0;">Intérêts en cours <?= (int) date('Y') ?> <span style="font-size:0.7em;font-weight:400;color:var(--text-muted,#6b7280);">(aperçu indicatif)</span></h3>
    </div>
    <div class="card-body">
        <p style="margin:0 0 0.8rem;color:var(--text-muted,#6b7280);font-size:0.85rem;">
            Estimation des intérêts accumulés depuis le 1<sup>er</sup> janvier <?= (int) date('Y') ?>
            jusqu'à aujourd'hui, calculée au prorata temporis (TWAB) pour chaque compte ayant un taux configuré.
            <strong>Aucune écriture en base — valeur purement indicative.</strong>
        </p>

        <!-- Filtre utilisateurs + bouton de calcul -->
        <form id="preview-form" method="GET" action="/moderation/savings-rate" style="margin-bottom:0.8rem;">
            <input type="hidden" name="preview" value="1">
            <?php if ($filterType): ?>
            <input type="hidden" name="type" value="<?= e($filterType) ?>">
            <?php endif; ?>

            <div style="display:flex;gap:0.5rem;align-items:flex-start;flex-wrap:wrap;">
                <!-- Champ autocomplete utilisateurs -->
                <div style="position:relative;flex:1;min-width:220px;max-width:380px;">
                    <input type="text" id="user-search-input" autocomplete="off"
                           placeholder="Filtrer par utilisateur…"
                           class="form-control"
                           style="padding:0.4rem 0.6rem;font-size:0.85rem;">
                    <ul id="user-search-dropdown"
                        style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;
                               background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);
                               border-radius:0.375rem;box-shadow:0 4px 12px rgba(0,0,0,.1);
                               list-style:none;margin:0.2rem 0 0;padding:0;max-height:200px;overflow-y:auto;">
                    </ul>
                </div>

                <!-- Badges des utilisateurs sélectionnés + champs cachés -->
                <div id="user-filter-tags" style="display:flex;gap:0.35rem;flex-wrap:wrap;align-items:center;">
                    <?php foreach ($filterUserIds as $uid): ?>
                    <span class="badge" data-uid="<?= (int) $uid ?>"
                          style="display:inline-flex;align-items:center;gap:0.25rem;
                                 background:var(--primary,#6366f1);color:#fff;
                                 padding:0.25rem 0.55rem;border-radius:999px;font-size:0.8rem;">
                        <?= e($filterUserLabels[$uid] ?? ('#' . $uid)) ?>
                        <button type="button" onclick="removeUser(<?= (int) $uid ?>)"
                                title="Retirer" aria-label="Retirer"
                                style="background:none;border:none;color:#fff;cursor:pointer;
                                       padding:0;line-height:1;font-size:1rem;">&times;</button>
                        <input type="hidden" name="user_ids[]" value="<?= (int) $uid ?>">
                    </span>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;">
                    <i class="bi bi-arrow-clockwise"></i>
                    <?= empty($filterUserIds) ? 'Calculer pour tous' : 'Actualiser l\'aperçu' ?>
                </button>
                <?php if (!empty($filterUserIds)): ?>
                <a href="/moderation/savings-rate?preview=1<?= $filterType ? '&type=' . urlencode($filterType) : '' ?>"
                   class="btn btn-outline btn-sm" style="white-space:nowrap;">
                    <i class="bi bi-x-circle"></i> Tous les comptes
                </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($previewResults === null): ?>
            <p class="text-muted" style="font-size:0.88rem;margin:0;">
                <i class="bi bi-info-circle"></i> Cliquez sur « Calculer pour tous » pour afficher l'aperçu.
            </p>
        <?php elseif (empty($previewResults)): ?>
            <p class="text-muted" style="font-size:0.88rem;margin:0;">
                <i class="bi bi-dash-circle"></i> Aucun compte éligible avec un taux configuré<?= !empty($filterUserIds) ? ' pour les utilisateurs sélectionnés' : '' ?>.
            </p>
        <?php else: ?>
            <div class="table-responsive">
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Titulaire</th>
                        <th>Type</th>
                        <th>Taux</th>
                        <th style="text-align:right;">Intérêts en cours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewResults as $row): ?>
                    <tr>
                        <td>
                            <a href="/accounts/<?= (int) $row['account_id'] ?>"><?= e($row['account_name']) ?></a>
                        </td>
                        <td class="text-muted"><?= e($row['username']) ?></td>
                        <td><code style="font-size:0.75rem;"><?= e($row['account_type']) ?></code></td>
                        <td><?= number_format($row['rate'] * 100, 2, ',', ' ') ?> %</td>
                        <td style="text-align:right;font-weight:600;color:var(--success,#16a34a);">
                            <?php if ($row['accrued'] > 0): ?>
                                +<?= number_format($row['accrued'], 2, ',', ' ') ?> <?= e($row['currency']) ?>
                            <?php else: ?>
                                <span class="text-muted">0,00</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php
                    $total = array_sum(array_column($previewResults, 'accrued'));
                    $currencies = array_unique(array_column($previewResults, 'currency'));
                ?>
                <?php if (count($currencies) === 1): ?>
                <tfoot>
                    <tr style="font-weight:700;border-top:2px solid var(--border-color,#e5e7eb);">
                        <td colspan="4" style="text-align:right;">Total</td>
                        <td style="text-align:right;color:var(--success,#16a34a);">
                            +<?= number_format($total, 2, ',', ' ') ?> <?= e($currencies[0]) ?>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            </div>
            <p style="margin:0.6rem 0 0;font-size:0.78rem;color:var(--text-muted,#6b7280);">
                <i class="bi bi-clock"></i> Calculé le <?= date('d/m/Y à H\hi') ?>
                <?php if (!empty($filterUserIds)): ?>
                — filtre : <?= e(implode(', ', array_values($filterUserLabels))) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const input    = document.getElementById('user-search-input');
    const dropdown = document.getElementById('user-search-dropdown');
    const tagsDiv  = document.getElementById('user-filter-tags');
    let debounce;

    // IDs déjà sélectionnés (pré-remplis depuis PHP)
    const selected = new Set(
        [...tagsDiv.querySelectorAll('[data-uid]')].map(el => parseInt(el.dataset.uid, 10))
    );

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        const q = input.value.trim();
        if (q.length < 2) { hideDropdown(); return; }
        debounce = setTimeout(() => fetchUsers(q), 220);
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') hideDropdown();
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) hideDropdown();
    });

    function fetchUsers(q) {
        fetch('/moderation/users/search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(rows => {
                dropdown.innerHTML = '';
                const filtered = rows.filter(r => !selected.has(r.id));
                if (!filtered.length) { hideDropdown(); return; }
                filtered.forEach(user => {
                    const li = document.createElement('li');
                    li.textContent = user.label;
                    li.style.cssText = 'padding:0.45rem 0.75rem;cursor:pointer;font-size:0.85rem;';
                    li.addEventListener('mouseenter', () => li.style.background = 'var(--hover-bg,#f3f4f6)');
                    li.addEventListener('mouseleave', () => li.style.background = '');
                    li.addEventListener('mousedown', e => { e.preventDefault(); addUser(user); });
                    dropdown.appendChild(li);
                });
                dropdown.style.display = 'block';
            })
            .catch(() => hideDropdown());
    }

    function addUser(user) {
        if (selected.has(user.id)) { hideDropdown(); return; }
        selected.add(user.id);

        const span = document.createElement('span');
        span.className = 'badge';
        span.dataset.uid = user.id;
        span.style.cssText = 'display:inline-flex;align-items:center;gap:0.25rem;background:var(--primary,#6366f1);color:#fff;padding:0.25rem 0.55rem;border-radius:999px;font-size:0.8rem;';
        span.innerHTML =
            escHtml(user.username) +
            `<button type="button" title="Retirer" aria-label="Retirer" onclick="removeUser(${user.id})" style="background:none;border:none;color:#fff;cursor:pointer;padding:0;line-height:1;font-size:1rem;">&times;</button>` +
            `<input type="hidden" name="user_ids[]" value="${user.id}">`;
        tagsDiv.appendChild(span);

        input.value = '';
        hideDropdown();
    }

    window.removeUser = function (uid) {
        selected.delete(uid);
        const el = tagsDiv.querySelector('[data-uid="' + uid + '"]');
        if (el) el.remove();
    };

    function hideDropdown() { dropdown.style.display = 'none'; }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>

<!-- Historique -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <h3 style="margin:0;"><i class="bi bi-clock-history"></i> Historique des taux</h3>
        <div style="display:flex;gap:0.4rem;align-items:center;">
            <span class="text-muted" style="font-size:0.85rem;">Filtrer :</span>
            <?php foreach (array_merge([''], $eligibleTypes) as $t): ?>
            <a href="/moderation/savings-rate<?= $t !== '' ? '?type=' . urlencode($t) : '' ?>"
               class="btn btn-sm <?= ($filterType === ($t !== '' ? $t : null)) || ($filterType === null && $t === '') ? 'btn-primary' : 'btn-outline' ?>">
                <?= $t !== '' ? e($typeMap[$t] ?? $t) : 'Tous' ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <?php if (empty($history)): ?>
            <p class="text-muted" style="padding:1rem;">Aucun historique disponible.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type de compte</th>
                        <th>Taux</th>
                        <th>Modérateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <?php $isFuture = strtotime($h['created_at']) > time(); ?>
                    <tr <?= $isFuture ? 'style="opacity:0.75;"' : '' ?>>
                        <td>
                            <?= date('d/m/Y à H\hi', strtotime($h['created_at'])) ?>
                            <?php if ($isFuture): ?>
                                <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;font-size:0.7em;margin-left:0.3rem;vertical-align:middle;">
                                    <i class="bi bi-clock"></i> planifié
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($typeMap[$h['account_type']] ?? $h['account_type']) ?>
                            <code style="font-size:0.75rem;margin-left:0.3rem;color:var(--text-muted,#6b7280);"><?= e($h['account_type']) ?></code>
                        </td>
                        <td><strong><?= number_format((float) $h['rate'] * 100, 2, ',', ' ') ?> %</strong></td>
                        <td><?= e($h['set_by_username'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

