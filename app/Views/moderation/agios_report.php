<?php
use App\Models\Account;
use App\Models\DirectDebit;
use App\Models\Transfer;

$currency = $account['currency'] ?? 'EUR';

function fmtBal(float $v, string $cur): string {
    return number_format($v, 2, ',', ' ') . ' ' . htmlspecialchars($cur, ENT_QUOTES);
}
function fmtDate(string $d): string {
    return date('d/m/Y à H\hi', strtotime($d));
}
function fmtDateShort(string $d): string {
    return date('d/m/Y', strtotime($d));
}
function balClass(float $v, float $limit): string {
    return $v < $limit ? 'text-danger' : ($v < 0 ? 'text-warning' : 'text-success');
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-graph-down-arrow" style="color:var(--danger);"></i> Rapport agios</h1>
        <p class="page-description">
            Compte <strong><?= e($account['name']) ?></strong>
            — titulaire&nbsp;: <strong><?= e($owner['username'] ?? '—') ?></strong>
            (<a href="/accounts/<?= (int) $account['id'] ?>">voir le compte</a>)
        </p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/accounts/<?= (int) $account['id'] ?>" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Retour au compte
        </a>
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-shield-check"></i> Modération
        </a>
    </div>
</div>

<!-- ── Bandeau récapitulatif ──────────────────────────────────────── -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
    <div class="stat-card">
        <div class="stat-value <?= $currentBalance < $overdraftLimit ? 'text-danger' : ($currentBalance < 0 ? 'text-warning' : 'text-success') ?>">
            <?= fmtBal($currentBalance, $currency) ?>
        </div>
        <div class="stat-label">Solde actuel</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $typeAllowsOd ? fmtBal($overdraft, $currency) : '—' ?></div>
        <div class="stat-label">Découvert autorisé</div>
    </div>
    <div class="stat-card">
        <div class="stat-value <?= count($episodes) > 0 ? 'text-danger' : 'text-success' ?>">
            <?= count($episodes) ?>
        </div>
        <div class="stat-label">Épisode(s) de dépassement</div>
    </div>
    <div class="stat-card">
        <?php $totalAgios = array_sum(array_column($agiosTx, 'amount')); ?>
        <div class="stat-value <?= $totalAgios > 0 ? 'text-danger' : '' ?>">
            <?= fmtBal($totalAgios, $currency) ?>
        </div>
        <div class="stat-label">Total agios prélevés</div>
    </div>
</div>

<?php if (empty($episodes)): ?>
<div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    Aucun épisode de dépassement du découvert autorisé détecté sur ce compte.
    <?php if ($currentBalance < 0 && $typeAllowsOd && $overdraft > 0): ?>
        Le compte est actuellement en découvert mais dans la limite autorisée
        (<?= fmtBal(-$overdraft, $currency) ?>).
    <?php endif; ?>
</div>
<?php else: ?>

<!-- ── Liste des épisodes ─────────────────────────────────────────── -->
<?php foreach ($episodes as $i => $ep): ?>
<?php
    $isOngoing = $ep['ended_at'] === null;
    $nbRejects = count($ep['rejected_dd']) + count($ep['failed_transfers']) + count($ep['failed_installments']);
    $cardBorder = $isOngoing ? 'var(--danger)' : 'var(--border-color)';
?>
<div class="card mt-2" style="border-left:4px solid <?= $cardBorder ?>;">
    <div class="card-body">
        <!-- En-tête de l'épisode -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
            <div>
                <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:0.5rem;">
                    <?php if ($isOngoing): ?>
                        <span class="badge badge-danger" style="font-size:0.75rem;">En cours</span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="font-size:0.75rem;">Clôturé</span>
                    <?php endif; ?>
                    Épisode #<?= count($episodes) - $i ?>
                </h3>
                <div style="margin-top:0.35rem;font-size:0.85rem;color:var(--text-muted);">
                    <i class="bi bi-calendar-event"></i>
                    Début&nbsp;: <strong><?= fmtDate($ep['started_at']) ?></strong>
                    <?php if (!$isOngoing): ?>
                        &nbsp;→&nbsp;Fin&nbsp;: <strong><?= fmtDate($ep['ended_at']) ?></strong>
                        &nbsp;·&nbsp;<strong><?= $ep['duration_days'] ?></strong> jour(s)
                    <?php else: ?>
                        &nbsp;·&nbsp;<span style="color:var(--danger);font-weight:600;">Toujours en dépassement</span>
                        &nbsp;·&nbsp;<strong><?= $ep['duration_days'] ?></strong> jour(s) depuis le début
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.82rem;color:var(--text-muted);">Dépassement max</div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--danger);">
                    <?= fmtBal($ep['max_depth'], $currency) ?>
                </div>
                <?php if (!$isOngoing): ?>
                <div style="font-size:0.78rem;color:var(--text-muted);">
                    Tx de déclenchement&nbsp;:
                    <strong><?= e($ep['start_tx']['category'] ?? '—') ?></strong>
                    (<?= fmtBal(-(float)$ep['start_tx']['amount'], $currency) ?>)
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);">
                    Tx de clôture&nbsp;:
                    <strong><?= e($ep['end_tx']['category'] ?? '—') ?></strong>
                    (+<?= fmtBal((float)$ep['end_tx']['amount'], $currency) ?>)
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rejets associés -->
        <?php if ($nbRejects > 0 || !empty($ep['agios_charged'])): ?>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.9rem;">
            <?php if ($nbRejects > 0): ?>
                <span class="badge badge-danger" style="font-size:0.78rem;">
                    <i class="bi bi-x-circle"></i>
                    <?= $nbRejects ?> rejet(s) / échec(s)
                </span>
            <?php endif; ?>
            <?php if (!empty($ep['agios_charged'])): ?>
                <?php $sumAgios = array_sum(array_column($ep['agios_charged'], 'amount')); ?>
                <span class="badge" style="background:var(--danger);color:#fff;font-size:0.78rem;">
                    <i class="bi bi-bank"></i>
                    <?= count($ep['agios_charged']) ?> agios&nbsp;·&nbsp;<?= fmtBal($sumAgios, $currency) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Onglets internes à l'épisode -->
        <?php $epId = 'ep-' . $i; ?>
        <div>
            <ul style="display:flex;flex-wrap:wrap;gap:0.3rem;list-style:none;padding:0;margin:0 0 0.75rem;">
                <li>
                    <button type="button" class="btn btn-sm btn-outline ep-tab active"
                            data-ep="<?= $epId ?>" data-tab="timeline"
                            onclick="showEpTab('<?= $epId ?>', 'timeline', this)">
                        <i class="bi bi-clock-history"></i> Évolution du solde (<?= count($ep['snapshot']) ?>)
                    </button>
                </li>
                <?php if (!empty($ep['rejected_dd'])): ?>
                <li>
                    <button type="button" class="btn btn-sm btn-outline ep-tab"
                            data-ep="<?= $epId ?>" data-tab="dd"
                            onclick="showEpTab('<?= $epId ?>', 'dd', this)">
                        <i class="bi bi-file-earmark-x"></i> Prélèv. rejetés (<?= count($ep['rejected_dd']) ?>)
                    </button>
                </li>
                <?php endif; ?>
                <?php if (!empty($ep['failed_transfers'])): ?>
                <li>
                    <button type="button" class="btn btn-sm btn-outline ep-tab"
                            data-ep="<?= $epId ?>" data-tab="tr"
                            onclick="showEpTab('<?= $epId ?>', 'tr', this)">
                        <i class="bi bi-arrow-left-right"></i> Virements échoués (<?= count($ep['failed_transfers']) ?>)
                    </button>
                </li>
                <?php endif; ?>
                <?php if (!empty($ep['failed_installments'])): ?>
                <li>
                    <button type="button" class="btn btn-sm btn-outline ep-tab"
                            data-ep="<?= $epId ?>" data-tab="inst"
                            onclick="showEpTab('<?= $epId ?>', 'inst', this)">
                        <i class="bi bi-calendar-x"></i> Mensualités échouées (<?= count($ep['failed_installments']) ?>)
                    </button>
                </li>
                <?php endif; ?>
                <?php if (!empty($ep['agios_charged'])): ?>
                <li>
                    <button type="button" class="btn btn-sm btn-outline ep-tab"
                            data-ep="<?= $epId ?>" data-tab="agios"
                            onclick="showEpTab('<?= $epId ?>', 'agios', this)">
                        <i class="bi bi-bank"></i> Agios prélevés (<?= count($ep['agios_charged']) ?>)
                    </button>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Onglet : timeline solde -->
            <div id="<?= $epId ?>-timeline" class="ep-panel">
                <div style="overflow-x:auto;">
                    <table class="table table-sm" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Catégorie</th>
                                <th>Commentaire</th>
                                <th style="text-align:right;">Montant</th>
                                <th style="text-align:right;">Solde après</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ep['snapshot'] as $tx): ?>
                            <tr style="<?= ($tx['category'] ?? '') === 'Agios' ? 'background:rgba(239,68,68,0.07);' : '' ?>">
                                <td style="white-space:nowrap;"><?= fmtDate($tx['created_at']) ?></td>
                                <td>
                                    <?php if ($tx['type'] === 'income'): ?>
                                        <span class="badge badge-success" style="font-size:0.7rem;">+ Entrée</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"  style="font-size:0.7rem;">− Dépense</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($tx['category'] ?? '—') ?></td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="<?= e($tx['comment'] ?? '') ?>">
                                    <?= e($tx['comment'] ?? '—') ?>
                                </td>
                                <td style="text-align:right;white-space:nowrap;font-weight:600;
                                           color:<?= $tx['type'] === 'income' ? 'var(--success-dark,#16a34a)' : 'var(--danger)' ?>">
                                    <?= $tx['type'] === 'income' ? '+' : '−' ?>
                                    <?= fmtBal((float)$tx['amount'], $currency) ?>
                                </td>
                                <td style="text-align:right;white-space:nowrap;font-weight:700;"
                                    class="<?= balClass((float)$tx['running_balance'], $overdraftLimit) ?>">
                                    <?= fmtBal((float)$tx['running_balance'], $currency) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Onglet : prélèvements rejetés -->
            <?php if (!empty($ep['rejected_dd'])): ?>
            <div id="<?= $epId ?>-dd" class="ep-panel" style="display:none;">
                <div style="overflow-x:auto;">
                    <table class="table table-sm" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Date prévue</th>
                                <th>Statut</th>
                                <th>Montant</th>
                                <th>Motif rejet</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ep['rejected_dd'] as $dd): ?>
                            <tr>
                                <td><?= fmtDate($dd['scheduled_at']) ?></td>
                                <td>
                                    <span class="badge badge-danger" style="font-size:0.7rem;">
                                        <?= htmlspecialchars(DirectDebit::STATUS_LABELS[$dd['status']] ?? $dd['status'], ENT_QUOTES) ?>
                                    </span>
                                </td>
                                <td><?= fmtBal((float)$dd['amount'], $currency) ?></td>
                                <td><?= e($dd['reject_reason'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Onglet : virements échoués -->
            <?php if (!empty($ep['failed_transfers'])): ?>
            <div id="<?= $epId ?>-tr" class="ep-panel" style="display:none;">
                <div style="overflow-x:auto;">
                    <table class="table table-sm" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Montant</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ep['failed_transfers'] as $tr): ?>
                            <tr>
                                <td><?= fmtDate($tr['created_at']) ?></td>
                                <td>
                                    <span class="badge badge-danger" style="font-size:0.7rem;">
                                        <?= htmlspecialchars(Transfer::STATUS_LABELS[$tr['status']] ?? $tr['status'], ENT_QUOTES) ?>
                                    </span>
                                </td>
                                <td><?= fmtBal((float)$tr['amount'], $currency) ?></td>
                                <td><?= e($tr['comment'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Onglet : mensualités échouées -->
            <?php if (!empty($ep['failed_installments'])): ?>
            <div id="<?= $epId ?>-inst" class="ep-panel" style="display:none;">
                <div style="overflow-x:auto;">
                    <table class="table table-sm" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Échéance</th>
                                <th>Statut</th>
                                <th>Montant</th>
                                <th>Type crédit</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ep['failed_installments'] as $inst): ?>
                            <tr>
                                <td><?= fmtDateShort($inst['due_date']) ?></td>
                                <td>
                                    <span class="badge badge-danger" style="font-size:0.7rem;">Échouée</span>
                                </td>
                                <td><?= fmtBal((float)$inst['amount'], $currency) ?></td>
                                <td><?= e($inst['loan_type'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Onglet : agios prélevés pendant l'épisode -->
            <?php if (!empty($ep['agios_charged'])): ?>
            <div id="<?= $epId ?>-agios" class="ep-panel" style="display:none;">
                <div style="overflow-x:auto;">
                    <table class="table table-sm" style="font-size:0.82rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ep['agios_charged'] as $ag): ?>
                            <tr>
                                <td><?= fmtDate($ag['created_at']) ?></td>
                                <td style="font-weight:600;color:var(--danger);">
                                    −<?= fmtBal((float)$ag['amount'], $currency) ?>
                                </td>
                                <td><?= e($ag['comment'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /onglets -->

        <!-- Bouton prélever agios si épisode toujours en cours et pas d'agios récent -->
        <?php if ($isOngoing): ?>
        <div style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid var(--border-color);">
            <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/charge-agios">
                <?= csrf_field() ?>
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0.6rem;">
                    <div style="display:flex;align-items:center;gap:0.4rem;flex:1;min-width:200px;">
                        <input type="number" name="amount" min="0.01" step="0.01" required
                               placeholder="Montant agios"
                               style="width:130px;font-size:0.84rem;padding:0.35rem 0.55rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);">
                        <span style="font-size:0.82rem;color:var(--text-muted);"><?= e($currency) ?></span>
                        <input type="text" name="comment" maxlength="255"
                               placeholder="Commentaire (optionnel)"
                               style="flex:1;font-size:0.84rem;padding:0.35rem 0.55rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);">
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Prélever des agios sur ce compte ?')">
                        <i class="bi bi-exclamation-triangle-fill"></i> Prélever agios
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; /* /empty episodes */ ?>

<!-- ── Historique global des agios prélevés ──────────────────────── -->
<?php if (!empty($agiosTx)): ?>
<div class="card mt-2">
    <div class="card-body">
        <h3 style="margin:0 0 0.85rem;font-size:0.95rem;">
            <i class="bi bi-bank" style="color:var(--danger);"></i>
            Tous les agios prélevés sur ce compte
        </h3>
        <div style="overflow-x:auto;">
            <table class="table table-sm" style="font-size:0.82rem;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align:right;">Montant</th>
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($agiosTx) as $ag): ?>
                    <tr>
                        <td><?= fmtDate($ag['created_at']) ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--danger);">
                            −<?= fmtBal((float)$ag['amount'], $currency) ?>
                        </td>
                        <td><?= e($ag['comment'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background:var(--light);font-weight:700;">
                    <td>Total</td>
                    <td style="text-align:right;color:var(--danger);">
                        −<?= fmtBal(array_sum(array_column($agiosTx, 'amount')), $currency) ?>
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function showEpTab(epId, tab, btn) {
    // Cacher tous les panneaux de cet épisode
    document.querySelectorAll('[id^="' + epId + '-"]').forEach(function (el) {
        el.style.display = 'none';
    });
    // Retirer active de tous les boutons du groupe
    document.querySelectorAll('.ep-tab[data-ep="' + epId + '"]').forEach(function (b) {
        b.classList.remove('active');
        b.style.background = '';
        b.style.color      = '';
    });
    // Afficher le panneau cible
    var panel = document.getElementById(epId + '-' + tab);
    if (panel) panel.style.display = '';
    // Activer le bouton
    if (btn) {
        btn.classList.add('active');
    }
}
</script>
