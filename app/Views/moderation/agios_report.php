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
    <div class="agios-report-header__actions">
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
?>
<div class="card mt-2 agios-ep-card<?= $isOngoing ? ' agios-ep-card--active' : '' ?>">
    <div class="card-body">
        <!-- En-tête de l'épisode -->
        <div class="agios-ep-header">
            <div>
                <h3 class="agios-ep-title">
                    <?php if ($isOngoing): ?>
                        <span class="badge badge-danger">En cours</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Clôturé</span>
                    <?php endif; ?>
                    Épisode #<?= count($episodes) - $i ?>
                </h3>
                <div class="agios-ep-meta">
                    <i class="bi bi-calendar-event"></i>
                    Début&nbsp;: <strong><?= fmtDate($ep['started_at']) ?></strong>
                    <?php if (!$isOngoing): ?>
                        &nbsp;→&nbsp;Fin&nbsp;: <strong><?= fmtDate($ep['ended_at']) ?></strong>
                        &nbsp;·&nbsp;<strong><?= $ep['duration_days'] ?></strong> jour(s)
                    <?php else: ?>
                        &nbsp;·&nbsp;<span class="agios-ep-ongoing-label">Toujours en dépassement</span>
                        &nbsp;·&nbsp;<strong><?= $ep['duration_days'] ?></strong> jour(s) depuis le début
                    <?php endif; ?>
                </div>
            </div>
            <div class="agios-ep-stats">
                <div class="agios-ep-stats__label">Dépassement max</div>
                <div class="agios-ep-stats__value">
                    <?= fmtBal($ep['max_depth'], $currency) ?>
                </div>
                <?php if (!$isOngoing): ?>
                <div class="agios-ep-stats__hint">
                    Tx de déclenchement&nbsp;:
                    <strong><?= e($ep['start_tx']['category'] ?? '—') ?></strong>
                    (<?= fmtBal(-(float)$ep['start_tx']['amount'], $currency) ?>)
                </div>
                <div class="agios-ep-stats__hint">
                    Tx de clôture&nbsp;:
                    <strong><?= e($ep['end_tx']['category'] ?? '—') ?></strong>
                    (+<?= fmtBal((float)$ep['end_tx']['amount'], $currency) ?>)
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rejets associés -->
        <?php if ($nbRejects > 0 || !empty($ep['agios_charged'])): ?>
        <div class="agios-ep-badges">
            <?php if ($nbRejects > 0): ?>
                <span class="badge badge-danger" style="font-size:0.78rem;">
                    <i class="bi bi-x-circle"></i>
                    <?= $nbRejects ?> rejet(s) / échec(s)
                </span>
            <?php endif; ?>
            <?php if (!empty($ep['agios_charged'])): ?>
                <?php $sumAgios = array_sum(array_column($ep['agios_charged'], 'amount')); ?>
                <span class="badge badge-danger">
                    <i class="bi bi-bank"></i>
                    <?= count($ep['agios_charged']) ?> agios&nbsp;·&nbsp;<?= fmtBal($sumAgios, $currency) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Onglets internes à l'épisode -->
        <?php $epId = 'ep-' . $i; ?>
        <div>
            <ul class="agios-ep-tabs">
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
                    <table class="table table-sm agios-ep-table">
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
                            <tr class="<?= ($tx['category'] ?? '') === 'Agios' ? 'agios-ep-tx-row--agios' : '' ?>">
                                <td class="agios-ep-tx-date"><?= fmtDate($tx['created_at']) ?></td>
                                <td>
                                    <?php if ($tx['type'] === 'income'): ?>
                                        <span class="badge badge-success">+ Entrée</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">− Dépense</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($tx['category'] ?? '—') ?></td>
                                <td class="agios-ep-tx-comment"
                                    title="<?= e($tx['comment'] ?? '') ?>">
                                    <?= e($tx['comment'] ?? '—') ?>
                                </td>
                                <td class="agios-ep-tx-amount agios-ep-tx-amount--<?= $tx['type'] === 'income' ? 'income' : 'expense' ?>">
                                    <?= $tx['type'] === 'income' ? '+' : '−' ?>
                                    <?= fmtBal((float)$tx['amount'], $currency) ?>
                                </td>
                                <td class="agios-ep-tx-balance <?= balClass((float)$tx['running_balance'], $overdraftLimit) ?>">
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
                    <table class="table table-sm agios-ep-table">
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
                                <td class="agios-ep-tx-date"><?= fmtDate($dd['scheduled_at']) ?></td>
                                <td>
                                    <span class="badge badge-danger">
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
                    <table class="table table-sm agios-ep-table">
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
                                <td class="agios-ep-tx-date"><?= fmtDate($tr['created_at']) ?></td>
                                <td>
                                    <span class="badge badge-danger">
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
                    <table class="table table-sm agios-ep-table">
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
                                    <span class="badge badge-danger">Échouée</span>
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
                    <table class="table table-sm agios-ep-table">
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
                                <td class="agios-ep-tx-date"><?= fmtDate($ag['created_at']) ?></td>
                                <td class="agios-ep-tx-amount agios-ep-tx-amount--expense">
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

        <!-- Formulaire prélever agios (disponible sur tout épisode, actif ou compensé) -->
        <?php /* toujours visible pour permettre le prélèvement rétroactif */ ?>
        <div class="agios-ep-form">
            <?php if (!$isOngoing): ?>
            <div class="alert alert-warning" style="font-size:0.82rem;margin-bottom:0.7rem;padding:0.5rem 0.75rem;">
                <i class="bi bi-clock-history"></i>
                Cet épisode est <strong>clôturé</strong> (découvert compensé le <?= fmtDate($ep['ended_at']) ?>).
                Vous pouvez tout de même prélever des agios rétroactivement pour cette période.
            </div>
            <?php endif; ?>

            <!-- Toggle Manuel / Par TAEG -->
            <div class="agios-ep-toggle">
                <button type="button" id="ep<?= $i ?>BtnManuel" onclick="agiosEpMode(<?= $i ?>,'manuel')"
                        class="agios-ep-toggle__btn active">
                    <i class="bi bi-pencil"></i> Manuel
                </button>
                <button type="button" id="ep<?= $i ?>BtnTaeg" onclick="agiosEpMode(<?= $i ?>,'taeg')"
                        class="agios-ep-toggle__btn">
                    <i class="bi bi-calculator"></i> Par TAEG
                </button>
            </div>

            <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/charge-agios">
                <?= csrf_field() ?>
                <input type="hidden" name="taeg_rate"    id="ep<?= $i ?>HiddenRate"    value="">
                <input type="hidden" name="taeg_capital" id="ep<?= $i ?>HiddenCapital" value="">
                <input type="hidden" name="taeg_days"    id="ep<?= $i ?>HiddenDays"    value="">

                <!-- Panel Manuel -->
                <div id="ep<?= $i ?>PanelManuel">
                    <div class="agios-ep-row">
                        <input type="number" name="amount" id="ep<?= $i ?>Amount"
                               min="0.01" step="0.01" required placeholder="Montant agios"
                               style="width:130px;">
                        <span style="font-size:0.82rem;color:var(--text-muted);"><?= e($currency) ?></span>
                        <input type="text" name="comment" id="ep<?= $i ?>Comment" maxlength="255"
                               value="Agios — période du <?= fmtDateShort($ep['started_at']) ?><?= $ep['ended_at'] ? ' au ' . fmtDateShort($ep['ended_at']) : ' (en cours)' ?>">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return agiosEpSubmit(<?= $i ?>)">
                            <i class="bi bi-exclamation-triangle-fill"></i> Prélever agios
                        </button>
                    </div>
                </div>

                <!-- Panel TAEG -->
                <div id="ep<?= $i ?>PanelTaeg" style="display:none;">
                    <div class="agios-ep-taeg-row">
                        <div class="agios-ep-taeg-field">
                            <label>TAEG&nbsp;(%)</label>
                            <input type="number" id="ep<?= $i ?>TaegRate" min="0.01" step="0.01"
                                   placeholder="ex.&nbsp;15.00" oninput="agiosEpCalc(<?= $i ?>)"
                                   style="width:90px;">
                        </div>
                        <div class="agios-ep-taeg-field">
                            <label>Capital&nbsp;(<?= e($currency) ?>)</label>
                            <input type="number" id="ep<?= $i ?>TaegCapital" min="0.01" step="0.01"
                                   value="<?= round((float)$ep['max_depth'], 2) ?>"
                                   oninput="agiosEpCalc(<?= $i ?>)"
                                   style="width:110px;">
                        </div>
                        <div class="agios-ep-taeg-field">
                            <label>Jours</label>
                            <input type="number" id="ep<?= $i ?>TaegDays" min="1" step="1"
                                   value="<?= (int)$ep['duration_days'] ?>"
                                   oninput="agiosEpCalc(<?= $i ?>)"
                                   style="width:70px;">
                        </div>
                        <div class="agios-ep-taeg-result">
                            = <strong id="ep<?= $i ?>TaegResult">—</strong>
                        </div>
                    </div>
                    <div class="agios-formula-hint">
                        Capital &times; TAEG&nbsp;% &divide; 100 &times; Jours &divide; 365
                    </div>
                    <div class="agios-ep-row" style="margin-top:0.4rem;">
                        <input type="text" name="comment" maxlength="255"
                               value="Agios — période du <?= fmtDateShort($ep['started_at']) ?><?= $ep['ended_at'] ? ' au ' . fmtDateShort($ep['ended_at']) : ' (en cours)' ?>">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return agiosEpSubmit(<?= $i ?>)">
                            <i class="bi bi-exclamation-triangle-fill"></i> Prélever agios
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; /* /empty episodes */ ?>

<!-- ── Historique global des agios prélevés ──────────────────────── -->
<?php if (!empty($agiosTx)): ?>
<div class="card mt-2">
    <div class="card-body">
        <h3 class="agios-report-global__title">
            <i class="bi bi-bank" style="color:var(--danger);"></i>
            Tous les agios prélevés sur ce compte
        </h3>
        <div style="overflow-x:auto;">
            <table class="table table-sm agios-ep-table">
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
                        <td class="agios-ep-tx-date"><?= fmtDate($ag['created_at']) ?></td>
                        <td class="agios-ep-tx-amount agios-ep-tx-amount--expense">
                            −<?= fmtBal((float)$ag['amount'], $currency) ?>
                        </td>
                        <td><?= e($ag['comment'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="agios-report-global__total">
                    <td>Total</td>
                    <td class="agios-report-global__amount">
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
function agiosEpMode(i, mode) {
    var isManuel = mode === 'manuel';
    var panelM = document.getElementById('ep' + i + 'PanelManuel');
    var panelT = document.getElementById('ep' + i + 'PanelTaeg');
    var btnM   = document.getElementById('ep' + i + 'BtnManuel');
    var btnT   = document.getElementById('ep' + i + 'BtnTaeg');
    if (panelM) panelM.style.display = isManuel ? '' : 'none';
    if (panelT) panelT.style.display = isManuel ? 'none' : '';
    if (btnM) btnM.classList.toggle('active', isManuel);
    if (btnT) btnT.classList.toggle('active', !isManuel);
    var amt = document.getElementById('ep' + i + 'Amount');
    if (amt) amt.required = isManuel;
    if (isManuel) {
        document.getElementById('ep' + i + 'HiddenRate').value    = '';
        document.getElementById('ep' + i + 'HiddenCapital').value = '';
        document.getElementById('ep' + i + 'HiddenDays').value    = '';
    } else {
        agiosEpCalc(i);
    }
}
function agiosEpCalc(i) {
    var rate    = parseFloat(document.getElementById('ep' + i + 'TaegRate').value);
    var capital = parseFloat(document.getElementById('ep' + i + 'TaegCapital').value);
    var days    = parseFloat(document.getElementById('ep' + i + 'TaegDays').value);
    var resultEl = document.getElementById('ep' + i + 'TaegResult');
    var amt      = document.getElementById('ep' + i + 'Amount');
    if (rate > 0 && capital > 0 && days > 0) {
        var amount = Math.round(capital * (rate / 100) * (days / 365) * 100) / 100;
        if (resultEl) resultEl.textContent = amount.toFixed(2).replace('.', ',');
        if (amt) amt.value = amount;
        document.getElementById('ep' + i + 'HiddenRate').value    = rate;
        document.getElementById('ep' + i + 'HiddenCapital').value = capital;
        document.getElementById('ep' + i + 'HiddenDays').value    = days;
    } else {
        if (resultEl) resultEl.textContent = '—';
        if (amt) amt.value = '';
        document.getElementById('ep' + i + 'HiddenRate').value    = '';
        document.getElementById('ep' + i + 'HiddenCapital').value = '';
        document.getElementById('ep' + i + 'HiddenDays').value    = '';
    }
}
function agiosEpSubmit(i) {
    var amt = document.getElementById('ep' + i + 'Amount');
    var amount = amt ? parseFloat(amt.value) : 0;
    if (!amount || amount <= 0) {
        alert('Veuillez saisir un montant ou renseigner les trois champs TAEG.');
        return false;
    }
    return confirm('Prélever des agios sur ce compte ?');
}
</script>
