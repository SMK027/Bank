<?php
/** @var array  $user */
/** @var array  $merchantAccounts */
/** @var array  $form */
/** @var array  $errors */
/** @var ?array $receipt */
/** @var ?array $posStatus */
$errors    = $errors ?? [];
$form      = $form ?? [];
$receipt   = $receipt ?? null;
$posStatus = $posStatus ?? null;
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-shop"></i> Terminal de paiement (TPE)</h1>
    <?php if ($posStatus && !empty($posStatus['is_disabled'])): ?>
        <span class="badge badge-danger"><i class="bi bi-power"></i> TPE désactivé</span>
    <?php else: ?>
        <span class="badge bg-success"><i class="bi bi-wifi"></i> Raccordé à la plateforme</span>
    <?php endif; ?>
</div>

<?php if ($posStatus && !empty($posStatus['is_disabled'])): ?>
    <div class="alert alert-danger" role="alert" style="display:flex;align-items:flex-start;gap:0.75rem;">
        <i class="bi bi-power" style="font-size:1.4rem;flex-shrink:0;"></i>
        <div>
            <strong>Le TPE est actuellement désactivé par la modération.</strong>
            <?php if (!empty($posStatus['reason'])): ?>
                <div class="text-small" style="margin-top:0.3rem;">
                    Motif : <?= e($posStatus['reason']) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($posStatus['disabled_until'])): ?>
                <div class="text-small">
                    Réactivation automatique prévue le
                    <?= e(date('d/m/Y H:i', strtotime($posStatus['disabled_until']))) ?>.
                </div>
            <?php else: ?>
                <div class="text-small">Aucune date de réactivation programmée.</div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($receipt): ?>
    <div id="pos-receipt" class="alert alert-success" role="alert" style="display:flex;align-items:flex-start;gap:0.75rem;">
        <i class="bi bi-receipt" style="font-size:1.5rem;flex-shrink:0;"></i>
        <div style="flex:1;">
            <strong>
                Paiement accepté
                <?php if (!empty($receipt['deferred'])): ?>
                    <span class="badge bg-warning text-dark" style="margin-left:0.4rem;">Débit différé</span>
                <?php endif; ?>
            </strong>
            <div style="margin-top:0.5rem;display:grid;grid-template-columns:max-content 1fr;gap:0.25rem 1rem;font-size:0.95rem;">
                <span class="text-muted">Référence :</span>
                <strong><?= e($receipt['reference']) ?></strong>
                <span class="text-muted">Date :</span>
                <span><?= e($receipt['datetime']) ?></span>
                <span class="text-muted">Commerçant :</span>
                <span><?= e($receipt['merchant']) ?></span>
                <span class="text-muted">Opération :</span>
                <span><?= e($receipt['label']) ?></span>
                <span class="text-muted">Montant :</span>
                <strong><?= number_format((float) $receipt['amount'], 2, ',', ' ') ?> <?= e($receipt['currency']) ?></strong>
                <span class="text-muted">Carte :</span>
                <code><?= e($receipt['card_masked']) ?></code>
                <?php if (!empty($receipt['merchant_account'])): ?>
                    <span class="text-muted">Compte crédité :</span>
                    <span><?= e($receipt['merchant_account']) ?></span>
                <?php else: ?>
                    <span class="text-muted">Compte crédité :</span>
                    <span class="text-muted"><em>Aucun (paiement enregistré sans crédit commerçant)</em></span>
                <?php endif; ?>
                <?php if (!empty($receipt['deferred']) && !empty($receipt['deferred_date'])): ?>
                    <span class="text-muted">Débit prévu le :</span>
                    <strong><?= e($receipt['deferred_date']) ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="pos-receipt-live" style="display:none;"></div>
<div id="pos-error-live" style="display:none;"></div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul style="margin:0.25rem 0 0 1.25rem;padding:0;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
    $allPosAccounts       = $merchantAccountsRaw ?? [];
    $suspendedPosAccounts = array_values(array_filter($allPosAccounts, fn($a) => \App\Models\Account::isPosSuspended($a)));
    $merchantSuspended    = !is_moderator() && empty($merchantAccounts) && !empty($suspendedPosAccounts);
    $tpeBlocked           = $posStatus && !empty($posStatus['is_disabled']);
    $blockForm            = $tpeBlocked || $merchantSuspended;
?>

<?php if ($merchantSuspended): ?>
    <div class="alert alert-danger" role="alert" style="display:flex;align-items:flex-start;gap:0.75rem;">
        <i class="bi bi-slash-circle" style="font-size:1.4rem;flex-shrink:0;margin-top:0.1rem;"></i>
        <div>
            <strong>Votre accès au TPE est suspendu par la modération.</strong>
            <?php
                $suspReason = '';
                $suspUntil  = null;
                foreach ($suspendedPosAccounts as $_sa) {
                    if (!empty($_sa['pos_suspend_reason']) && $suspReason === '') {
                        $suspReason = $_sa['pos_suspend_reason'];
                    }
                    if (!empty($_sa['pos_suspended_until']) && $suspUntil === null) {
                        $suspUntil = $_sa['pos_suspended_until'];
                    }
                }
            ?>
            <?php if ($suspReason !== ''): ?>
                <div class="text-small" style="margin-top:0.3rem;">
                    Motif : <?= e($suspReason) ?>
                </div>
            <?php endif; ?>
            <?php if ($suspUntil): ?>
                <div class="text-small">
                    Réactivation automatique prévue le
                    <?= e(date('d/m/Y H:i', strtotime($suspUntil))) ?>.
                </div>
            <?php else: ?>
                <div class="text-small">Aucune date de réactivation programmée. Contactez la modération pour plus d'informations.</div>
            <?php endif; ?>
            <div class="text-small" style="margin-top:0.4rem;">
                Vous ne pouvez pas encaisser de paiements tant que la suspension est active.
            </div>
        </div>
    </div>
<?php elseif (empty($merchantAccounts) && !is_moderator()): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        Vous ne disposez d'aucun compte professionnel. Vous pouvez tout de même
        utiliser le TPE&nbsp;: la carte du client sera débitée et l'opération sera
        enregistrée sans crédit commerçant.
        <?php if (!is_professional()): ?>
            <br><a href="/profile/professional">Activez votre statut professionnel</a> pour
            associer un compte d'encaissement.
        <?php else: ?>
            <br><a href="/accounts/create">Créer un compte professionnel</a> pour bénéficier
            du crédit automatique.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$blockForm): ?>
    <div class="card" id="pos-form-card" style="max-width:680px;transition:border-color 0.3s;">
        <div class="card-body">
            <p class="text-muted" style="margin-top:0;">
                Saisissez les informations de la transaction. La carte du client sera
                débitée et le compte d'encaissement sélectionné sera crédité du montant.
            </p>

            <form id="pos-form" method="POST" action="/pos/charge" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="card_number" class="form-label">
                        <i class="bi bi-credit-card-2-front"></i> Numéro de carte
                    </label>
                    <div style="position:relative;">
                        <input type="text" id="card_number" name="card_number" class="form-control"
                               value="<?= e((string) ($form['card_number'] ?? '')) ?>"
                               inputmode="numeric" autocomplete="cc-number" autofocus
                               placeholder="4242 4242 4242 4242" required maxlength="23"
                               style="padding-right:2.4rem;">
                        <span id="card-status-icon"
                              style="position:absolute;right:0.7rem;top:50%;transform:translateY(-50%);font-size:1.1rem;display:none;"
                              aria-hidden="true"></span>
                    </div>
                    <small id="card-status-msg" class="text-muted">
                        16 chiffres. Espaces et tirets autorisés.
                    </small>
                    <small id="card-status-detail" style="display:none;font-weight:600;font-size:0.82rem;"></small>
                </div>

                <div class="form-group">
                    <label for="amount" class="form-label">
                        <i class="bi bi-currency-euro"></i> Montant
                    </label>
                    <input type="text" id="amount" name="amount" class="form-control"
                           placeholder="0,00" required inputmode="decimal"
                           value="<?= e((string) ($form['amount'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="label" class="form-label">
                        <i class="bi bi-tag"></i> Intitulé de l'opération
                    </label>
                    <input type="text" id="label" name="label" class="form-control"
                           placeholder="Ex : Achat sur place" required maxlength="120"
                           value="<?= e((string) ($form['label'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="merchant" class="form-label">
                        <i class="bi bi-shop"></i> Commerçant
                    </label>
                    <input type="text" id="merchant" name="merchant" class="form-control"
                           placeholder="Ex : Boulangerie Dupont" required maxlength="120"
                           value="<?= e((string) ($form['merchant'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="account_id" class="form-label">
                        <i class="bi bi-bank"></i> Compte d'encaissement
                        <span class="text-muted" style="font-weight:normal;font-size:0.85em;">(facultatif)</span>
                    </label>
                    <select id="account_id" name="account_id" class="form-control">
                        <option value="">— Aucun (pas de crédit commerçant) —</option>
                        <?php foreach ($merchantAccounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"
                                <?= (int) ($form['account_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                                <?= e($a['name']) ?> (<?= e($a['currency']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Si aucun compte n'est sélectionné, seule la carte du client sera débitée
                        (l'opération est enregistrée mais aucun compte commerçant n'est crédité).
                    </small>
                </div>

                <div id="pos-submit-status"
                     style="display:none;margin-top:1rem;padding:0.65rem 0.85rem;border-radius:6px;font-size:0.9rem;"
                     role="status" aria-live="polite"></div>

                <div class="pos-actions form-actions" style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;">
                    <a href="/dashboard" class="btn btn-secondary btn-cancel">Annuler</a>
                    <button type="submit" id="pos-submit-btn" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> Encaisser
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    'use strict';

    /* ── Références DOM ──────────────────────────────── */
    var cardInput   = document.getElementById('card_number');
    var amountInput = document.getElementById('amount');
    var statusIcon  = document.getElementById('card-status-icon');
    var statusMsg   = document.getElementById('card-status-msg');
    var statusDetail= document.getElementById('card-status-detail');
    var form        = document.getElementById('pos-form');
    var formCard    = document.getElementById('pos-form-card');
    var submitBtn   = document.getElementById('pos-submit-btn');
    var submitStat  = document.getElementById('pos-submit-status');
    var receiptBox  = document.getElementById('pos-receipt-live');
    var errorBox    = document.getElementById('pos-error-live');

    if (!cardInput || !form) return;

    /* ── Animations CSS ──────────────────────────────── */
    var style = document.createElement('style');
    style.textContent = [
        '@keyframes pos-shake{0%,100%{transform:translateX(0)}',
        '15%{transform:translateX(-8px)}30%{transform:translateX(8px)}',
        '45%{transform:translateX(-6px)}60%{transform:translateX(6px)}',
        '75%{transform:translateX(-4px)}90%{transform:translateX(2px)}}',

        '@keyframes pos-success-glow{0%{box-shadow:0 0 0 0 rgba(34,197,94,0.6)}',
        '50%{box-shadow:0 0 0 14px rgba(34,197,94,0)}',
        '100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}',

        '@keyframes pos-error-flash{0%,100%{border-color:var(--border-color,#d1d5db)}',
        '25%,75%{border-color:#ef4444;background:rgba(239,68,68,0.04)}}',

        '@keyframes pos-receipt-in{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}',

        '.pos-shake{animation:pos-shake 0.55s ease both}',
        '.pos-success-glow{animation:pos-success-glow 0.7s ease-out}',
        '.pos-error-flash{animation:pos-error-flash 0.6s ease both}',
        '.pos-receipt-in{animation:pos-receipt-in 0.35s ease both}',
    ].join('');
    document.head.appendChild(style);

    function triggerAnimation(el, cls) {
        if (!el) return;
        el.classList.remove(cls);
        void el.offsetWidth; // reflow
        el.classList.add(cls);
        el.addEventListener('animationend', function h() {
            el.classList.remove(cls);
            el.removeEventListener('animationend', h);
        });
    }

    /* ── Vérification carte ──────────────────────────── */
    var verifyTimer = null;
    var verifyAbort = null;
    var lastState   = null;

    /* Icône + message + détail selon l'état */
    var STATE_CONFIG = {
        ok:                 { icon: 'check-circle-fill',       color: '#10b981', msgClass: 'text-success' },
        checking:           { icon: 'hourglass-split',         color: '#6b7280', msgClass: 'text-muted'   },
        invalid:            { icon: 'x-circle-fill',           color: '#ef4444', msgClass: 'text-danger'  },
        unknown:            { icon: 'question-circle-fill',    color: '#ef4444', msgClass: 'text-danger'  },
        blocked:            { icon: 'lock-fill',               color: '#f59e0b', msgClass: 'text-warning' },
        disabled:           { icon: 'slash-circle-fill',       color: '#ef4444', msgClass: 'text-danger'  },
        expired:            { icon: 'calendar-x-fill',         color: '#ef4444', msgClass: 'text-danger'  },
        insufficient_funds: { icon: 'exclamation-triangle-fill', color: '#ef4444', msgClass: 'text-danger'},
        limit_exceeded:     { icon: 'bar-chart-fill',          color: '#f59e0b', msgClass: 'text-warning' },
        offline:            { icon: 'wifi-off',                color: '#6b7280', msgClass: 'text-muted'   },
        idle:               { icon: null,                      color: null,      msgClass: 'text-muted'   },
    };

    function setCardStatus(state, text, detail) {
        if (!statusIcon || !statusMsg) return;
        lastState = state;

        var cfg = STATE_CONFIG[state] || STATE_CONFIG.idle;

        statusMsg.classList.remove('text-muted','text-success','text-danger','text-warning');

        if (state === 'idle' || !cfg.icon) {
            statusIcon.style.display = 'none';
            statusIcon.innerHTML     = '';
            statusMsg.classList.add('text-muted');
            statusMsg.textContent    = '16 chiffres. Espaces et tirets autorisés.';
            if (statusDetail) { statusDetail.style.display = 'none'; statusDetail.textContent = ''; }
            return;
        }

        statusIcon.style.display = 'inline-block';
        statusIcon.innerHTML     = '<i class="bi bi-' + cfg.icon + '" style="color:' + cfg.color + ';"></i>';
        statusMsg.classList.add(cfg.msgClass);
        statusMsg.textContent    = text || '';

        if (statusDetail) {
            if (detail) {
                statusDetail.style.display  = 'block';
                statusDetail.textContent    = detail;
                statusDetail.style.color    = cfg.color;
            } else {
                statusDetail.style.display  = 'none';
                statusDetail.textContent    = '';
            }
        }
    }

    function getAmount() {
        if (!amountInput) return null;
        var v = amountInput.value.trim().replace(',', '.');
        var n = parseFloat(v);
        return isNaN(n) || n <= 0 ? null : n;
    }

    function verifyCard() {
        var raw = (cardInput.value || '').replace(/[\s-]/g, '');
        lastState = null;

        if (raw.length === 0)  { setCardStatus('idle'); return; }
        if (raw.length < 13)   { setCardStatus('checking', 'Saisie en cours\u2026'); return; }

        if (verifyAbort) verifyAbort.abort();
        verifyAbort = ('AbortController' in window) ? new AbortController() : null;

        setCardStatus('checking', 'V\u00e9rification\u2026');

        var url = '/pos/verify-card?number=' + encodeURIComponent(raw);
        var amt = getAmount();
        if (amt !== null) url += '&amount=' + encodeURIComponent(amt);

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            signal: verifyAbort ? verifyAbort.signal : undefined
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, body: j }; }); })
        .then(function(res) {
            var b = res.body || {};
            setCardStatus(b.state || 'invalid', b.message || 'Carte invalide.', b.detail || null);
        })
        .catch(function(e) {
            if (e && e.name === 'AbortError') return;
            setCardStatus('invalid', 'Erreur de v\u00e9rification.');
        });
    }

    cardInput.addEventListener('input', function () {
        if (verifyTimer) clearTimeout(verifyTimer);
        verifyTimer = setTimeout(verifyCard, 350);
    });

    /* Re-vérifier quand le montant change (pour solde/plafond en temps réel) */
    if (amountInput) {
        amountInput.addEventListener('input', function () {
            var raw = (cardInput.value || '').replace(/[\s-]/g, '');
            if (raw.length >= 13) {
                if (verifyTimer) clearTimeout(verifyTimer);
                verifyTimer = setTimeout(verifyCard, 400);
            }
        });
    }

    if (cardInput.value) verifyCard();

    /* ── Soumission AJAX ─────────────────────────────── */
    function setSubmitStatus(kind, html) {
        if (!submitStat) return;
        if (!kind) { submitStat.style.display = 'none'; submitStat.innerHTML = ''; return; }
        submitStat.style.display = 'block';
        var bg = '#e5e7eb', color = '#1f2937', icon = '';
        if (kind === 'pending') { bg = '#dbeafe'; color = '#1e3a8a'; icon = '<i class="bi bi-hourglass-split"></i> '; }
        if (kind === 'ok')      { bg = '#d1fae5'; color = '#065f46'; icon = '<i class="bi bi-check-circle-fill"></i> '; }
        if (kind === 'err')     { bg = '#fee2e2'; color = '#991b1b'; icon = '<i class="bi bi-x-octagon-fill"></i> '; }
        submitStat.style.background = bg;
        submitStat.style.color      = color;
        submitStat.innerHTML        = icon + html;
    }

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function fmtAmt(a, c) {
        var n = Number(a);
        if (isNaN(n)) return a + ' ' + (c || '');
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (c || '');
    }

    /* Icône correspondant au motif de refus */
    var REFUSE_ICONS = {
        blocked:            '<i class="bi bi-lock-fill" style="color:#f59e0b;"></i>',
        disabled:           '<i class="bi bi-slash-circle-fill" style="color:#ef4444;"></i>',
        expired:            '<i class="bi bi-calendar-x-fill" style="color:#ef4444;"></i>',
        insufficient_funds: '<i class="bi bi-exclamation-triangle-fill" style="color:#ef4444;"></i>',
        limit_exceeded:     '<i class="bi bi-bar-chart-fill" style="color:#f59e0b;"></i>',
        unknown:            '<i class="bi bi-question-circle-fill" style="color:#ef4444;"></i>',
        offline:            '<i class="bi bi-wifi-off" style="color:#6b7280;"></i>',
    };

    function renderError(message, errors, state) {
        if (!errorBox) return;
        var iconHtml = (state && REFUSE_ICONS[state]) ? REFUSE_ICONS[state] + ' ' : '<i class="bi bi-exclamation-triangle-fill"></i> ';
        var list = '';
        if (Array.isArray(errors) && errors.length > 1) {
            list = '<ul style="margin:0.4rem 0 0 1.4rem;padding:0;">';
            for (var i = 0; i < errors.length; i++) list += '<li>' + escHtml(errors[i]) + '</li>';
            list += '</ul>';
        }
        errorBox.style.display = 'block';
        errorBox.className     = 'alert alert-danger pos-receipt-in';
        errorBox.setAttribute('role', 'alert');
        errorBox.innerHTML     = iconHtml + '<strong>' + escHtml(message || 'Paiement refus\u00e9.') + '</strong>' + list;
        triggerAnimation(formCard, 'pos-shake');
        triggerAnimation(formCard, 'pos-error-flash');
    }

    function renderReceipt(rec) {
        if (!receiptBox) return;
        var deferred = rec.deferred
            ? '<span class="badge bg-warning text-dark" style="margin-left:0.4rem;">D\u00e9bit diff\u00e9r\u00e9</span>' : '';
        var account = rec.merchant_account
            ? escHtml(rec.merchant_account)
            : '<span class="text-muted"><em>Aucun (paiement enregistr\u00e9 sans cr\u00e9dit commer\u00e7ant)</em></span>';
        var deferredRow = (rec.deferred && rec.deferred_date)
            ? '<span class="text-muted">D\u00e9bit pr\u00e9vu le :</span><strong>' + escHtml(rec.deferred_date) + '</strong>'
            : '';

        receiptBox.style.display = 'flex';
        receiptBox.className     = 'alert alert-success pos-receipt-in';
        receiptBox.setAttribute('role', 'alert');
        receiptBox.style.cssText = 'display:flex;align-items:flex-start;gap:0.75rem;';
        receiptBox.innerHTML =
            '<i class="bi bi-receipt-cutoff" style="font-size:1.5rem;flex-shrink:0;color:#10b981;"></i>' +
            '<div style="flex:1;">' +
                '<strong>Paiement accept\u00e9' + deferred + '</strong>' +
                '<div style="margin-top:0.5rem;display:grid;grid-template-columns:max-content 1fr;gap:0.25rem 1rem;font-size:0.95rem;">' +
                    '<span class="text-muted">R\u00e9f\u00e9rence :</span><strong>' + escHtml(rec.reference) + '</strong>' +
                    '<span class="text-muted">Date :</span><span>' + escHtml(rec.datetime) + '</span>' +
                    '<span class="text-muted">Commer\u00e7ant :</span><span>' + escHtml(rec.merchant) + '</span>' +
                    '<span class="text-muted">Op\u00e9ration :</span><span>' + escHtml(rec.label) + '</span>' +
                    '<span class="text-muted">Montant :</span><strong>' + fmtAmt(rec.amount, rec.currency) + '</strong>' +
                    '<span class="text-muted">Carte :</span><code>' + escHtml(rec.card_masked) + '</code>' +
                    '<span class="text-muted">Compte cr\u00e9dit\u00e9 :</span><span>' + account + '</span>' +
                    deferredRow +
                '</div>' +
            '</div>';
        triggerAnimation(formCard, 'pos-success-glow');
        try { receiptBox.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(e) {}
    }

    form.addEventListener('submit', function (ev) {
        if (!window.fetch || !window.FormData) return;
        ev.preventDefault();

        if (errorBox)   { errorBox.style.display   = 'none'; errorBox.innerHTML   = ''; }
        if (receiptBox) { receiptBox.style.display  = 'none'; receiptBox.innerHTML = ''; }

        submitBtn.disabled = true;
        setSubmitStatus('pending', 'Traitement du paiement en cours\u2026');

        fetch('/pos/charge', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form)
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, status: r.status, body: j }; }); })
        .then(function(res) {
            var b = res.body || {};
            if (b.success && b.receipt) {
                setSubmitStatus('ok', escHtml(b.message || 'Paiement accept\u00e9.'));
                renderReceipt(b.receipt);
                form.reset();
                setCardStatus('idle');
            } else {
                setSubmitStatus('err', escHtml(b.message || 'Paiement refus\u00e9.'));
                renderError(b.message, b.errors, b.state);
            }
        })
        .catch(function () {
            setSubmitStatus('err', 'Erreur r\u00e9seau, veuillez r\u00e9essayer.');
            triggerAnimation(formCard, 'pos-shake');
        })
        .finally(function () {
            submitBtn.disabled = false;
        });
    });
})();
</script>
