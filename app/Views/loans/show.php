<?php
/** @var array  $loan */
/** @var array  $installments */
/** @var float  $remaining */
/** @var array  $types */
/** @var array  $statusLabels */
/** @var array  $statusBadge */
/** @var array  $iLabels */
/** @var array  $iBadge */
/** @var string $csrfToken */

use App\Models\Loan;
use App\Models\LoanInstallment;

$typeInfo  = $types[$loan['loan_type']] ?? null;
$pct       = $loan['amount'] > 0 ? min(100, round($loan['amount_repaid'] / $loan['amount'] * 100)) : 0;
$isPending = $loan['status'] === Loan::STATUS_PENDING;
$isActive  = $loan['status'] === Loan::STATUS_ACTIVE;
?>
<div class="page-header">
    <div>
        <h1>
            <i class="bi <?= $typeInfo ? htmlspecialchars($typeInfo['icon']) : 'bi-cash-coin' ?>"></i>
            <?= $typeInfo ? htmlspecialchars($typeInfo['label']) : htmlspecialchars($loan['loan_type']) ?>
            <span style="font-size:1rem;font-weight:400;color:var(--text-muted)">#<?= (int)$loan['id'] ?></span>
        </h1>
        <p class="page-description">
            Compte : <?= htmlspecialchars($loan['account_name'] ?? '—') ?>
            &bull; <?= number_format((float)$loan['annual_rate'], 2, ',', ' ') ?> % annuel
        </p>
    </div>
    <div>
        <a href="/loans" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Mes crédits
        </a>
    </div>
</div>

<!-- ── Alerte acceptation ─────────────────────────────────────────────────── -->
<?php if ($isPending): ?>
<div class="alert alert-warning" style="margin-bottom:1.5rem;">
    <div style="display:flex;align-items:flex-start;gap:0.75rem;">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.4rem;flex-shrink:0;margin-top:0.1rem"></i>
        <div style="flex:1">
            <strong style="display:block;margin-bottom:0.4rem">Offre de crédit en attente de votre réponse</strong>
            <p style="margin:0 0 0.75rem;font-size:0.9rem;">
                Un crédit de <strong><?= number_format((float)$loan['amount'], 2, ',', ' ') ?> €</strong>
                au taux de <strong><?= number_format((float)$loan['annual_rate'], 2, ',', ' ') ?> %</strong> par an
                vous a été proposé sur le compte <strong>« <?= htmlspecialchars($loan['account_name'] ?? '') ?> »</strong>.
                Si vous acceptez, le montant sera immédiatement crédité sur ce compte.
            </p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <form method="POST" action="/loans/<?= (int)$loan['id'] ?>/accept">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Confirmer l\'acceptation du crédit ? Le montant sera crédité immédiatement.')">
                        <i class="bi bi-check-circle"></i> Accepter et recevoir les fonds
                    </button>
                </form>
                <form method="POST" action="/loans/<?= (int)$loan['id'] ?>/reject">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger)"
                            onclick="return confirm('Refuser ce crédit ? Cette action est irréversible.')">
                        <i class="bi bi-x-circle"></i> Refuser
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── KPIs ──────────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.2rem">Montant accordé</div>
        <div style="font-size:1.3rem;font-weight:700;color:var(--primary)"><?= number_format((float)$loan['amount'], 2, ',', ' ') ?> €</div>
    </div>
    <?php if ($isActive): ?>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.2rem">Remboursé</div>
        <div style="font-size:1.3rem;font-weight:700;color:var(--success)"><?= number_format((float)$loan['amount_repaid'], 2, ',', ' ') ?> €</div>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.2rem">Restant dû</div>
        <div style="font-size:1.3rem;font-weight:700;color:var(--danger)"><?= number_format($remaining, 2, ',', ' ') ?> €</div>
    </div>
    <?php endif; ?>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.2rem">Taux annuel</div>
        <div style="font-size:1.3rem;font-weight:700"><?= number_format((float)$loan['annual_rate'], 2, ',', ' ') ?> %</div>
    </div>
    <div class="card" style="padding:1rem;">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.2rem">Statut</div>
        <div><span class="badge <?= htmlspecialchars($statusBadge[$loan['status']] ?? 'badge-secondary') ?>" style="font-size:0.9rem">
            <?= htmlspecialchars($statusLabels[$loan['status']] ?? $loan['status']) ?>
        </span></div>
    </div>
</div>

<!-- ── Barre de progression ──────────────────────────────────────────────── -->
<?php if ($isActive): ?>
<div class="card mb-3" style="padding:1rem 1.25rem;">
    <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:var(--text-muted);margin-bottom:5px">
        <span>Progression du remboursement</span>
        <span><?= $pct ?> %</span>
    </div>
    <div style="height:10px;background:var(--gray-light);border-radius:5px;overflow:hidden;">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--success);border-radius:5px;transition:width .4s;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ── Échéancier ──────────────────────────────────────────────────────────── -->
<?php if (!empty($installments)): ?>
<div class="card">
    <div class="card-header">
        <h3 style="margin:0;font-size:1rem;"><i class="bi bi-calendar3"></i> Échéancier</h3>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date d'échéance</th>
                    <th class="text-right">Montant</th>
                    <th class="text-center">Statut</th>
                    <th>Payée le</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installments as $inst): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($inst['due_date'])) ?></td>
                    <td class="text-right fw-medium"><?= number_format((float)$inst['amount'], 2, ',', ' ') ?> €</td>
                    <td class="text-center">
                        <span class="badge <?= htmlspecialchars($iBadge[$inst['status']] ?? 'badge-secondary') ?>">
                            <?= htmlspecialchars($iLabels[$inst['status']] ?? $inst['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:0.82rem;color:var(--text-muted)">
                        <?= $inst['paid_at'] ? date('d/m/Y', strtotime($inst['paid_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding:1.5rem;text-align:center;color:var(--text-muted);">
    <i class="bi bi-calendar-x" style="font-size:1.8rem;margin-bottom:0.5rem;display:block"></i>
    Aucun échéancier défini pour ce crédit pour l'instant.
</div>
<?php endif; ?>
