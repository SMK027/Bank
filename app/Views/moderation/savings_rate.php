<div class="page-header">
    <div>
        <h1><i class="bi bi-percent"></i> Taux d'intérêt maximum — Épargne</h1>
        <p class="page-description">Définissez le taux annuel brut <strong>maximum</strong> autorisé par type de compte. Les utilisateurs fixent leur propre taux dans la configuration de leur compte, dans la limite de ce plafond.</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-percent"></i> Taux d'intérêt</span>
    </div>
</div>

<?php
use App\Models\Account;
$typeLabels = array_column(Account::TYPES, 'label', null);
// $typeLabels est indexé par clé de tableau, on refait la map correctement
$typeMap = [];
foreach (Account::TYPES as $key => $def) { $typeMap[$key] = $def['label']; }
?>

<!-- Taux actuels par type -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-gear"></i> Taux actuels par type de compte</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Type de compte</th>
                    <th>Taux maximum actif</th>
                    <th>Actif depuis</th>
                    <th>Par</th>
                    <th>Nouveau taux (%)</th>
                    <th style="width:150px;">Date d'effet</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eligibleTypes as $accountType): ?>
                <?php $current = $allRates[$accountType] ?? null; ?>
                <tr>
                    <td>
                        <strong><?= e($typeMap[$accountType] ?? $accountType) ?></strong>
                        <code style="font-size:0.75rem;margin-left:0.4rem;color:var(--text-muted,#6b7280);"><?= e($accountType) ?></code>
                    </td>
                    <td>
                        <?php if ($current): ?>
                            <span style="font-size:1.1rem;font-weight:700;color:var(--success,#16a34a);">
                                <?= number_format((float) $current['rate'] * 100, 2, ',', ' ') ?> %
                            </span>
                        <?php else: ?>
                            <span class="text-muted"><i class="bi bi-dash"></i> Non configuré (aucune limite)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= $current ? date('d/m/Y à H\hi', strtotime($current['created_at'])) : '—' ?>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= $current ? e($current['set_by_username'] ?? '—') : '—' ?>
                    </td>
                    <td colspan="3">
                        <form method="POST" action="/moderation/savings-rate"
                              style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_type" value="<?= e($accountType) ?>">
                            <input type="number" name="rate" class="form-control"
                                   style="padding:0.3rem 0.5rem;font-size:0.85rem;width:100px;"
                                   min="0" max="100" step="0.01" required
                                   placeholder="Ex : 3.00"
                                   value="<?= $current ? htmlspecialchars(number_format((float) $current['rate'] * 100, 2, '.', ''), ENT_QUOTES) : '' ?>">
                            <input type="date" name="effective_date" class="form-control"
                                   style="padding:0.3rem 0.5rem;font-size:0.85rem;width:145px;"
                                   min="<?= date('Y') . '-01-01' ?>"
                                   max="<?= date('Y') . '-12-31' ?>"
                                   title="Laisser vide pour appliquer immédiatement">
                            <span class="form-hint" style="font-size:0.72rem;color:var(--text-muted,#6b7280);white-space:nowrap;">
                                date vide = maintenant
                            </span>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-check-lg"></i> Enregistrer
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Intérêts en cours — aperçu indicatif -->
<div class="card" style="margin-bottom:1.5rem;border-left:4px solid #10b981;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:0.6rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <i class="bi bi-graph-up-arrow" style="color:#10b981;font-size:1.1rem;"></i>
            <h3 style="margin:0;">Intérêts en cours <?= (int) date('Y') ?> <span style="font-size:0.7em;font-weight:400;color:var(--text-muted,#6b7280);">(aperçu indicatif)</span></h3>
        </div>
        <a href="/moderation/savings-rate?preview=1<?= $filterType ? '&type=' . urlencode($filterType) : '' ?>"
           class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-clockwise"></i> Actualiser l'aperçu
        </a>
    </div>
    <div class="card-body">
        <p style="margin:0 0 0.8rem;color:var(--text-muted,#6b7280);font-size:0.85rem;">
            Estimation des intérêts accumulés depuis le 1<sup>er</sup> janvier <?= (int) date('Y') ?>
            jusqu'à aujourd'hui, calculée au prorata temporis (TWAB) pour chaque compte ayant un taux configuré.
            <strong>Aucune écriture en base — valeur purement indicative.</strong>
        </p>
        <?php if ($previewResults === null): ?>
            <p class="text-muted" style="font-size:0.88rem;margin:0;">
                <i class="bi bi-info-circle"></i> Cliquez sur « Actualiser l'aperçu » pour calculer.
            </p>
        <?php elseif (empty($previewResults)): ?>
            <p class="text-muted" style="font-size:0.88rem;margin:0;">
                <i class="bi bi-dash-circle"></i> Aucun compte éligible avec un taux configuré.
            </p>
        <?php else: ?>
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Titulaire</th>
                        <th>Type</th>
                        <th>Taux</th>
                        <th style="text-align:right;">Intérêts en cours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewResults as $row): ?>
                    <tr>
                        <td>
                            <a href="/accounts/<?= (int) $row['account_id'] ?>"><?= e($row['account_name']) ?></a>
                        </td>
                        <td class="text-muted"><?= e($row['username']) ?></td>
                        <td><code style="font-size:0.75rem;"><?= e($row['account_type']) ?></code></td>
                        <td><?= number_format($row['rate'] * 100, 2, ',', ' ') ?> %</td>
                        <td style="text-align:right;font-weight:600;color:var(--success,#16a34a);">
                            <?php if ($row['accrued'] > 0): ?>
                                +<?= number_format($row['accrued'], 2, ',', ' ') ?> <?= e($row['currency']) ?>
                            <?php else: ?>
                                <span class="text-muted">0,00</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php
                    $total = array_sum(array_column($previewResults, 'accrued'));
                    $currencies = array_unique(array_column($previewResults, 'currency'));
                ?>
                <?php if (count($currencies) === 1): ?>
                <tfoot>
                    <tr style="font-weight:700;border-top:2px solid var(--border-color,#e5e7eb);">
                        <td colspan="4" style="text-align:right;">Total</td>
                        <td style="text-align:right;color:var(--success,#16a34a);">
                            +<?= number_format($total, 2, ',', ' ') ?> <?= e($currencies[0]) ?>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            <p style="margin:0.6rem 0 0;font-size:0.78rem;color:var(--text-muted,#6b7280);">
                <i class="bi bi-clock"></i> Calculé le <?= date('d/m/Y à H\hi') ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Historique -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <h3 style="margin:0;"><i class="bi bi-clock-history"></i> Historique des taux</h3>
        <div style="display:flex;gap:0.4rem;align-items:center;">
            <span class="text-muted" style="font-size:0.85rem;">Filtrer :</span>
            <?php foreach (array_merge([''], $eligibleTypes) as $t): ?>
            <a href="/moderation/savings-rate<?= $t !== '' ? '?type=' . urlencode($t) : '' ?>"
               class="btn btn-sm <?= ($filterType === ($t !== '' ? $t : null)) || ($filterType === null && $t === '') ? 'btn-primary' : 'btn-outline' ?>">
                <?= $t !== '' ? e($typeMap[$t] ?? $t) : 'Tous' ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($history)): ?>
            <p class="text-muted" style="padding:1rem;">Aucun historique disponible.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type de compte</th>
                        <th>Taux</th>
                        <th>Modérateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <?php $isFuture = strtotime($h['created_at']) > time(); ?>
                    <tr <?= $isFuture ? 'style="opacity:0.75;"' : '' ?>>
                        <td>
                            <?= date('d/m/Y à H\hi', strtotime($h['created_at'])) ?>
                            <?php if ($isFuture): ?>
                                <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;font-size:0.7em;margin-left:0.3rem;vertical-align:middle;">
                                    <i class="bi bi-clock"></i> planifié
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($typeMap[$h['account_type']] ?? $h['account_type']) ?>
                            <code style="font-size:0.75rem;margin-left:0.3rem;color:var(--text-muted,#6b7280);"><?= e($h['account_type']) ?></code>
                        </td>
                        <td><strong><?= number_format((float) $h['rate'] * 100, 2, ',', ' ') ?> %</strong></td>
                        <td><?= e($h['set_by_username'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

