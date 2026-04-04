<?php
/** @var array  $ownAccounts    Comptes personnels de l'utilisateur connecté */
/** @var array  $sharedAccounts Comptes partagés avec l'utilisateur connecté */
/** @var int|null $preselect   ID du compte pré-sélectionné comme émetteur */
/** @var bool   $isModerator   L'utilisateur est-il modérateur ? */
/** @var string|null $allAccountsJson JSON de tous les comptes (modérateur) */
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
                        <i class="bi bi-arrow-left-right"></i> Effectuer le virement
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
                        <i class="bi bi-arrow-left-right"></i> Effectuer le virement
                    </button>
                </form>

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
            submitBtn.disabled = sameAcc || fundErr;
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

            submitM.disabled = sameAcc || frozen || fundErr;
        }

        if (filterUser) filterUser.addEventListener('change', rebuildSelects);
        if (filterType) filterType.addEventListener('change', rebuildSelects);
        modFrom.addEventListener('change', function () { updateFromInfoM(); checkMod(); });
        modTo.addEventListener('change', checkMod);
        if (amountMEl) amountMEl.addEventListener('input', checkMod);

        rebuildSelects();
    }

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
