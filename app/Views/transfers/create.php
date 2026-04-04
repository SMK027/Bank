<?php
/** @var array  $ownAccounts    Comptes personnels de l'utilisateur connecté */
/** @var array  $sharedAccounts Comptes partagés avec l'utilisateur connecté */
/** @var int|null $preselect   ID du compte pré-sélectionné comme émetteur */
/** @var bool   $isModerator   L'utilisateur est-il modérateur ? */
/** @var string|null $allAccountsJson JSON de tous les comptes (modérateur) */
/** @var string|null $transfersJson   JSON des virements enrichis (modérateur) */
/** @var array  $allUsers      Tous les utilisateurs (modérateur) */
/** @var array  $accountTypes  Account::TYPES */
/** @var string $activeTab     'personal' | 'moderation' */

$isMod     = $isModerator ?? false;
$activeTab = $activeTab ?? 'personal';
$allAccounts = $isMod ? ($allAccountsJson ?? '[]') : '[]';
$personalAccounts = array_merge($ownAccounts, $sharedAccounts);
?>

<div class="auth-container" style="max-width:600px">
    <div class="card">
        <div class="card-body">
            <h2 style="margin-bottom:1.25rem">
                <i class="bi bi-arrow-left-right"></i> Virement entre comptes
            </h2>

            <?php if ($isMod): ?>
            <!-- ─── Onglets modérateur ──────────────────────────────────── -->
            <div style="display:flex; border-bottom:2px solid var(--border-color); margin-bottom:1.5rem; gap:0;">
                <button type="button" id="tab-btn-personal"
                        onclick="switchTransferTab('personal')"
                        class="transfer-tab-btn <?= $activeTab === 'personal' ? 'active' : '' ?>"
                        style="flex:1; padding:0.6rem 1rem; background:none; border:none;
                               border-bottom:3px solid <?= $activeTab === 'personal' ? 'var(--primary)' : 'transparent' ?>;
                               color:<?= $activeTab === 'personal' ? 'var(--primary)' : 'var(--text-muted)' ?>;
                               font-weight:500; cursor:pointer; transition:all .15s; margin-bottom:-2px;">
                    <i class="bi bi-person"></i> Personnel
                </button>
                <button type="button" id="tab-btn-moderation"
                        onclick="switchTransferTab('moderation')"
                        class="transfer-tab-btn <?= $activeTab === 'moderation' ? 'active' : '' ?>"
                        style="flex:1; padding:0.6rem 1rem; background:none; border:none;
                               border-bottom:3px solid <?= $activeTab === 'moderation' ? 'var(--primary)' : 'transparent' ?>;
                               color:<?= $activeTab === 'moderation' ? 'var(--primary)' : 'var(--text-muted)' ?>;
                               font-weight:500; cursor:pointer; transition:all .15s; margin-bottom:-2px;">
                    <i class="bi bi-shield-check"></i> Modération
                </button>
            </div>
            <?php endif; ?>

            <!-- ════════════════════════════════════════════════════════════
                 ONGLET PERSONNEL
                 ════════════════════════════════════════════════════════════ -->
            <div id="tab-personal" style="<?= ($isMod && $activeTab !== 'personal') ? 'display:none' : '' ?>">

                <?php if (count($personalAccounts) < 2): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Vous devez avoir accès à au moins deux comptes pour effectuer un virement.
                    </div>
                <?php else: ?>

                <form method="POST" action="/transfers/create" id="form-personal">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="personal">

                    <!-- Compte émetteur -->
                    <div class="form-group">
                        <label for="from_account_id" class="form-label">
                            <i class="bi bi-arrow-up-circle text-danger"></i> Compte émetteur
                        </label>
                        <select id="from_account_id" name="from_account_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php if (!empty($ownAccounts)): ?>
                                <optgroup label="Mes comptes">
                                    <?php foreach ($ownAccounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>"
                                                data-balance="<?= (float) ($acc['balance'] ?? 0) ?>"
                                                data-overdraft="<?= (float) ($acc['overdraft'] ?? 0) ?>"
                                                data-no-overdraft="<?= \App\Models\Account::typeAllowsOverdraft($acc['type'] ?? 'standard') ? '0' : '1' ?>"
                                                data-currency="<?= e($acc['currency']) ?>"
                                                <?= $preselect === (int) $acc['id'] ? 'selected' : '' ?>>
                                            <?= e($acc['name']) ?>
                                            (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($sharedAccounts)): ?>
                                <optgroup label="Comptes partagés">
                                    <?php foreach ($sharedAccounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>"
                                                data-balance="<?= (float) ($acc['balance'] ?? 0) ?>"
                                                data-overdraft="<?= (float) ($acc['overdraft'] ?? 0) ?>"
                                                data-no-overdraft="<?= \App\Models\Account::typeAllowsOverdraft($acc['type'] ?? 'standard') ? '0' : '1' ?>"
                                                data-currency="<?= e($acc['currency']) ?>"
                                                <?= $preselect === (int) $acc['id'] ? 'selected' : '' ?>>
                                            <?= e($acc['name']) ?>
                                            (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div id="from-info-p" style="margin-top:0.4rem; font-size:0.82rem; display:none;">
                            Solde disponible : <strong id="from-balance-p"></strong>
                            <span id="from-od-info-p" style="display:none"> · Découvert autorisé : <strong id="from-od-p"></strong></span>
                        </div>
                    </div>

                    <!-- Compte destinataire -->
                    <div class="form-group">
                        <label for="to_account_id" class="form-label">
                            <i class="bi bi-arrow-down-circle text-success"></i> Compte destinataire
                        </label>
                        <select id="to_account_id" name="to_account_id" class="form-control" required>
                            <option value="">— Sélectionner —</option>
                            <?php if (!empty($ownAccounts)): ?>
                                <optgroup label="Mes comptes">
                                    <?php foreach ($ownAccounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>">
                                            <?= e($acc['name']) ?>
                                            (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($sharedAccounts)): ?>
                                <optgroup label="Comptes partagés">
                                    <?php foreach ($sharedAccounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>">
                                            <?= e($acc['name']) ?>
                                            (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Montant + Motif -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount-p" class="form-label">Montant</label>
                            <input type="number" id="amount-p" name="amount" class="form-control"
                                   placeholder="0.00" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="motif-p" class="form-label">Motif <span class="text-muted">(optionnel)</span></label>
                            <input type="text" id="motif-p" name="motif" class="form-control"
                                   placeholder="Ex : Remboursement loyer">
                        </div>
                    </div>


                    <!-- Planification -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                            <button type="button" id="sched-btn-now-p"
                                    onclick="setSchedMode('p','now')"
                                    class="btn btn-sm btn-primary"
                                    style="flex:1;">
                                <i class="bi bi-lightning-charge"></i> Instantané
                            </button>
                            <button type="button" id="sched-btn-later-p"
                                    onclick="setSchedMode('p','later')"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="flex:1;">
                                <i class="bi bi-calendar-event"></i> Planifié
                            </button>
                        </div>
                        <div id="sched-date-p" style="display:none;">
                            <label for="scheduled_at-p" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                <i class="bi bi-clock"></i> Date d'exécution
                            </label>
                            <input type="datetime-local" id="scheduled_at-p" name="scheduled_at"
                                   class="form-control">
                            <div id="warn-p-date" class="alert alert-warning" style="display:none; margin-top:0.4rem; padding:0.5rem 0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="warn-p-date-text"></span>
                            </div>
                        </div>
                    </div>

                    <div id="warn-p" style="display:none; margin-bottom:0.75rem;">
                        <div id="warn-p-same" class="alert alert-warning" style="display:none;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Le compte émetteur et le compte destinataire doivent être différents.
                        </div>
                        <div id="warn-p-funds" class="alert alert-danger" style="display:none;">
                            <i class="bi bi-x-circle-fill"></i>
                            <span id="warn-p-funds-text"></span>
                        </div>
                    </div>

                    <button type="submit" id="submit-p" class="btn btn-primary btn-block">
                        <i class="bi bi-arrow-left-right" id="submit-p-icon"></i> <span id="submit-p-label">Effectuer le virement</span>
                    </button>
                </form>

                <?php endif; ?>
            </div><!-- /tab-personal -->

            <!-- ════════════════════════════════════════════════════════════
                 ONGLET MODÉRATION (modérateur uniquement)
                 ════════════════════════════════════════════════════════════ -->
            <?php if ($isMod): ?>
            <div id="tab-moderation" style="<?= $activeTab !== 'moderation' ? 'display:none' : '' ?>">

                <!-- Filtres de recherche -->
                <div style="background:var(--bg-secondary,#f8f9fa); border:1px solid var(--border-color); border-radius:var(--border-radius,8px); padding:1rem; margin-bottom:1.25rem;">
                    <p style="margin:0 0 0.75rem; font-weight:600; font-size:0.88rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted);">
                        <i class="bi bi-search"></i> Filtrer les comptes
                    </p>
                    <div class="form-row" style="margin-bottom:0">
                        <div class="form-group" style="margin-bottom:0">
                            <label for="filter-user" class="form-label">Utilisateur</label>
                            <select id="filter-user" class="form-control">
                                <option value="">Tous les utilisateurs</option>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label for="filter-type" class="form-label">Type de compte</label>
                            <select id="filter-type" class="form-control">
                                <option value="">Tous les types</option>
                                <?php foreach ($accountTypes as $key => $info): ?>
                                    <option value="<?= e($key) ?>"><?= e($info['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <form method="POST" action="/transfers/create" id="form-moderation">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="moderation">

                    <!-- Compte émetteur (modération) -->
                    <div class="form-group">
                        <label for="mod-from" class="form-label">
                            <i class="bi bi-arrow-up-circle text-danger"></i> Compte émetteur
                        </label>
                        <select id="mod-from" name="from_account_id" class="form-control" required>
                            <option value="">— Tous les comptes disponibles —</option>
                        </select>
                        <div id="from-info-m" style="margin-top:0.4rem; font-size:0.82rem; display:none;">
                            Solde disponible : <strong id="from-balance-m"></strong>
                            <span id="from-od-info-m" style="display:none"> · Découvert autorisé : <strong id="from-od-m"></strong></span>
                            <span id="from-frozen-m" style="display:none; color:var(--danger)"> · <i class="bi bi-snow"></i> Compte gelé</span>
                        </div>
                    </div>

                    <!-- Compte destinataire (modération) -->
                    <div class="form-group">
                        <label for="mod-to" class="form-label">
                            <i class="bi bi-arrow-down-circle text-success"></i> Compte destinataire
                        </label>
                        <select id="mod-to" name="to_account_id" class="form-control" required>
                            <option value="">— Tous les comptes disponibles —</option>
                        </select>
                    </div>

                    <!-- Montant + Motif -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount-m" class="form-label">Montant</label>
                            <input type="number" id="amount-m" name="amount" class="form-control"
                                   placeholder="0.00" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="motif-m" class="form-label">Motif <span class="text-muted">(optionnel)</span></label>
                            <input type="text" id="motif-m" name="motif" class="form-control"
                                   placeholder="Ex : Remboursement loyer">
                        </div>
                    </div>


                    <!-- Planification -->
                    <div style="margin-bottom:0.75rem;">
                        <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                            <button type="button" id="sched-btn-now-m"
                                    onclick="setSchedMode('m','now')"
                                    class="btn btn-sm btn-primary"
                                    style="flex:1;">
                                <i class="bi bi-lightning-charge"></i> Instantané
                            </button>
                            <button type="button" id="sched-btn-later-m"
                                    onclick="setSchedMode('m','later')"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="flex:1;">
                                <i class="bi bi-calendar-event"></i> Planifié
                            </button>
                        </div>
                        <div id="sched-date-m" style="display:none;">
                            <label for="scheduled_at-m" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                <i class="bi bi-clock"></i> Date d'exécution
                            </label>
                            <input type="datetime-local" id="scheduled_at-m" name="scheduled_at"
                                   class="form-control">
                            <div id="warn-m-date" class="alert alert-warning" style="display:none; margin-top:0.4rem; padding:0.5rem 0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="warn-m-date-text"></span>
                            </div>
                        </div>
                    </div>

                    <div id="warn-m" style="display:none; margin-bottom:0.75rem;">
                        <div id="warn-m-same" class="alert alert-warning" style="display:none;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Le compte émetteur et le compte destinataire doivent être différents.
                        </div>
                        <div id="warn-m-frozen" class="alert alert-danger" style="display:none;">
                            <i class="bi bi-snow"></i>
                            <span>Le compte émetteur est gelé — les virements sortants sont bloqués.</span>
                        </div>
                        <div id="warn-m-funds" class="alert alert-danger" style="display:none;">
                            <i class="bi bi-x-circle-fill"></i>
                            <span id="warn-m-funds-text"></span>
                        </div>
                    </div>

                    <button type="submit" id="submit-m" class="btn btn-primary btn-block">
                        <i class="bi bi-arrow-left-right" id="submit-m-icon"></i> <span id="submit-m-label">Effectuer le virement</span>
                    </button>
                </form>

                <?php if ($isMod && isset($transfersJson)): ?>
                <!-- ── Historique des virements (onglet modération) ── -->
                <hr style="margin:1.75rem 0 1.25rem">
                <h4 style="margin-bottom:0.9rem;font-size:1rem;font-weight:600">
                    <i class="bi bi-list-ul"></i> Historique des virements
                    <span id="transfer-count-badge" style="font-size:0.78rem;font-weight:400;color:var(--text-muted);margin-left:0.4rem"></span>
                </h4>

                <!-- Barre de filtres -->
                <div style="background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:8px;padding:0.9rem 1rem;margin-bottom:0.9rem;">
                    <?php
                    $tfData    = json_decode($transfersJson, true) ?: [];
                    $tfAuthors = [];
                    foreach ($tfData as $tf) {
                        if (!empty($tf['user_name']) && !in_array($tf['user_name'], $tfAuthors, true)) {
                            $tfAuthors[] = $tf['user_name'];
                        }
                    }
                    sort($tfAuthors);
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 0.75rem;">
                        <div>
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Auteur</label>
                            <select id="tf-author" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                                <option value="">Tous</option>
                                <?php foreach ($tfAuthors as $uname): ?>
                                    <option value="<?= e($uname) ?>"><?= e($uname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Statut</label>
                            <select id="tf-status" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                                <option value="">Tous</option>
                                <option value="success">R\u00e9ussi</option>
                                <option value="scheduled">Planifi\u00e9</option>
                                <option value="failed">\u00c9chou\u00e9</option>
                                <option value="cancelled">Annul\u00e9</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant min.</label>
                            <input type="number" id="tf-amount-min" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div>
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant max.</label>
                            <input type="number" id="tf-amount-max" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" min="0" step="0.01" placeholder="\u2014">
                        </div>
                        <div>
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Date du</label>
                            <input type="date" id="tf-date-from" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                        </div>
                        <div>
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Date au</label>
                            <input type="date" id="tf-date-to" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                        </div>
                        <div style="grid-column:1/-1">
                            <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Motif</label>
                            <input type="text" id="tf-motif" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" placeholder="Recherche libre\u2026">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:0.6rem">
                        <button type="button" onclick="resetTfFilters()" class="btn btn-outline btn-sm">
                            <i class="bi bi-x-circle"></i> R\u00e9initialiser
                        </button>
                    </div>
                </div>

                <!-- Tableau -->
                <div class="table-responsive" style="border-radius:6px;border:1px solid var(--border-color,#e2e8f0);overflow:hidden">
                    <table class="table" id="transfers-history-table" style="margin:0;font-size:0.8rem">
                        <thead>
                            <tr>
                                <th style="white-space:nowrap">#</th>
                                <th style="white-space:nowrap">Auteur</th>
                                <th style="white-space:nowrap">\u00c9metteur</th>
                                <th style="white-space:nowrap">Destinataire</th>
                                <th style="white-space:nowrap">Montant</th>
                                <th>Motif</th>
                                <th style="white-space:nowrap">Statut</th>
                                <th style="white-space:nowrap">Planifi\u00e9 le</th>
                                <th style="white-space:nowrap">Ex\u00e9cut\u00e9 le</th>
                            </tr>
                        </thead>
                        <tbody id="transfers-history-body"></tbody>
                    </table>
                </div>
                <p id="tf-empty" style="display:none;text-align:center;color:var(--text-muted);padding:1.2rem 0;font-size:0.88rem">
                    <i class="bi bi-search"></i> Aucun virement ne correspond \u00e0 vos crit\u00e8res.
                </p>

                <script>
                (function () {
                    var TRANSFERS = <?= $transfersJson ?>;

                    var STATUS_LABELS = {
                        'scheduled': 'Planifi\u00e9',
                        'success':   'R\u00e9ussi',
                        'failed':    '\u00c9chou\u00e9',
                        'cancelled': 'Annul\u00e9'
                    };
                    var STATUS_BADGES = {
                        'scheduled': 'badge-info',
                        'success':   'badge-success',
                        'failed':    'badge-danger',
                        'cancelled': 'badge-secondary'
                    };

                    function fmt(n) {
                        return Number(n).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2});
                    }
                    function fmtDate(s) {
                        if (!s) return '\u2014';
                        var d = new Date(s.replace(' ', 'T'));
                        if (isNaN(d)) return s;
                        return d.toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit', year:'numeric'})
                             + '\u00a0' + d.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
                    }
                    function dateOnly(s) {
                        return s ? String(s).substring(0, 10) : '';
                    }
                    function esc(s) {
                        return String(s || '')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;');
                    }

                    function renderTransfers(list) {
                        var tbody = document.getElementById('transfers-history-body');
                        var empty = document.getElementById('tf-empty');
                        var badge = document.getElementById('transfer-count-badge');
                        if (!tbody) return;
                        if (badge) badge.textContent = '(' + list.length + ')';
                        if (list.length === 0) {
                            tbody.innerHTML = '';
                            if (empty) empty.style.display = '';
                            return;
                        }
                        if (empty) empty.style.display = 'none';
                        var html = '';
                        list.forEach(function (t) {
                            var st   = t.status || 'success';
                            var bCls = STATUS_BADGES[st] || 'badge-secondary';
                            var bLbl = STATUS_LABELS[st] || st;
                            var motifCell = t.motif
                                ? '<span title="' + esc(t.motif) + '">' + esc(t.motif) + '</span>'
                                : '<span style="color:var(--text-muted)">\u2014</span>';
                            html += '<tr>'
                                + '<td style="color:var(--text-muted)">#' + esc(t.id) + '</td>'
                                + '<td>' + esc(t.user_name) + '</td>'
                                + '<td>' + esc(t.from_account) + '</td>'
                                + '<td>' + esc(t.to_account) + '</td>'
                                + '<td style="white-space:nowrap;font-weight:600">' + fmt(t.amount) + '</td>'
                                + '<td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + motifCell + '</td>'
                                + '<td><span class="badge ' + bCls + '">' + bLbl + '</span></td>'
                                + '<td style="white-space:nowrap;font-size:0.75rem">' + fmtDate(t.scheduled_at) + '</td>'
                                + '<td style="white-space:nowrap;font-size:0.75rem">' + fmtDate(t.executed_at) + '</td>'
                                + '</tr>';
                        });
                        tbody.innerHTML = html;
                    }

                    function filterTransfers() {
                        var author   = (document.getElementById('tf-author')     || {value:''}).value;
                        var status   = (document.getElementById('tf-status')     || {value:''}).value;
                        var amtMinEl = document.getElementById('tf-amount-min');
                        var amtMaxEl = document.getElementById('tf-amount-max');
                        var amtMin   = amtMinEl && amtMinEl.value !== '' ? parseFloat(amtMinEl.value) : -Infinity;
                        var amtMax   = amtMaxEl && amtMaxEl.value !== '' ? parseFloat(amtMaxEl.value) :  Infinity;
                        var dateFrom = (document.getElementById('tf-date-from')  || {value:''}).value;
                        var dateTo   = (document.getElementById('tf-date-to')    || {value:''}).value;
                        var motif    = ((document.getElementById('tf-motif')     || {value:''}).value || '').toLowerCase().trim();

                        var filtered = TRANSFERS.filter(function (t) {
                            if (author && t.user_name !== author)                      return false;
                            if (status && t.status    !== status)                      return false;
                            if (t.amount < amtMin)                                     return false;
                            if (amtMax !== Infinity && t.amount > amtMax)              return false;
                            var d = dateOnly(t.created_at);
                            if (dateFrom && d < dateFrom)                              return false;
                            if (dateTo   && d > dateTo)                                return false;
                            if (motif && (t.motif || '').toLowerCase().indexOf(motif) === -1) return false;
                            return true;
                        });
                        renderTransfers(filtered);
                    }

                    ['tf-author','tf-status','tf-amount-min','tf-amount-max','tf-date-from','tf-date-to'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) el.addEventListener('change', filterTransfers);
                    });
                    var motifEl = document.getElementById('tf-motif');
                    if (motifEl) {
                        motifEl.addEventListener('input',  filterTransfers);
                        motifEl.addEventListener('change', filterTransfers);
                    }

                    window.resetTfFilters = function () {
                        ['tf-author','tf-status','tf-amount-min','tf-amount-max','tf-date-from','tf-date-to','tf-motif'].forEach(function (id) {
                            var el = document.getElementById(id);
                            if (el) el.value = '';
                        });
                        filterTransfers();
                    };

                    renderTransfers(TRANSFERS);
                })();
                </script>
                <?php endif; ?>

            </div><!-- /tab-moderation -->
            <?php endif; ?>

            <p class="text-center text-muted text-small mt-2">
                <a href="/dashboard"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a>
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    /* État des modes de planification — déclaré en tête pour éviter un TypeError
       lors de l'appel initial de checkPersonal / checkMod */
    var schedModes = { p: 'now', m: 'now' };

    /* ─── ONGLET PERSONNEL ─────────────────────────────────────────────── */
    var fromEl   = document.getElementById('from_account_id');
    var toEl     = document.getElementById('to_account_id');
    var amountEl = document.getElementById('amount-p');
    if (fromEl && toEl && amountEl) {
        var fromInfo   = document.getElementById('from-info-p');
        var fromBalEl  = document.getElementById('from-balance-p');
        var fromOdInfo = document.getElementById('from-od-info-p');
        var fromOdEl   = document.getElementById('from-od-p');
        var warnSame   = document.getElementById('warn-p-same');
        var warnFunds  = document.getElementById('warn-p-funds');
        var warnText   = document.getElementById('warn-p-funds-text');
        var submitBtn  = document.getElementById('submit-p');

        function fmt(n, cur) {
            return n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '\u00a0' + (cur || '');
        }

        function getFromData() {
            var opt = fromEl.options[fromEl.selectedIndex];
            if (!opt || !opt.value) return null;
            return {
                balance:     parseFloat(opt.dataset.balance)   || 0,
                overdraft:   parseFloat(opt.dataset.overdraft) || 0,
                noOverdraft: opt.dataset.noOverdraft === '1',
                currency:    opt.dataset.currency || ''
            };
        }

        function updateFromInfo() {
            var d = getFromData();
            if (!d) { fromInfo.style.display = 'none'; return; }
            fromInfo.style.display = 'block';
            fromBalEl.textContent  = fmt(d.balance, d.currency);
            fromBalEl.style.color  = d.balance >= 0 ? 'var(--success-dark)' : 'var(--danger)';
            if (d.overdraft > 0) {
                fromOdInfo.style.display = 'inline';
                fromOdEl.textContent = fmt(d.overdraft, d.currency);
            } else {
                fromOdInfo.style.display = 'none';
            }
        }

        function checkPersonal() {
            var d      = getFromData();
            var sameAcc = fromEl.value && toEl.value && fromEl.value === toEl.value;
            var fundErr = false;
            warnSame.style.display  = 'none';
            warnFunds.style.display = 'none';
            if (sameAcc) warnSame.style.display = 'block';
            if (d && (parseFloat(amountEl.value) || 0) > 0) {
                var newBal  = d.balance - (parseFloat(amountEl.value) || 0);
                var exceeds = newBal < -d.overdraft;
                if (exceeds) {
                    fundErr = true;
                    if (d.noOverdraft) {
                        warnText.textContent = 'Impossible : ce compte ne permet pas le solde négatif. Solde disponible : ' + fmt(d.balance, d.currency) + '.';
                    } else {
                        warnText.textContent = 'Fonds insuffisants. Solde prévu : ' + fmt(newBal, d.currency) + ' (dépassement du découvert de ' + fmt(d.overdraft, d.currency) + ').';
                    }
                    warnFunds.style.display = 'block';
                }
            }
            var dateErr = !checkSchedDate('p');
            submitBtn.disabled = sameAcc || fundErr || dateErr;
        }

        fromEl.addEventListener('change', function () { updateFromInfo(); checkPersonal(); });
        toEl.addEventListener('change', checkPersonal);
        amountEl.addEventListener('input', checkPersonal);
        updateFromInfo();
        checkPersonal();
    }

    /* ─── ONGLET MODÉRATION ────────────────────────────────────────────── */
    var ALL_ACCOUNTS = <?= $allAccounts ?>;

    var filterUser   = document.getElementById('filter-user');
    var filterType   = document.getElementById('filter-type');
    var modFrom      = document.getElementById('mod-from');
    var modTo        = document.getElementById('mod-to');
    var amountMEl    = document.getElementById('amount-m');

    if (modFrom && modTo) {
        var fromInfoM   = document.getElementById('from-info-m');
        var fromBalM    = document.getElementById('from-balance-m');
        var fromOdInfoM = document.getElementById('from-od-info-m');
        var fromOdM     = document.getElementById('from-od-m');
        var fromFrozenM = document.getElementById('from-frozen-m');
        var warnMSame   = document.getElementById('warn-m-same');
        var warnMFrozen = document.getElementById('warn-m-frozen');
        var warnMFunds  = document.getElementById('warn-m-funds');
        var warnMText   = document.getElementById('warn-m-funds-text');
        var submitM     = document.getElementById('submit-m');

        function fmt2(n, cur) {
            return n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '\u00a0' + (cur || '');
        }

        function filteredAccounts() {
            var uid  = filterUser ? filterUser.value : '';
            var typ  = filterType ? filterType.value : '';
            return ALL_ACCOUNTS.filter(function (a) {
                if (uid && String(a.user_id) !== uid) return false;
                if (typ && a.type !== typ) return false;
                return true;
            });
        }

        function buildOption(a) {
            var opt = document.createElement('option');
            opt.value = a.id;
            var frozenLabel = a.frozen ? ' 🔒 Gelé' : '';
            var balLabel = a.balance.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            opt.textContent = a.user_name + ' — ' + a.name + ' (' + balLabel + '\u00a0' + a.currency + ')' + frozenLabel;
            opt.dataset.balance     = a.balance;
            opt.dataset.overdraft   = a.overdraft;
            opt.dataset.noOverdraft = a.no_overdraft ? '1' : '0';
            opt.dataset.currency    = a.currency;
            opt.dataset.frozen      = a.frozen ? '1' : '0';
            if (a.frozen) opt.style.color = 'var(--text-muted)';
            return opt;
        }

        function addOutOfFilterOption(selectEl, prevId) {
            if (!prevId) return;
            if (selectEl.querySelector('option[value="' + prevId + '"]')) return;
            var acc = ALL_ACCOUNTS.find(function (a) { return String(a.id) === String(prevId); });
            if (!acc) return;
            var grp = document.createElement('optgroup');
            grp.label = 'Sélection actuelle (hors filtre)';
            var opt = buildOption(acc);
            grp.appendChild(opt);
            selectEl.appendChild(grp);
        }

        function rebuildSelects() {
            var accounts = filteredAccounts();
            var prevFrom = modFrom.value;
            var prevTo   = modTo.value;

            modFrom.innerHTML = '<option value="">\u2014 S\u00e9lectionner un compte \u2014</option>';
            modTo.innerHTML   = '<option value="">\u2014 S\u00e9lectionner un compte \u2014</option>';

            accounts.forEach(function (a) {
                modFrom.appendChild(buildOption(a));
                modTo.appendChild(buildOption(a));
            });

            // Si la sélection précédente est hors filtre, la proposer quand même
            addOutOfFilterOption(modFrom, prevFrom);
            addOutOfFilterOption(modTo, prevTo);

            if (prevFrom) modFrom.value = prevFrom;
            if (prevTo)   modTo.value   = prevTo;

            updateFromInfoM();
            checkMod();
        }

        function getModFromData() {
            var opt = modFrom.options[modFrom.selectedIndex];
            if (!opt || !opt.value) return null;
            return {
                balance:     parseFloat(opt.dataset.balance)   || 0,
                overdraft:   parseFloat(opt.dataset.overdraft) || 0,
                noOverdraft: opt.dataset.noOverdraft === '1',
                currency:    opt.dataset.currency || '',
                frozen:      opt.dataset.frozen === '1'
            };
        }

        function updateFromInfoM() {
            var d = getModFromData();
            if (!d) { fromInfoM.style.display = 'none'; return; }
            fromInfoM.style.display = 'block';
            fromBalM.textContent = fmt2(d.balance, d.currency);
            fromBalM.style.color = d.balance >= 0 ? 'var(--success-dark)' : 'var(--danger)';
            fromOdInfoM.style.display = d.overdraft > 0 ? 'inline' : 'none';
            if (d.overdraft > 0) fromOdM.textContent = fmt2(d.overdraft, d.currency);
            fromFrozenM.style.display = d.frozen ? 'inline' : 'none';
        }

        function checkMod() {
            var d       = getModFromData();
            var sameAcc = modFrom.value && modTo.value && modFrom.value === modTo.value;
            var frozen  = d && d.frozen;
            var fundErr = false;

            warnMSame.style.display   = 'none';
            warnMFrozen.style.display = 'none';
            warnMFunds.style.display  = 'none';

            if (sameAcc) warnMSame.style.display = 'block';
            if (frozen)  warnMFrozen.style.display = 'block';

            if (d && !frozen && (parseFloat(amountMEl.value) || 0) > 0) {
                var newBal  = d.balance - (parseFloat(amountMEl.value) || 0);
                var exceeds = newBal < -d.overdraft;
                if (exceeds) {
                    fundErr = true;
                    if (d.noOverdraft) {
                        warnMText.textContent = 'Impossible : ce compte ne permet pas le solde négatif. Solde disponible : ' + fmt2(d.balance, d.currency) + '.';
                    } else {
                        warnMText.textContent = 'Fonds insuffisants. Solde prévu : ' + fmt2(newBal, d.currency) + ' (dépassement du découvert de ' + fmt2(d.overdraft, d.currency) + ').';
                    }
                    warnMFunds.style.display = 'block';
                }
            }

            var dateMErr = !checkSchedDate('m');
            submitM.disabled = sameAcc || frozen || fundErr || dateMErr;
        }

        if (filterUser) filterUser.addEventListener('change', rebuildSelects);
        if (filterType) filterType.addEventListener('change', rebuildSelects);
        modFrom.addEventListener('change', function () { updateFromInfoM(); checkMod(); });
        modTo.addEventListener('change', checkMod);
        if (amountMEl) amountMEl.addEventListener('input', checkMod);

        rebuildSelects();
    }


    /* ─── Planification (toggle instantané / planifié) ──────────────────── */
    window.setSchedMode = function (form, mode) {
        schedModes[form] = mode;
        var dateDiv   = document.getElementById('sched-date-' + form);
        var btnNow    = document.getElementById('sched-btn-now-' + form);
        var btnLater  = document.getElementById('sched-btn-later-' + form);
        var labelEl   = document.getElementById('submit-' + form + '-label');
        if (!dateDiv) return;

        if (mode === 'later') {
            dateDiv.style.display = '';
            btnNow.className   = btnNow.className.replace('btn-primary', 'btn-outline-secondary');
            btnLater.className = btnLater.className.replace('btn-outline-secondary', 'btn-primary');
            if (labelEl) labelEl.textContent = 'Planifier le virement';
        } else {
            dateDiv.style.display = 'none';
            var inp = document.getElementById('scheduled_at-' + form);
            if (inp) inp.value = '';
            btnNow.className   = btnNow.className.replace('btn-outline-secondary', 'btn-primary');
            btnLater.className = btnLater.className.replace('btn-primary', 'btn-outline-secondary');
            if (labelEl) labelEl.textContent = 'Effectuer le virement';
        }

        // Re-déclencher la validation
        if (form === 'p' && typeof checkPersonal === 'function') checkPersonal();
        if (form === 'm' && typeof checkMod      === 'function') checkMod();
    };

    // Validation date dans checkPersonal et checkMod (ajoutée via patch)
    function checkSchedDate(form) {
        var inp     = document.getElementById('scheduled_at-' + form);
        var warnDiv = document.getElementById('warn-' + form + '-date');
        var warnTxt = document.getElementById('warn-' + form + '-date-text');
        if (!inp || !warnDiv) return true; // pas de champ = pas d'erreur
        if (schedModes[form] !== 'later') {
            warnDiv.style.display = 'none';
            return true;
        }
        if (!inp.value) {
            warnDiv.style.display = 'block';
            warnTxt.textContent   = 'Veuillez saisir une date d\'exécution.';
            return false;
        }
        var ts = new Date(inp.value).getTime();
        if (ts <= Date.now()) {
            warnDiv.style.display = 'block';
            warnTxt.textContent   = 'La date doit être dans le futur.';
            return false;
        }
        warnDiv.style.display = 'none';
        return true;
    }

    // Patch checkPersonal pour inclure la validation de date
    (function () {
        var fromEl   = document.getElementById('from_account_id');
        var schedInpP = document.getElementById('scheduled_at-p');
        if (schedInpP) {
            schedInpP.addEventListener('input', function () {
                if (fromEl) fromEl.dispatchEvent(new Event('change'));
            });
        }
        var schedInpM = document.getElementById('scheduled_at-m');
        if (schedInpM) {
            schedInpM.addEventListener('input', function () {
                var modFrom = document.getElementById('mod-from');
                if (modFrom) modFrom.dispatchEvent(new Event('change'));
            });
        }
    })();

    /* ─── Switcher d'onglets ───────────────────────────────────────────── */
    window.switchTransferTab = function (tab) {
        var tabs = ['personal', 'moderation'];
        tabs.forEach(function (t) {
            var panel = document.getElementById('tab-' + t);
            var btn   = document.getElementById('tab-btn-' + t);
            if (!panel || !btn) return;
            var active = (t === tab);
            panel.style.display     = active ? '' : 'none';
            btn.style.borderBottom  = active ? '3px solid var(--primary)' : '3px solid transparent';
            btn.style.color         = active ? 'var(--primary)' : 'var(--text-muted)';
        });
    };
})();
</script>
