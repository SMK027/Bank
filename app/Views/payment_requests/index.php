<?php
/**
 * @var array  $requests        Toutes les demandes (envoyées + reçues)
 * @var int    $pendingReceived  Nb de demandes en attente reçues
 * @var array  $ownAccounts     Comptes propres actifs de l'utilisateur
 * @var int    $currentUserId   ID de l'utilisateur connecté
 */

$statusLabels = [
    'pending'   => ['label' => 'En attente',  'class' => 'warning'],
    'paid'      => ['label' => 'Payée',        'class' => 'success'],
    'refused'   => ['label' => 'Refusée',      'class' => 'danger'],
    'cancelled' => ['label' => 'Annulée',      'class' => 'secondary'],
    'expired'   => ['label' => 'Expirée',      'class' => 'secondary'],
];
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-send-fill"></i> Demandes d'argent</h1>
        <p class="page-description">Envoyez ou répondez aux demandes de paiement entre utilisateurs.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('pr-create-form').scrollIntoView({behavior:'smooth'})">
        <i class="bi bi-plus-lg"></i> Nouvelle demande
    </button>
</div>

<?php if ($pendingReceived > 0): ?>
<div class="alert" style="background:rgba(67,97,238,0.1);border-left:4px solid var(--primary);margin-bottom:1.5rem;">
    <i class="bi bi-bell-fill"></i>
    Vous avez <strong><?= $pendingReceived ?></strong> demande<?= $pendingReceived > 1 ? 's' : '' ?> en attente de paiement.
</div>
<?php endif; ?>

<!-- ── Liste des demandes ─────────────────────────────────────────────── -->
<?php if (empty($requests)): ?>
<div class="empty-state" style="margin-bottom:2rem;">
    <div class="empty-icon">💸</div>
    <p>Aucune demande d'argent pour le moment.</p>
    <p class="text-muted text-small">Créez une demande ci-dessous pour commencer.</p>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:2rem;">
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>De / À</th>
                        <th>Motif</th>
                        <th class="text-right">Montant</th>
                        <th>Statut</th>
                        <th>Expiration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r):
                        $isSender   = (int) $r['requester_id'] === $currentUserId;
                        $isReceiver = (int) $r['recipient_id'] === $currentUserId;
                        $status     = $r['status'];
                        $isPending  = ($status === 'pending' && (empty($r['expires_at']) || strtotime($r['expires_at']) > time()));
                        $sl         = $statusLabels[$status] ?? ['label' => $status, 'class' => 'secondary'];
                        $amount     = (float) $r['amount'];
                        $currency   = e($r['currency']);
                    ?>
                    <tr>
                        <td>
                            <?php if ($isSender): ?>
                                <span class="badge" style="background:rgba(239,71,111,0.12);color:var(--danger);font-size:0.8em;">
                                    <i class="bi bi-arrow-up-right"></i> Envoyée à
                                </span>
                                <strong><?= e($r['recipient_username']) ?></strong>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(6,214,160,0.12);color:var(--success);font-size:0.8em;">
                                    <i class="bi bi-arrow-down-left"></i> Reçue de
                                </span>
                                <strong><?= e($r['requester_username']) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['motif'] !== '' ? e($r['motif']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-right font-bold">
                            <?= number_format($amount, 2, ',', ' ') ?> <?= $currency ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $sl['class'] ?>"><?= $sl['label'] ?></span>
                        </td>
                        <td class="text-muted" style="font-size:0.85rem;">
                            <?php if (!empty($r['expires_at'])): ?>
                                <?php
                                    $exp = strtotime($r['expires_at']);
                                    $now = time();
                                    $diff = $exp - $now;
                                ?>
                                <?php if ($status === 'pending' && $diff > 0): ?>
                                    <?php if ($diff < 86400): ?>
                                        <span style="color:var(--danger);">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Expire dans <?= ceil($diff / 3600) ?>h
                                        </span>
                                    <?php else: ?>
                                        <?= date('d/m/Y', $exp) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= date('d/m/Y', $exp) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($isPending && $isReceiver): ?>
                                <!-- Payer -->
                                <button type="button"
                                        class="btn btn-primary btn-sm"
                                        onclick="openPayModal(<?= (int) $r['id'] ?>, '<?= number_format($amount, 2, ',', ' ') ?> <?= $currency ?>')"
                                        style="font-size:0.82rem;">
                                    <i class="bi bi-check-circle"></i> Payer
                                </button>
                                <!-- Refuser -->
                                <form method="POST" action="/payment-requests/<?= (int) $r['id'] ?>/refuse" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Refuser cette demande ?')"
                                            style="font-size:0.82rem;">
                                        <i class="bi bi-x-circle"></i> Refuser
                                    </button>
                                </form>
                            <?php elseif ($isPending && $isSender): ?>
                                <!-- Annuler -->
                                <form method="POST" action="/payment-requests/<?= (int) $r['id'] ?>/cancel" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="btn btn-outline btn-sm"
                                            onclick="return confirm('Annuler cette demande ?')"
                                            style="font-size:0.82rem;">
                                        <i class="bi bi-trash"></i> Annuler
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Formulaire : Nouvelle demande ──────────────────────────────────── -->
<h2 class="mb-2" id="pr-create-form"><i class="bi bi-pencil-square"></i> Nouvelle demande</h2>
<div class="card" style="margin-bottom:2rem;max-width:560px;">
    <div class="card-body">
        <form method="POST" action="/payment-requests/create" id="pr-form">
            <?= csrf_field() ?>

            <!-- Destinataire -->
            <div class="form-group">
                <label class="form-label" for="pr-recipient-input">
                    Destinataire <span class="text-danger">*</span>
                </label>
                <input
                    type="text"
                    id="pr-recipient-input"
                    class="form-control"
                    placeholder="Rechercher par nom d'utilisateur…"
                    autocomplete="off"
                    required
                >
                <input type="hidden" name="recipient_id" id="pr-recipient-id" required>
                <input type="hidden" name="recipient_search" id="pr-recipient-search">
                <div id="pr-autocomplete" style="display:none;position:absolute;z-index:100;background:#fff;border:1px solid var(--gray-light);border-radius:var(--border-radius-sm);min-width:260px;box-shadow:var(--shadow);">
                </div>
                <div id="pr-recipient-selected" style="display:none;margin-top:0.4rem;">
                    <span class="badge badge-success" id="pr-recipient-badge"></span>
                    <button type="button" onclick="clearRecipient()" class="btn btn-sm btn-outline" style="padding:0.1rem 0.4rem;font-size:0.78rem;">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>

            <!-- Montant -->
            <div class="form-group">
                <label class="form-label" for="pr-amount">Montant <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="pr-amount" name="amount" min="0.01" step="0.01" placeholder="ex : 25.00" required>
            </div>

            <!-- Motif -->
            <div class="form-group">
                <label class="form-label" for="pr-motif">Motif</label>
                <input type="text" class="form-control" id="pr-motif" name="motif" placeholder="ex : Remboursement resto" maxlength="255">
            </div>

            <!-- Compte de réception (facultatif) -->
            <div class="form-group">
                <label class="form-label" for="pr-account">Compte de réception</label>
                <select class="form-control" id="pr-account" name="from_account_id">
                    <option value="">— Compte par défaut (n'importe lequel de mes comptes actifs) —</option>
                    <?php foreach ($ownAccounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?> (<?= e($acc['currency']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Si non précisé, le premier compte actif sera utilisé.</small>
            </div>

            <!-- Expiration -->
            <div class="form-group">
                <label class="form-label" for="pr-expiry">Validité (jours)</label>
                <select class="form-control" id="pr-expiry" name="expiry_days">
                    <option value="3">3 jours</option>
                    <option value="7" selected>7 jours (défaut)</option>
                    <option value="14">14 jours</option>
                    <option value="30">30 jours</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" id="pr-submit" disabled>
                <i class="bi bi-send"></i> Envoyer la demande
            </button>
        </form>
    </div>
</div>

<!-- ── Modal : Choisir le compte pour payer ───────────────────────────── -->
<div id="pay-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="card" style="max-width:420px;width:100%;margin:1rem;">
        <div class="card-body">
            <h3 style="margin-bottom:1rem;"><i class="bi bi-check-circle"></i> Payer la demande</h3>
            <p id="pay-modal-amount" style="font-size:1.1rem;font-weight:600;margin-bottom:1rem;"></p>
            <form method="POST" id="pay-modal-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="pay-account">Compte à débiter <span class="text-danger">*</span></label>
                    <select class="form-control" name="from_account_id" id="pay-account" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($ownAccounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>">
                                <?= e($acc['name']) ?> (<?= e($acc['currency']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Confirmer</button>
                    <button type="button" class="btn btn-outline" onclick="closePayModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Autocomplete destinataire ───────────────────────────────────────────
(function () {
    var input     = document.getElementById('pr-recipient-input');
    var hiddenId  = document.getElementById('pr-recipient-id');
    var hiddenQ   = document.getElementById('pr-recipient-search');
    var dropdown  = document.getElementById('pr-autocomplete');
    var selected  = document.getElementById('pr-recipient-selected');
    var badge     = document.getElementById('pr-recipient-badge');
    var submit    = document.getElementById('pr-submit');
    var timer     = null;

    function updateSubmit() {
        submit.disabled = !hiddenId.value;
    }

    window.clearRecipient = function () {
        hiddenId.value = '';
        input.value    = '';
        hiddenQ.value  = '';
        selected.style.display = 'none';
        input.style.display    = '';
        updateSubmit();
    };

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('/payment-requests/search-users?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (users) {
                    dropdown.innerHTML = '';
                    if (!users.length) {
                        dropdown.style.display = 'none';
                        return;
                    }
                    users.forEach(function (u) {
                        var item = document.createElement('div');
                        item.style.cssText = 'padding:0.5rem 0.75rem;cursor:pointer;';
                        item.innerHTML = '<i class="bi bi-person"></i> ' + u.username;
                        item.addEventListener('mouseenter', function () { item.style.background = 'var(--gray-lighter)'; });
                        item.addEventListener('mouseleave', function () { item.style.background = ''; });
                        item.addEventListener('click', function () {
                            hiddenId.value         = u.id;
                            hiddenQ.value          = u.username;
                            badge.textContent      = '👤 ' + u.username;
                            selected.style.display = 'flex';
                            selected.style.gap     = '0.4rem';
                            selected.style.alignItems = 'center';
                            input.style.display    = 'none';
                            dropdown.style.display = 'none';
                            updateSubmit();
                        });
                        dropdown.appendChild(item);
                    });
                    dropdown.style.display = 'block';
                });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== input) {
            dropdown.style.display = 'none';
        }
    });

    updateSubmit();
})();

// ── Modal de paiement ───────────────────────────────────────────────────
window.openPayModal = function (requestId, amountLabel) {
    var modal = document.getElementById('pay-modal');
    var form  = document.getElementById('pay-modal-form');
    document.getElementById('pay-modal-amount').textContent = amountLabel;
    form.action = '/payment-requests/' + requestId + '/pay';
    modal.style.display = 'flex';
};

window.closePayModal = function () {
    document.getElementById('pay-modal').style.display = 'none';
};

document.getElementById('pay-modal').addEventListener('click', function (e) {
    if (e.target === this) closePayModal();
});
</script>
