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

<?php if (empty($merchantAccounts) && !is_moderator()): ?>
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

    <div class="card" id="pos-form-card" style="max-width:680px;">
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
                               placeholder="4242 4242 4242 4242" required maxlength="23"
                               value="<?= e((string) ($form['card_number'] ?? '')) ?>"
                               inputmode="numeric" autofocus
                               style="padding-right:2.4rem;">
                        <span id="card-status-icon"
                              style="position:absolute;right:0.7rem;top:50%;transform:translateY(-50%);font-size:1.1rem;display:none;"
                              aria-hidden="true"></span>
                    </div>
                    <small id="card-status-msg" class="text-muted">
                        16 chiffres. Espaces et tirets autorisés.
                    </small>
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

                <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1.5rem;">
                    <a href="/dashboard" class="btn btn-secondary">Annuler</a>
                    <button type="submit" id="pos-submit-btn" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> Encaisser
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
(function () {
    'use strict';

    var cardInput   = document.getElementById('card_number');
    var statusIcon  = document.getElementById('card-status-icon');
    var statusMsg   = document.getElementById('card-status-msg');
    var form        = document.getElementById('pos-form');
    var submitBtn   = document.getElementById('pos-submit-btn');
    var submitStat  = document.getElementById('pos-submit-status');
    var receiptBox  = document.getElementById('pos-receipt-live');
    var errorBox    = document.getElementById('pos-error-live');

    if (!cardInput || !form) return;

    var verifyTimer = null;
    var verifyAbort = null;
    var lastCardOk  = null;

    function setCardStatus(state, text) {
        if (!statusIcon || !statusMsg) return;
        statusIcon.style.display = 'inline-block';
        statusMsg.textContent = text || '';
        statusMsg.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');

        switch (state) {
            case 'ok':
                statusIcon.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#10b981;"></i>';
                statusMsg.classList.add('text-success');
                break;
            case 'checking':
                statusIcon.innerHTML = '<i class="bi bi-hourglass-split" style="color:#6b7280;"></i>';
                statusMsg.classList.add('text-muted');
                break;
            case 'invalid':
            case 'unknown':
            case 'unusable':
                statusIcon.innerHTML = '<i class="bi bi-x-circle-fill" style="color:#ef4444;"></i>';
                statusMsg.classList.add('text-danger');
                break;
            case 'blocked':
                statusIcon.innerHTML = '<i class="bi bi-shield-exclamation" style="color:#f59e0b;"></i>';
                statusMsg.classList.add('text-warning');
                break;
            case 'idle':
            default:
                statusIcon.style.display = 'none';
                statusIcon.innerHTML = '';
                statusMsg.classList.add('text-muted');
                statusMsg.textContent = '16 chiffres. Espaces et tirets autorisés.';
                break;
        }
    }

    function verifyCard() {
        var raw = (cardInput.value || '').replace(/[\s-]/g, '');
        lastCardOk = null;

        if (raw.length === 0) {
            setCardStatus('idle');
            return;
        }
        if (raw.length < 13) {
            setCardStatus('checking', 'Saisie en cours…');
            return;
        }

        if (verifyAbort) verifyAbort.abort();
        verifyAbort = ('AbortController' in window) ? new AbortController() : null;

        setCardStatus('checking', 'Vérification…');

        fetch('/pos/verify-card?number=' + encodeURIComponent(raw), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            signal: verifyAbort ? verifyAbort.signal : undefined
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                var b = res.body || {};
                if (b.success) {
                    lastCardOk = b;
                    var label = 'Carte valide' + (b.masked ? ' (' + b.masked + ')' : '');
                    if (b.currency) label += ' — devise ' + b.currency;
                    setCardStatus('ok', label);
                } else {
                    setCardStatus(b.state || 'invalid', b.message || 'Carte invalide.');
                }
            })
            .catch(function (e) {
                if (e && e.name === 'AbortError') return;
                setCardStatus('invalid', 'Erreur de vérification.');
            });
    }

    cardInput.addEventListener('input', function () {
        if (verifyTimer) clearTimeout(verifyTimer);
        verifyTimer = setTimeout(verifyCard, 350);
    });

    if (cardInput.value) verifyCard();

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
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }

    function fmtAmt(a, c) {
        var n = Number(a);
        if (isNaN(n)) return a + ' ' + (c || '');
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (c || '');
    }

    function renderReceipt(rec) {
        if (!receiptBox) return;
        var deferred = rec.deferred
            ? '<span class="badge bg-warning text-dark" style="margin-left:0.4rem;">Débit différé</span>' : '';
        var account = rec.merchant_account
            ? escHtml(rec.merchant_account)
            : '<span class="text-muted"><em>Aucun (paiement enregistré sans crédit commerçant)</em></span>';
        var deferredRow = (rec.deferred && rec.deferred_date)
            ? '<span class="text-muted">Débit prévu le :</span><strong>' + escHtml(rec.deferred_date) + '</strong>'
            : '';

        receiptBox.style.display = 'block';
        receiptBox.className     = 'alert alert-success';
        receiptBox.setAttribute('role', 'alert');
        receiptBox.style.cssText = 'display:flex;align-items:flex-start;gap:0.75rem;';
        receiptBox.innerHTML =
            '<i class="bi bi-receipt" style="font-size:1.5rem;flex-shrink:0;"></i>' +
            '<div style="flex:1;">' +
                '<strong>Paiement accepté' + deferred + '</strong>' +
                '<div style="margin-top:0.5rem;display:grid;grid-template-columns:max-content 1fr;gap:0.25rem 1rem;font-size:0.95rem;">' +
                    '<span class="text-muted">Référence :</span><strong>' + escHtml(rec.reference) + '</strong>' +
                    '<span class="text-muted">Date :</span><span>' + escHtml(rec.datetime) + '</span>' +
                    '<span class="text-muted">Commerçant :</span><span>' + escHtml(rec.merchant) + '</span>' +
                    '<span class="text-muted">Opération :</span><span>' + escHtml(rec.label) + '</span>' +
                    '<span class="text-muted">Montant :</span><strong>' + fmtAmt(rec.amount, rec.currency) + '</strong>' +
                    '<span class="text-muted">Carte :</span><code>' + escHtml(rec.card_masked) + '</code>' +
                    '<span class="text-muted">Compte crédité :</span><span>' + account + '</span>' +
                    deferredRow +
                '</div>' +
            '</div>';
        try { receiptBox.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) {}
    }

    function renderError(message, errors) {
        if (!errorBox) return;
        var list = '';
        if (Array.isArray(errors) && errors.length) {
            list = '<ul style="margin:0.25rem 0 0 1.25rem;padding:0;">';
            for (var i = 0; i < errors.length; i++) list += '<li>' + escHtml(errors[i]) + '</li>';
            list += '</ul>';
        }
        errorBox.style.display = 'block';
        errorBox.className     = 'alert alert-danger';
        errorBox.setAttribute('role', 'alert');
        errorBox.innerHTML     = '<i class="bi bi-exclamation-triangle-fill"></i> '
                               + escHtml(message || 'Paiement refusé.') + list;
    }

    form.addEventListener('submit', function (ev) {
        if (!window.fetch || !window.FormData) return; // fallback : POST classique
        ev.preventDefault();

        if (errorBox)   { errorBox.style.display = 'none'; errorBox.innerHTML = ''; }
        if (receiptBox) { receiptBox.style.display = 'none'; receiptBox.innerHTML = ''; }

        submitBtn.disabled = true;
        setSubmitStatus('pending', 'Traitement du paiement en cours…');

        fetch('/pos/charge', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form)
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); })
            .then(function (res) {
                var b = res.body || {};
                if (b.success && b.receipt) {
                    setSubmitStatus('ok', escHtml(b.message || 'Paiement accepté.'));
                    renderReceipt(b.receipt);
                    // Réinitialiser le formulaire pour la transaction suivante
                    form.reset();
                    setCardStatus('idle');
                } else {
                    setSubmitStatus('err', escHtml(b.message || 'Paiement refusé.'));
                    renderError(b.message, b.errors);
                }
            })
            .catch(function () {
                setSubmitStatus('err', 'Erreur réseau, veuillez réessayer.');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
})();
</script>
