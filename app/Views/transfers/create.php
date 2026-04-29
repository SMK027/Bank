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
                                        <option value="<?= (int) $acc['id'] ?>"
                                                data-currency="<?= e($acc['currency']) ?>">
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
                                                data-currency="<?= e($acc['currency']) ?>">
                                            <?= e($acc['name']) ?>
                                            (<?= number_format((float) ($acc['balance'] ?? 0), 2, ',', ' ') ?> <?= e($acc['currency']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div id="currency-info-p" class="alert alert-info" style="display:none; margin-top:0.5rem; padding:0.5rem 0.75rem; font-size:0.85rem;">
                            <i class="bi bi-currency-exchange"></i>
                            <span id="currency-info-p-text"></span>
                        </div>
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
                            <button type="button" id="sched-btn-recur-p"
                                    onclick="setSchedMode('p','recurring')"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="flex:1;">
                                <i class="bi bi-arrow-repeat"></i> Récurrent
                            </button>
                        </div>
                        <div id="sched-date-p" style="display:none;">
                            <label for="scheduled_at-p" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                <i class="bi bi-clock"></i> Date d'exécution
                            </label>
                            <input type="text" id="scheduled_at-p" name="scheduled_at"
                                   class="form-control" placeholder="jj/mm/aaaa hh:mm">
                            <div id="warn-p-date" class="alert alert-warning" style="display:none; margin-top:0.4rem; padding:0.5rem 0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="warn-p-date-text"></span>
                            </div>
                        </div>
                        <div id="sched-recur-p" style="display:none;">
                            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                                <div style="flex:1; min-width:180px;">
                                    <label for="first_execution_at-p" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                        <i class="bi bi-calendar-plus"></i> Date du premier virement
                                    </label>
                                    <input type="text" id="first_execution_at-p" name="first_execution_at"
                                           class="form-control"
                                           placeholder="jj/mm/aaaa hh:mm">
                                </div>
                                <div style="width:140px;">
                                    <label for="interval_days-p" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                        <i class="bi bi-arrow-clockwise"></i> Intervalle (jours)
                                    </label>
                                    <input type="number" id="interval_days-p" name="interval_days"
                                           class="form-control" min="1" step="1" placeholder="Ex : 30">
                                </div>
                            </div>
                            <div id="warn-p-recur" class="alert alert-warning" style="display:none; margin-top:0.4rem; padding:0.5rem 0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="warn-p-recur-text"></span>
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
                        <div class="form-group" style="margin-bottom:0;position:relative">
                            <label for="filter-user-search" class="form-label">Utilisateur</label>
                            <input type="text" id="filter-user-search" class="form-control"
                                   placeholder="Tous les utilisateurs…" autocomplete="off">
                            <div id="filter-user-results"
                                 style="display:none;position:absolute;z-index:400;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);top:calc(100% + 2px);left:0;"></div>
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
                        <div id="currency-info-m" class="alert alert-info" style="display:none; margin-top:0.5rem; padding:0.5rem 0.75rem; font-size:0.85rem;">
                            <i class="bi bi-currency-exchange"></i>
                            <span id="currency-info-m-text"></span>
                        </div>
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
                            <button type="button" id="sched-btn-recur-m"
                                    onclick="setSchedMode('m','recurring')"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="flex:1;">
                                <i class="bi bi-arrow-repeat"></i> Récurrent
                            </button>
                        </div>
                        <div id="sched-date-m" style="display:none;">
                            <label for="scheduled_at-m" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                <i class="bi bi-clock"></i> Date d'exécution
                            </label>
                            <input type="text" id="scheduled_at-m" name="scheduled_at"
                                   class="form-control" placeholder="jj/mm/aaaa hh:mm">
                            <div id="warn-m-date" class="alert alert-warning" style="display:none; margin-top:0.4rem; padding:0.5rem 0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="warn-m-date-text"></span>
                            </div>
                        </div>
                        <div id="sched-recur-m" style="display:none;">
                            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                                <div style="flex:1; min-width:180px;">
                                    <label for="first_execution_at-m" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                        <i class="bi bi-calendar-plus"></i> Date du premier virement
                                    </label>
                                    <input type="text" id="first_execution_at-m" name="first_execution_at"
                                           class="form-control"
                                           placeholder="jj/mm/aaaa hh:mm">
                                </div>
                                <div style="width:140px;">
                                    <label for="interval_days-m" class="form-label" style="font-size:0.85rem; color:var(--text-muted);">
                                        <i class="bi bi-arrow-clockwise"></i> Intervalle (jours)
                                    </label>
                                    <input type="number" id="interval_days-m" name="interval_days"
                                           class="form-control" min="1" step="1" placeholder="Ex : 30">
                                </div>
                            </div>
                            <div id="warn-m-recur" class="alert alert-warning" style="display:none; margin-top:0.4rem; padding:0.5rem 0.75rem;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="warn-m-recur-text"></span>
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

                <?php if ($isMod): ?>
                <div style="text-align:center;margin-top:1.5rem">
                    <a href="/moderation/transfers" class="btn btn-outline btn-sm">
                        <i class="bi bi-list-ul"></i> Voir l'historique des virements
                    </a>
                </div>
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
            // Indicateur de conversion de devise
            var curBox = document.getElementById('currency-info-p');
            var curTxt = document.getElementById('currency-info-p-text');
            if (curBox && curTxt) {
                var toOpt = toEl.options[toEl.selectedIndex];
                var toCur = toOpt ? (toOpt.dataset.currency || '') : '';
                if (d && d.currency && toCur && d.currency !== toCur && !sameAcc) {
                    curTxt.textContent = 'Conversion automatique ' + d.currency + ' → ' + toCur + ' au taux du jour. Le compte destinataire sera crédité du montant converti.';
                    curBox.style.display = 'block';
                } else {
                    curBox.style.display = 'none';
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

    var filterUserSearch = document.getElementById('filter-user-search');
    var filterUserResults = document.getElementById('filter-user-results');
    var filterUserQuery   = '';  // texte courant (correspondance partielle)
    var filterType        = document.getElementById('filter-type');
    var modFrom           = document.getElementById('mod-from');
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
            var q    = filterUserQuery.trim().toLowerCase();
            var typ  = filterType ? filterType.value : '';
            return ALL_ACCOUNTS.filter(function (a) {
                if (q && a.user_name.toLowerCase().indexOf(q) === -1) return false;
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

            // Indicateur de conversion de devise (modération)
            var curBoxM = document.getElementById('currency-info-m');
            var curTxtM = document.getElementById('currency-info-m-text');
            if (curBoxM && curTxtM) {
                var toOptM = modTo.options[modTo.selectedIndex];
                var toCurM = toOptM ? (toOptM.dataset.currency || '') : '';
                if (d && d.currency && toCurM && d.currency !== toCurM && !sameAcc) {
                    curTxtM.textContent = 'Conversion automatique ' + d.currency + ' → ' + toCurM + ' au taux du jour. Le compte destinataire sera crédité du montant converti.';
                    curBoxM.style.display = 'block';
                } else {
                    curBoxM.style.display = 'none';
                }
            }

            var dateMErr = !checkSchedDate('m');
            submitM.disabled = sameAcc || frozen || fundErr || dateMErr;
        }

        if (filterType) filterType.addEventListener('change', rebuildSelects);

        // Autocomplete filter-user
        if (filterUserSearch && filterUserResults) {
            // Construire la liste unique des utilisateurs depuis ALL_ACCOUNTS
            function getUniqueUsers() {
                var seen = {}, list = [];
                ALL_ACCOUNTS.forEach(function (a) {
                    if (!seen[a.user_name]) { seen[a.user_name] = true; list.push(a.user_name); }
                });
                list.sort();
                return list;
            }

            function showUserSuggestions(q) {
                var lower = q.toLowerCase();
                var users = getUniqueUsers();
                var matches = q === '' ? users : users.filter(function (n) { return n.toLowerCase().indexOf(lower) !== -1; });
                if (!matches.length) { filterUserResults.style.display = 'none'; return; }
                var html = '';
                matches.forEach(function (n) {
                    html += '<div class="fu-item" style="padding:0.45rem 0.75rem;cursor:pointer;font-size:0.83rem;border-bottom:1px solid var(--border-color);"'
                          + ' data-name="' + n.replace(/"/g, '&quot;') + '">'
                          + n.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
                });
                filterUserResults.innerHTML = html;
                filterUserResults.querySelectorAll('.fu-item').forEach(function (el) {
                    el.addEventListener('mouseenter', function () { this.style.background = 'var(--bg-secondary)'; });
                    el.addEventListener('mouseleave', function () { this.style.background = ''; });
                    el.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        filterUserSearch.value = this.dataset.name;
                        filterUserQuery        = this.dataset.name;
                        filterUserResults.style.display = 'none';
                        rebuildSelects();
                    });
                });
                filterUserResults.style.display = 'block';
            }

            filterUserSearch.addEventListener('input', function () {
                filterUserQuery = this.value;
                showUserSuggestions(this.value.trim());
                rebuildSelects();
            });
            filterUserSearch.addEventListener('focus', function () {
                if (this.value.trim() === '') showUserSuggestions('');
            });
            filterUserSearch.addEventListener('blur', function () {
                setTimeout(function () { filterUserResults.style.display = 'none'; }, 150);
            });
            document.addEventListener('click', function (e) {
                if (!filterUserSearch.contains(e.target) && !filterUserResults.contains(e.target)) {
                    filterUserResults.style.display = 'none';
                }
            });
        }
        modFrom.addEventListener('change', function () { updateFromInfoM(); checkMod(); });
        modTo.addEventListener('change', checkMod);
        if (amountMEl) amountMEl.addEventListener('input', checkMod);

        rebuildSelects();
    }


    /* ─── Planification (toggle instantané / planifié) ──────────────────── */
    window.setSchedMode = function (form, mode) {
        schedModes[form] = mode;
        var dateDiv   = document.getElementById('sched-date-'  + form);
        var recurDiv  = document.getElementById('sched-recur-' + form);
        var btnNow    = document.getElementById('sched-btn-now-'   + form);
        var btnLater  = document.getElementById('sched-btn-later-' + form);
        var btnRecur  = document.getElementById('sched-btn-recur-' + form);
        var labelEl   = document.getElementById('submit-' + form + '-label');
        if (!dateDiv) return;

        // Reset all buttons to outline
        [btnNow, btnLater, btnRecur].forEach(function(b) {
            if (b) {
                b.className = b.className.replace('btn-primary', 'btn-outline-secondary');
            }
        });

        if (mode === 'later') {
            if (dateDiv)  dateDiv.style.display  = '';
            if (recurDiv) recurDiv.style.display = 'none';
            // Clear recurring fields
            var ri = document.getElementById('interval_days-'       + form);
            var rf = document.getElementById('first_execution_at-'  + form);
            if (ri) ri.value = '';
            if (rf) rf.value = '';
            if (btnLater) btnLater.className = btnLater.className.replace('btn-outline-secondary', 'btn-primary');
            if (labelEl) labelEl.textContent = 'Planifier le virement';
        } else if (mode === 'recurring') {
            if (dateDiv)  dateDiv.style.display  = 'none';
            if (recurDiv) recurDiv.style.display = '';
            // Clear one-time scheduled_at
            var sinp = document.getElementById('scheduled_at-' + form);
            if (sinp) sinp.value = '';
            if (btnRecur) btnRecur.className = btnRecur.className.replace('btn-outline-secondary', 'btn-primary');
            if (labelEl) labelEl.textContent = 'Créer le virement récurrent';
        } else {
            // 'now'
            if (dateDiv)  dateDiv.style.display  = 'none';
            if (recurDiv) recurDiv.style.display = 'none';
            var sinp2 = document.getElementById('scheduled_at-' + form);
            var ri2   = document.getElementById('interval_days-'      + form);
            var rf2   = document.getElementById('first_execution_at-' + form);
            if (sinp2) sinp2.value = '';
            if (ri2)   ri2.value   = '';
            if (rf2)   rf2.value   = '';
            if (btnNow) btnNow.className = btnNow.className.replace('btn-outline-secondary', 'btn-primary');
            if (labelEl) labelEl.textContent = 'Effectuer le virement';
        }

        // Re-déclencher la validation
        if (form === 'p' && typeof checkPersonal === 'function') checkPersonal();
        if (form === 'm' && typeof checkMod      === 'function') checkMod();
    };

    // Validation date dans checkPersonal et checkMod (ajoutée via patch)
    /**
     * Parse une date au format jj/mm/aaaa hh:mm ou ISO (Y-m-dTH:i).
     * Retourne un timestamp (ms) ou NaN.
     */
    function parseDateInput(val) {
        if (!val) return NaN;
        // Format jj/mm/aaaa hh:mm
        var m = val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/);
        if (m) return new Date(parseInt(m[3]), parseInt(m[2]) - 1, parseInt(m[1]), parseInt(m[4]), parseInt(m[5])).getTime();
        // Format jj/mm/aaaa (sans heure)
        var m2 = val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (m2) return new Date(parseInt(m2[3]), parseInt(m2[2]) - 1, parseInt(m2[1])).getTime();
        // Format ISO (datetime-local fallback)
        var ts = new Date(val).getTime();
        return ts;
    }

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
        var ts = parseDateInput(inp.value);
        if (isNaN(ts)) {
            warnDiv.style.display = 'block';
            warnTxt.textContent   = 'Format de date invalide (attendu : jj/mm/aaaa hh:mm).';
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
