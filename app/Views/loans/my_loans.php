<div class="page-header">
    <div>
        <h1><i class="bi bi-cash-coin"></i> Mes crédits</h1>
        <p class="page-description">Crédits accordés sur vos comptes</p>
    </div>
</div>

<?php if (empty($loans)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-cash-coin" style="font-size:2.5rem;color:var(--text-muted)"></i></div>
        <p>Vous n'avez aucun crédit.</p>
    </div>
<?php else: ?>

<!-- Crédits en attente d'acceptation -->
<?php $pendingLoans = array_filter($loans, fn($l) => $l['status'] === 'pending_acceptance'); ?>
<?php if (!empty($pendingLoans)): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:1.2rem;flex-shrink:0"></i>
    <div>
        <strong>Action requise !</strong>
        Vous avez <?= count($pendingLoans) ?> offre(s) de crédit en attente de votre acceptation.
    </div>
</div>
<?php endif; ?>

<div style="display:flex;flex-direction:column;gap:1rem;">
    <?php foreach ($loans as $loan): ?>
    <?php
        $t       = $types[$loan['loan_type']] ?? null;
        $badge   = $statusBadge[$loan['status']] ?? 'badge-secondary';
        $label   = $statusLabels[$loan['status']] ?? $loan['status'];
        $pct     = $loan['amount'] > 0 ? min(100, round($loan['amount_repaid'] / $loan['amount'] * 100)) : 0;
        $remaining = round((float)$loan['amount'] - (float)$loan['amount_repaid'], 2);
    ?>
    <div class="card <?= $loan['status'] === 'pending_acceptance' ? 'card--highlight' : '' ?>"
         style="<?= $loan['status'] === 'pending_acceptance' ? 'border:1px solid var(--warning);' : '' ?>">
        <div class="card-body" style="padding:1.1rem 1.25rem;">
            <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <!-- Icône type -->
                <div style="font-size:1.6rem;color:var(--primary);flex-shrink:0;width:2.2rem;text-align:center">
                    <i class="bi <?= $t ? htmlspecialchars($t['icon']) : 'bi-cash-coin' ?>"></i>
                </div>
                <!-- Infos principales -->
                <div style="flex:1;min-width:200px;">
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.2rem;">
                        <strong style="font-size:1rem;">
                            <?= $t ? htmlspecialchars($t['label']) : htmlspecialchars($loan['loan_type']) ?>
                        </strong>
                        <span class="badge <?= htmlspecialchars($badge) ?>"><?= htmlspecialchars($label) ?></span>
                        <span style="font-size:0.78rem;color:var(--text-muted)">#<?= $loan['id'] ?></span>
                    </div>
                    <div style="font-size:0.83rem;color:var(--text-muted);margin-bottom:0.5rem;">
                        Compte : <?= htmlspecialchars($loan['account_name'] ?? '—') ?>
                        &bull; <?= number_format((float)$loan['annual_rate'], 2, ',', ' ') ?> % / an
                    </div>
                    <!-- Barre de progression remboursement -->
                    <?php if ($loan['status'] === 'active'): ?>
                    <div style="margin-top:0.4rem;">
                        <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--text-muted);margin-bottom:3px">
                            <span>Remboursé : <?= number_format((float)$loan['amount_repaid'], 2, ',', ' ') ?> €</span>
                            <span><?= $pct ?> %</span>
                        </div>
                        <div style="height:6px;background:var(--gray-light);border-radius:3px;overflow:hidden;">
                            <div style="height:100%;width:<?= $pct ?>%;background:var(--success);border-radius:3px;"></div>
                        </div>
                        <div style="text-align:right;font-size:0.78rem;color:var(--text-muted);margin-top:2px">
                            Restant dû : <?= number_format($remaining, 2, ',', ' ') ?> €
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Montant + lien -->
                <div style="text-align:right;flex-shrink:0;">
                    <div style="font-size:1.35rem;font-weight:700;color:var(--primary)">
                        <?= number_format((float)$loan['amount'], 2, ',', ' ') ?> €
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.5rem;">
                        Accordé le <?= $loan['granted_at'] ? date('d/m/Y', strtotime($loan['granted_at'])) : '—' ?>
                    </div>
                    <a href="/loans/<?= (int)$loan['id'] ?>" class="btn btn-outline btn-sm">
                        <i class="bi bi-eye"></i> Détails
                        <?php if ($loan['status'] === 'pending_acceptance'): ?>
                        &amp; Répondre
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
