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
                        <form method="POST"
                              action="/moderation/loans/<?= (int)$loan['id'] ?>/installments/<?= (int)$inst['id'] ?>/cancel"
                              onsubmit="return confirm('Annuler cette échéance ?')">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn btn-sm btn-outline" style="color:var(--danger);border-color:var(--danger)"
                                    title="Annuler l'échéance">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
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
                        <details style="position:relative">
                            <summary class="btn btn-sm btn-outline" style="color:var(--warning);border-color:var(--warning);list-style:none;cursor:pointer"
                                     title="Replanifier">
                                <i class="bi bi-calendar-event"></i>
                            </summary>
                            <div style="position:absolute;right:0;top:calc(100% + 4px);background:var(--white);border:1px solid var(--border-color);
                                        border-radius:var(--border-radius);box-shadow:var(--shadow);padding:0.75rem;min-width:260px;z-index:100">
                                <div style="font-size:0.82rem;font-weight:600;margin-bottom:0.5rem;color:var(--warning)">
                                    <i class="bi bi-exclamation-triangle"></i> Replanifier la mensualité
                                </div>
                                <form method="POST"
                                      action="/moderation/loans/<?= (int)$loan['id'] ?>/installments/<?= (int)$inst['id'] ?>/reschedule">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <div style="margin-bottom:0.5rem">
                                        <label style="font-size:0.78rem;font-weight:600;display:block;margin-bottom:2px">Nouvelle date</label>
                                        <input type="date" name="new_due_date" class="form-control"
                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                               value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required
                                               style="font-size:0.82rem;height:auto;padding:0.3rem 0.5rem">
                                    </div>
                                    <div style="margin-bottom:0.6rem">
                                        <label style="font-size:0.78rem;font-weight:600;display:block;margin-bottom:2px">
                                            Pénalité de retard (€)
                                            <span style="font-weight:400;color:var(--text-muted)">optionnel</span>
                                        </label>
                                        <input type="number" name="penalty" class="form-control"
                                               min="0" step="0.01" value="0" placeholder="0.00"
                                               style="font-size:0.82rem;height:auto;padding:0.3rem 0.5rem">
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.6rem">
                                        Montant actuel : <?= number_format((float)$inst['principal'] + (float)$inst['interest'], 2, ',', ' ') ?> €
                                        &rarr; avec pénalité : <strong id="preview_<?= $inst['id'] ?>">—</strong>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-warning" style="width:100%">
                                        <i class="bi bi-calendar-check"></i> Confirmer la replanification
                                    </button>
                                </form>
                            </div>
                        </details>
                        <script>
                        (function() {
                            var base    = <?= (float)$inst['principal'] + (float)$inst['interest'] ?>;
                            var preview = document.getElementById('preview_<?= $inst['id'] ?>');
                            var input   = document.querySelector('[name="penalty"]');
                            if (!input || !preview) return;
                            var form = input.closest('form');
                            form.querySelector('[name="penalty"]').addEventListener('input', function() {
                                var p = parseFloat(this.value) || 0;
                                preview.textContent = (Math.round((base + p) * 100) / 100).toFixed(2).replace('.', ',') + ' €';
                            });
                        })();
                        </script>
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
