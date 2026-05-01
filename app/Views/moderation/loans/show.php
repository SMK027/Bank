<?php
/** @var array  $loan */
/** @var array  $installments */
/** @var float  $totalScheduled */
/** @var float  $totalInterest */
/** @var float  $remaining */
/** @var float  $remainingInterest */
/** @var float  $schedulable */
/** @var float  $rate */
/** @var array  $types */
/** @var array  $statusLabels */
/** @var array  $statusBadge */
/** @var array  $iLabels */
/** @var array  $iBadge */
/** @var string $csrfToken */

use App\Models\Loan;
use App\Models\LoanInstallment;

$typeInfo  = $types[$loan['loan_type']] ?? null;
$canEdit   = in_array($loan['status'], [Loan::STATUS_PENDING, Loan::STATUS_ACTIVE], true);
$isPending = $loan['status'] === Loan::STATUS_PENDING;
$isActive  = $loan['status'] === Loan::STATUS_ACTIVE;
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-coin"></i> Crédit #<?= (int)$loan['id'] ?>
            — <?= htmlspecialchars($loan['owner_username'] ?? '—') ?>
        </h1>
        <p class="page-description">
            <?= $typeInfo ? htmlspecialchars($typeInfo['label']) : htmlspecialchars($loan['loan_type']) ?>
            &bull; Accordé le <?= $loan['granted_at'] ? date('d/m/Y', strtotime($loan['granted_at'])) : '—' ?>
            par <?= htmlspecialchars($loan['moderator_username'] ?? '—') ?>
        </p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/loans" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Liste des crédits
        </a>
        <a href="/accounts/<?= (int)$loan['account_id'] ?>" class="btn btn-outline btn-sm">
            <i class="bi bi-wallet2"></i> Voir le compte
        </a>
        <?php if ($canEdit): ?>
        <form method="POST"
              action="/moderation/loans/<?= (int)$loan['id'] ?>/cancel"
              onsubmit="return confirm('Annuler ce crédit ?\n\nSi des fonds ont été versés, un débit sera effectué.\nToutes les mensualités déjà payées seront remboursées.')">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-x-octagon"></i> Annuler le crédit
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ── KPIs ──────────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem">Montant accordé</div>
        <div style="font-size:1.4rem;font-weight:700;color:var(--primary)"><?= number_format((float)$loan['amount'], 2, ',', ' ') ?> €</div>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem">Remboursé</div>
        <div style="font-size:1.4rem;font-weight:700;color:var(--success)"><?= number_format((float)$loan['amount_repaid'], 2, ',', ' ') ?> €</div>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem">Restant dû</div>
        <div style="font-size:1.4rem;font-weight:700;color:var(--danger)"><?= number_format($remaining, 2, ',', ' ') ?> €</div>
        <?php if ($remainingInterest > 0): ?>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px">
            dont <?= number_format($remainingInterest, 2, ',', ' ') ?> € d'intérêts
        </div>
        <?php endif; ?>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem">Taux annuel</div>
        <div style="font-size:1.4rem;font-weight:700"><?= number_format((float)$loan['annual_rate'], 2, ',', ' ') ?> %</div>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem">Statut</div>
        <div><span class="badge <?= htmlspecialchars($statusBadge[$loan['status']] ?? 'badge-secondary') ?>" style="font-size:0.9rem">
            <?= htmlspecialchars($statusLabels[$loan['status']] ?? $loan['status']) ?>
        </span></div>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.25rem">Versement des fonds</div>
        <?php if (!empty($loan['disburse_funds'])): ?>
            <span class="badge badge-success"><i class="bi bi-cash-stack"></i> Fonds versés</span>
        <?php else: ?>
            <span class="badge badge-secondary"><i class="bi bi-slash-circle"></i> Sans versement</span>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($loan['notes'])): ?>
<div class="card mb-3" style="border-left:4px solid var(--info);">
    <div class="card-body" style="padding:0.75rem 1rem;">
        <strong><i class="bi bi-chat-left-text"></i> Notes internes :</strong>
        <p style="margin:0.25rem 0 0;"><?= nl2br(htmlspecialchars($loan['notes'])) ?></p>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">

    <!-- ── Modifier le taux ──────────────────────────────────────────────── -->
    <?php if ($canEdit): ?>
    <div class="card">
        <div class="card-header">
            <h3 style="margin:0;font-size:1rem;"><i class="bi bi-percent"></i> Modifier le taux</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/moderation/loans/<?= (int)$loan['id'] ?>/rate">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label for="rate_input" class="form-label" style="font-size:0.85rem;">Nouveau taux annuel (%)</label>
                    <div style="display:flex;gap:0.5rem;">
                        <input type="number" id="rate_input" name="annual_rate" class="form-control"
                               min="0" max="100" step="0.01"
                               value="<?= number_format((float)$loan['annual_rate'], 2, '.', '') ?>">
                        <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap">
                            <i class="bi bi-check"></i> Appliquer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Infos compte ──────────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin:0;font-size:1rem;"><i class="bi bi-wallet2"></i> Compte bénéficiaire</h3>
        </div>
        <div class="card-body" style="font-size:0.9rem;">
            <p style="margin:0 0 0.35rem"><strong>Compte :</strong> <?= htmlspecialchars($loan['account_name'] ?? '—') ?></p>
            <p style="margin:0 0 0.35rem"><strong>Titulaire :</strong> <?= htmlspecialchars($loan['owner_username'] ?? '—') ?></p>
            <p style="margin:0"><strong>Accepté le :</strong>
                <?= $loan['accepted_at'] ? date('d/m/Y à H:i', strtotime($loan['accepted_at'])) : '<span style="color:var(--text-muted)">—</span>' ?>
            </p>
        </div>
    </div>
</div>

<!-- ── Échéancier ──────────────────────────────────────────────────────────── -->
<div class="card mt-3">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <h3 style="margin:0;font-size:1rem;"><i class="bi bi-calendar3"></i> Échéancier</h3>
        <div style="font-size:0.82rem;color:var(--text-muted)">
            Planifié : <strong><?= number_format($totalScheduled, 2, ',', ' ') ?> €</strong>
            / <?= number_format((float)$loan['amount'], 2, ',', ' ') ?> € capital
            <?php if ($totalInterest > 0): ?>
            — <span style="color:var(--warning)">dont <?= number_format($totalInterest, 2, ',', ' ') ?> € d'intérêts</span>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            — Capital restant : <strong style="color:<?= $schedulable > 0 ? 'var(--success)' : 'var(--text-muted)' ?>">
                <?= number_format($schedulable, 2, ',', ' ') ?> €
            </strong>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($installments)): ?>
        <div class="card-body" style="text-align:center;color:var(--text-muted);padding:1.5rem;">
            <i class="bi bi-calendar-x" style="font-size:1.8rem;margin-bottom:0.5rem;display:block"></i>
            Aucune échéance planifiée.
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date d'échéance</th>
                    <th class="text-right">Montant</th>
                    <th class="text-center">Statut</th>
                    <th>Payée le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installments as $inst): ?>
                <tr>
                    <td style="font-size:0.8rem;color:var(--text-muted)">#<?= $inst['id'] ?></td>
                    <td><?= date('d/m/Y', strtotime($inst['due_date'])) ?></td>
                    <td class="text-right fw-medium">
                        <?= number_format((float)$inst['amount'], 2, ',', ' ') ?> €
                        <?php if ((float)$inst['interest'] > 0 || (float)$inst['penalty'] > 0): ?>
                        <div style="font-size:0.72rem;color:var(--text-muted);font-weight:400;margin-top:2px;white-space:nowrap">
                            <?= number_format((float)$inst['principal'], 2, ',', ' ') ?> € capital
                            <?php if ((float)$inst['interest'] > 0): ?>
                            + <?= number_format((float)$inst['interest'], 2, ',', ' ') ?> € int.
                            <?php endif; ?>
                            <?php if ((float)$inst['penalty'] > 0): ?>
                            + <span style="color:var(--danger)"><?= number_format((float)$inst['penalty'], 2, ',', ' ') ?> € pénalité</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= htmlspecialchars($iBadge[$inst['status']] ?? 'badge-secondary') ?>">
                            <?= htmlspecialchars($iLabels[$inst['status']] ?? $inst['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:0.82rem;color:var(--text-muted)">
                        <?= $inst['paid_at'] ? date('d/m/Y', strtotime($inst['paid_at'])) : '—' ?>
                    </td>
                    <td>
                        <?php if ($inst['status'] === LoanInstallment::STATUS_PENDING && $canEdit): ?>
                        <div style="display:flex;gap:0.3rem;align-items:center">
                        <form method="POST"
                              action="/moderation/loans/<?= (int)$loan['id'] ?>/installments/<?= (int)$inst['id'] ?>/cancel"
                              onsubmit="return confirm('Annuler cette échéance ?')">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger)"
                                    title="Annuler l'échéance">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline js-open-reschedule"
                                style="color:var(--secondary,#6b7280);border-color:var(--secondary,#6b7280)"
                                title="Modifier la date d'échéance"
                                data-inst-id="<?= (int)$inst['id'] ?>"
                                data-loan-id="<?= (int)$loan['id'] ?>"
                                data-date="<?= e(date('d/m/Y', strtotime($inst['due_date']))) ?>"
                                data-base="<?= (float)$inst['principal'] + (float)$inst['interest'] ?>"
                                data-penalty="<?= number_format((float)($inst['penalty'] ?? 0), 2, '.', '') ?>"
                                data-new-date="<?= e($inst['due_date']) ?>">
                            <i class="bi bi-calendar2-event"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline js-open-penalty"
                                style="color:var(--danger);border-color:var(--danger)"
                                title="Modifier la pénalité de retard"
                                data-inst-id="<?= (int)$inst['id'] ?>"
                                data-loan-id="<?= (int)$loan['id'] ?>"
                                data-date="<?= e(date('d/m/Y', strtotime($inst['due_date']))) ?>"
                                data-base="<?= (float)$inst['principal'] + (float)$inst['interest'] ?>"
                                data-penalty="<?= number_format((float)($inst['penalty'] ?? 0), 2, '.', '') ?>">
                            <i class="bi bi-exclamation-triangle"></i>
                        </button>
                        </div>
                        <?php elseif ($inst['status'] === LoanInstallment::STATUS_PAID): ?>
                        <form method="POST"
                              action="/moderation/loans/<?= (int)$loan['id'] ?>/installments/<?= (int)$inst['id'] ?>/refund"
                              onsubmit="return confirm('Rembourser cette mensualité de <?= number_format((float)$inst['amount'], 2, ',', ' ') ?> € ?')">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--info);border-color:var(--info)"
                                    title="Rembourser cette mensualité">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                        <?php elseif ($inst['status'] === LoanInstallment::STATUS_FAILED && $canEdit): ?>
                        <button type="button" class="btn btn-sm btn-outline js-open-reschedule"
                                style="color:var(--warning);border-color:var(--warning)"
                                title="Replanifier cette mensualité"
                                data-inst-id="<?= (int)$inst['id'] ?>"
                                data-loan-id="<?= (int)$loan['id'] ?>"
                                data-date="<?= e(date('d/m/Y', strtotime($inst['due_date']))) ?>"
                                data-base="<?= (float)$inst['principal'] + (float)$inst['interest'] ?>"
                                data-penalty="<?= number_format((float)($inst['penalty'] ?? 0), 2, '.', '') ?>">
                            <i class="bi bi-calendar-event"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-size:0.85rem;background:rgba(0,0,0,.02)">
                    <td colspan="2" class="fw-medium">Total planifié</td>
                    <td class="text-right fw-medium">
                        <?= number_format($totalScheduled, 2, ',', ' ') ?> €
                        <?php if ($totalInterest > 0): ?>
                        <div style="font-size:0.72rem;color:var(--text-muted);font-weight:400;margin-top:2px">
                            dont <?= number_format($totalInterest, 2, ',', ' ') ?> € d'intérêts
                        </div>
                        <?php endif; ?>
                    </td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- ── Formulaire d'ajout d'échéance ──────────────────────────────────── -->
    <?php if ($canEdit && $schedulable > 0): ?>
    <div class="card-body" style="border-top:1px solid var(--border-color);padding:1rem;">
        <h4 style="margin:0 0 0.75rem;font-size:0.9rem;font-weight:600;">
            <i class="bi bi-calendar-plus"></i> Ajouter une échéance
        </h4>
        <form method="POST" action="/moderation/loans/<?= (int)$loan['id'] ?>/installments">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:2px">Date d'échéance</label>
                    <input type="date" name="due_date" class="form-control"
                           min="<?= date('Y-m-d') ?>" required
                           style="font-size:0.85rem;padding:0.35rem 0.6rem;height:auto;width:auto">
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:2px">
                        Montant total (€)
                        <span style="font-weight:400;color:var(--text-muted)">
                            — Capital restant : <?= number_format($schedulable, 2, ',', ' ') ?> €
                            <?php if ($rate > 0): ?>
                            / Intérêts si remboursement total : <span id="interest_preview"><?= number_format(round($schedulable * $rate, 2), 2, ',', ' ') ?></span> €
                            <?php endif; ?>
                        </span>
                    </label>
                    <input type="number" id="installment_amount" name="amount" class="form-control"
                           min="0.01" step="0.01"
                           placeholder="<?= number_format(round($schedulable * (1 + $rate), 2), 2, '.', '') ?>"
                           required
                           style="font-size:0.85rem;padding:0.35rem 0.6rem;height:auto;width:150px">
                    <?php if ($rate > 0): ?>
                    <div id="breakdown_hint" style="font-size:0.75rem;color:var(--text-muted);margin-top:3px;display:none">
                        Capital : <strong id="principal_preview">—</strong> €
                        &nbsp;+&nbsp; Intérêts : <strong id="interest_prev2">—</strong> €
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar-plus"></i> Ajouter
                </button>
            </div>
        </form>
        <?php if ($rate > 0): ?>
        <script>
        (function() {
            var RATE        = <?= $rate ?>;
            var SCHEDULABLE = <?= $schedulable ?>;
            var amountInput   = document.getElementById('installment_amount');
            var hintEl        = document.getElementById('breakdown_hint');
            var principalEl   = document.getElementById('principal_preview');
            var interestEl    = document.getElementById('interest_prev2');

            function fmt(v) {
                return v.toFixed(2).replace('.', ',');
            }
            function update() {
                var amount    = parseFloat(amountInput.value.replace(',', '.')) || 0;
                var interest  = Math.round(amount * RATE / (1 + RATE) * 100) / 100;
                var principal = Math.round((amount - interest) * 100) / 100;
                if (amount > 0) {
                    hintEl.style.display = 'block';
                    principalEl.textContent = fmt(Math.max(0, principal));
                    interestEl.textContent  = fmt(interest);
                } else {
                    hintEl.style.display = 'none';
                }
            }
            amountInput.addEventListener('input', update);
        })();
        </script>
        <?php endif; ?>
    </div>
    <?php elseif ($canEdit && $schedulable <= 0): ?>
    <div class="card-body" style="border-top:1px solid var(--border-color);padding:0.75rem 1rem;
         font-size:0.83rem;color:var(--text-muted);text-align:center;">
        <i class="bi bi-check-circle text-success"></i>
        Le montant total du crédit est entièrement planifié.
    </div>
    <?php endif; ?>
</div>

<!-- ── Modal : modifier la pénalité ──────────────────────────────────────── -->
<div id="modal-penalty"
     role="dialog" aria-modal="true" aria-labelledby="modal-penalty-title"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:1rem">
    <div style="background:var(--white,#fff);border-radius:var(--border-radius,8px);
                box-shadow:0 8px 40px rgba(0,0,0,0.25);padding:1.5rem;
                width:100%;max-width:420px;position:relative">
        <button type="button" id="modal-penalty-close"
                aria-label="Fermer"
                style="position:absolute;top:0.75rem;right:1rem;background:none;
                       border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);line-height:1">
            &times;
        </button>
        <h3 id="modal-penalty-title" style="margin:0 0 0.25rem;font-size:1.05rem;color:var(--danger)">
            <i class="bi bi-exclamation-triangle"></i> Pénalité de retard
        </h3>
        <p id="modal-penalty-subtitle" style="margin:0 0 1.25rem;font-size:0.83rem;color:var(--text-muted)"></p>
        <form id="modal-penalty-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="form-group" style="margin-bottom:1rem">
                <label for="modal-penalty-amount" style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px">
                    Montant de la pénalité (€)
                    <span style="font-weight:400;color:var(--text-muted)"> — 0 pour supprimer</span>
                </label>
                <input type="number" id="modal-penalty-amount" name="penalty"
                       class="form-control" min="0" step="0.01" placeholder="0.00">
            </div>
            <div style="background:var(--light,#f3f4f6);border-radius:6px;padding:0.75rem;margin-bottom:1.25rem;font-size:0.84rem">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                    <span style="color:var(--text-muted)">Capital + intérêts</span>
                    <strong id="modal-penalty-base">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                    <span style="color:var(--text-muted)">Pénalité</span>
                    <strong id="modal-penalty-pen" style="color:var(--danger)">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border-color,#e5e7eb);padding-top:0.4rem;margin-top:0.4rem">
                    <span style="font-weight:600">Nouveau total</span>
                    <strong id="modal-penalty-total" style="font-size:1rem">—</strong>
                </div>
            </div>
            <div style="display:flex;gap:0.5rem">
                <button type="submit" class="btn btn-danger" style="flex:1">
                    <i class="bi bi-check2"></i> Appliquer
                </button>
                <button type="button" id="modal-penalty-cancel" class="btn btn-outline" style="flex:1">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal : replanifier ───────────────────────────────────────────────── -->
<div id="modal-reschedule"
     role="dialog" aria-modal="true" aria-labelledby="modal-reschedule-title"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:1rem">
    <div style="background:var(--white,#fff);border-radius:var(--border-radius,8px);
                box-shadow:0 8px 40px rgba(0,0,0,0.25);padding:1.5rem;
                width:100%;max-width:440px;position:relative">
        <button type="button" id="modal-reschedule-close"
                aria-label="Fermer"
                style="position:absolute;top:0.75rem;right:1rem;background:none;
                       border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);line-height:1">
            &times;
        </button>
        <h3 id="modal-reschedule-title" style="margin:0 0 0.25rem;font-size:1.05rem;color:var(--warning,#f59e0b)">
            <i class="bi bi-calendar-event"></i> Replanifier la mensualité
        </h3>
        <p id="modal-reschedule-subtitle" style="margin:0 0 1.25rem;font-size:0.83rem;color:var(--text-muted)"></p>
        <form id="modal-reschedule-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="form-group" style="margin-bottom:1rem">
                <label for="modal-reschedule-date" style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px">Nouvelle date d'échéance</label>
                <input type="date" id="modal-reschedule-date" name="new_due_date"
                       class="form-control"
                       min="<?= date('Y-m-d') ?>"
                       value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label for="modal-reschedule-penalty" style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:4px">
                    Pénalité de retard (€)
                    <span style="font-weight:400;color:var(--text-muted)"> — optionnel</span>
                </label>
                <input type="number" id="modal-reschedule-penalty" name="penalty"
                       class="form-control" min="0" step="0.01" value="0" placeholder="0.00">
            </div>
            <div style="background:var(--light,#f3f4f6);border-radius:6px;padding:0.75rem;margin-bottom:1.25rem;font-size:0.84rem">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                    <span style="color:var(--text-muted)">Capital + intérêts</span>
                    <strong id="modal-reschedule-base">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                    <span style="color:var(--text-muted)">Pénalité</span>
                    <strong id="modal-reschedule-pen" style="color:var(--danger)">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border-color,#e5e7eb);padding-top:0.4rem;margin-top:0.4rem">
                    <span style="font-weight:600">Nouveau total</span>
                    <strong id="modal-reschedule-total" style="font-size:1rem">—</strong>
                </div>
            </div>
            <div style="display:flex;gap:0.5rem">
                <button type="submit" class="btn btn-warning" style="flex:1">
                    <i class="bi bi-calendar-check"></i> Confirmer
                </button>
                <button type="button" id="modal-reschedule-cancel" class="btn btn-outline" style="flex:1">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    function fmt(v) {
        return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',') + ' €';
    }

    /* ── Penalty modal ── */
    var mPen      = document.getElementById('modal-penalty');
    var mPenForm  = document.getElementById('modal-penalty-form');
    var mPenSub   = document.getElementById('modal-penalty-subtitle');
    var mPenInput = document.getElementById('modal-penalty-amount');
    var mPenBase  = document.getElementById('modal-penalty-base');
    var mPenPen   = document.getElementById('modal-penalty-pen');
    var mPenTotal = document.getElementById('modal-penalty-total');
    var penBase   = 0;

    function updatePenPreview() {
        var p = parseFloat(mPenInput.value) || 0;
        mPenPen.textContent   = fmt(p);
        mPenTotal.textContent = fmt(penBase + p);
    }
    mPenInput.addEventListener('input', updatePenPreview);

    document.querySelectorAll('.js-open-penalty').forEach(function (btn) {
        btn.addEventListener('click', function () {
            penBase = parseFloat(this.dataset.base) || 0;
            mPenForm.action = '/moderation/loans/' + this.dataset.loanId + '/installments/' + this.dataset.instId + '/penalty';
            mPenSub.textContent = 'Mensualité du ' + this.dataset.date;
            mPenInput.value = this.dataset.penalty;
            mPenBase.textContent  = fmt(penBase);
            updatePenPreview();
            mPen.style.display = 'flex';
            mPenInput.focus();
        });
    });

    function closePenModal() { mPen.style.display = 'none'; }
    document.getElementById('modal-penalty-close').addEventListener('click', closePenModal);
    document.getElementById('modal-penalty-cancel').addEventListener('click', closePenModal);
    mPen.addEventListener('click', function (e) { if (e.target === mPen) closePenModal(); });

    /* ── Reschedule modal ── */
    var mRs      = document.getElementById('modal-reschedule');
    var mRsForm  = document.getElementById('modal-reschedule-form');
    var mRsSub   = document.getElementById('modal-reschedule-subtitle');
    var mRsPen   = document.getElementById('modal-reschedule-penalty');
    var mRsBase  = document.getElementById('modal-reschedule-base');
    var mRsPenEl = document.getElementById('modal-reschedule-pen');
    var mRsTotal = document.getElementById('modal-reschedule-total');
    var rsBase   = 0;

    function updateRsPreview() {
        var p = parseFloat(mRsPen.value) || 0;
        mRsPenEl.textContent = fmt(p);
        mRsTotal.textContent = fmt(rsBase + p);
    }
    mRsPen.addEventListener('input', updateRsPreview);

    document.querySelectorAll('.js-open-reschedule').forEach(function (btn) {
        btn.addEventListener('click', function () {
            rsBase = parseFloat(this.dataset.base) || 0;
            var isFailed = !this.dataset.newDate;
            mRsForm.action = '/moderation/loans/' + this.dataset.loanId + '/installments/' + this.dataset.instId + '/reschedule';
            mRsSub.textContent = (isFailed ? 'Échéance échouée du ' : 'Échéance du ') + this.dataset.date;
            mRsPen.value = this.dataset.penalty;
            mRsBase.textContent = fmt(rsBase);
            /* pré-remplir la date : pour pending = date actuelle, pour failed = +30j */
            var dateInput = document.getElementById('modal-reschedule-date');
            dateInput.value = this.dataset.newDate || dateInput.defaultValue;
            updateRsPreview();
            mRs.style.display = 'flex';
            dateInput.focus();
        });
    });

    function closeRsModal() { mRs.style.display = 'none'; }
    document.getElementById('modal-reschedule-close').addEventListener('click', closeRsModal);
    document.getElementById('modal-reschedule-cancel').addEventListener('click', closeRsModal);
    mRs.addEventListener('click', function (e) { if (e.target === mRs) closeRsModal(); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (mPen.style.display === 'flex') closePenModal();
        if (mRs.style.display  === 'flex') closeRsModal();
    });
}());
</script>
