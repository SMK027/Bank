<?php
use App\Models\AuditLog;

/**
 * @var array  $entries   Entrées de journal paginées
 * @var array  $filters   Filtres actifs
 * @var int    $page      Page courante
 * @var int    $pages     Nombre total de pages
 * @var int    $total     Nombre total d'entrées
 * @var int    $perPage   Entrées par page
 * @var array  $actions   Tableau action => libellé pour le select
 */

// Reconstruit la query string en préservant tous les filtres sauf 'page'
function auditQueryString(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    $clean = array_filter($params, fn($v) => $v !== '');
    return $clean ? '?' . http_build_query($clean) : '?';
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-text"></i> Modération — Journal d'audit</h1>
        <p class="page-description">Trace de toutes les actions effectuées sur la plateforme.</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/recurring-transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Virements récurrents</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-journal-text"></i> Journal d'audit</span>
    </div>
</div>

<!-- ── Filtres ───────────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <form method="GET" action="/moderation/audit-log">
            <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;flex:2;min-width:180px;">
                    <label class="form-label" style="font-size:0.78rem;margin-bottom:0.3rem;">
                        <i class="bi bi-tag"></i> Type d'action
                    </label>
                    <select name="action" class="form-control" style="padding:0.35rem 0.6rem;font-size:0.84rem;">
                        <option value="">— Tous —</option>
                        <?php
                        $grouped = [];
                        foreach ($actions as $key => $label) {
                            $cat = explode('.', $key)[0];
                            $grouped[$cat][$key] = $label;
                        }
                        foreach ($grouped as $cat => $items):
                            $catLabel = match($cat) {
                                'auth'               => 'Authentification',
                                'account'            => 'Comptes',
                                'transaction'        => 'Transactions',
                                'transfer'           => 'Virements',
                                'transfer_recurring' => 'Virements récurrents',
                                'direct_debit'       => 'Prélèvements',
                                'mandate'            => 'Mandats',
                                'user'               => 'Utilisateurs',
                                'access'             => 'Accès partagés',
                                'guardianship'       => 'Tutelles',
                                'minor_account'      => 'Comptes mineurs',
                                default              => ucfirst($cat),
                            };
                        ?>
                        <optgroup label="<?= e($catLabel) ?>">
                            <?php foreach ($items as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($filters['action'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                    <label class="form-label" style="font-size:0.78rem;margin-bottom:0.3rem;">
                        <i class="bi bi-person"></i> Acteur
                    </label>
                    <input type="text" name="username" class="form-control"
                           style="padding:0.35rem 0.6rem;font-size:0.84rem;"
                           placeholder="ex : alice"
                           value="<?= e($filters['username'] ?? '') ?>">
                </div>

                <div class="form-group" style="margin:0;flex:1;min-width:120px;">
                    <label class="form-label" style="font-size:0.78rem;margin-bottom:0.3rem;">
                        <i class="bi bi-bank"></i> Compte cible (ID)
                    </label>
                    <input type="number" name="target_account_id" class="form-control"
                           style="padding:0.35rem 0.6rem;font-size:0.84rem;"
                           placeholder="ex : 12"
                           value="<?= e($filters['target_account_id'] ?? '') ?>">
                </div>

                <div class="form-group" style="margin:0;flex:1;min-width:130px;">
                    <label class="form-label" style="font-size:0.78rem;margin-bottom:0.3rem;">
                        <i class="bi bi-calendar-event"></i> Du
                    </label>
                    <input type="date" name="date_from" class="form-control"
                           style="padding:0.35rem 0.6rem;font-size:0.84rem;"
                           value="<?= e($filters['date_from'] ?? '') ?>">
                </div>

                <div class="form-group" style="margin:0;flex:1;min-width:130px;">
                    <label class="form-label" style="font-size:0.78rem;margin-bottom:0.3rem;">
                        <i class="bi bi-calendar-check"></i> Au
                    </label>
                    <input type="date" name="date_to" class="form-control"
                           style="padding:0.35rem 0.6rem;font-size:0.84rem;"
                           value="<?= e($filters['date_to'] ?? '') ?>">
                </div>

                <div style="padding-bottom:0.05rem;display:flex;gap:0.5rem;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                    <?php if (!empty($filters)): ?>
                    <a href="/moderation/audit-log" class="btn btn-outline btn-sm" title="Réinitialiser">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Résultats ─────────────────────────────────────────────────────────── -->
<div class="d-flex justify-between align-center mb-1">
    <span class="text-muted text-small">
        <?= number_format($total, 0, ',', ' ') ?> entrée<?= $total > 1 ? 's' : '' ?>
        <?php if (!empty($filters)): ?>
            <span class="text-warning">(filtrées)</span>
        <?php endif; ?>
    </span>
    <?php if ($pages > 1): ?>
    <span class="text-muted text-small">Page <?= $page ?> / <?= $pages ?></span>
    <?php endif; ?>
</div>

<?php if (empty($entries)): ?>
<div class="alert alert-info">Aucune entrée ne correspond aux critères.</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="table" style="font-size:0.85rem;margin:0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width:155px">Date</th>
                        <th style="width:200px">Action</th>
                        <th style="width:130px">Acteur</th>
                        <th>Détails</th>
                        <th style="width:110px">Cible</th>
                        <th class="text-nowrap" style="width:130px">IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <?php
                    $badgeClass = AuditLog::badgeClass($e['action']);
                    $details    = $e['details_decoded'] ?? [];
                    ?>
                    <tr>
                        <td class="text-nowrap text-muted">
                            <?= e((new DateTime($e['created_at']))->format('d/m/Y H:i:s')) ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?>" style="font-size:.78rem;">
                                <?= e(AuditLog::label($e['action'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($e['actor_name']): ?>
                                <a href="/moderation/users" style="text-decoration:none;">
                                    <?= e($e['actor_name']) ?>
                                </a>
                                <br><span class="text-muted text-small">#<?= (int) $e['user_id'] ?></span>
                            <?php else: ?>
                                <span class="text-muted font-italic">Système</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($details)):
                            $parts = [];
                            $labelMap = [
                                'name'           => 'Compte',
                                'type'           => 'Type',
                                'amount'         => 'Montant',
                                'from_account'   => 'De',
                                'to_account'     => 'Vers',
                                'username'       => 'Utilisateur',
                                'email'          => 'Email',
                                'role'           => 'Rôle',
                                'new_role'       => 'Nouveau rôle',
                                'mandate'        => 'Mandat',
                                'mandate_number' => 'Mandat',
                                'transfer_id'    => 'Virement #',
                                'account_id'     => 'Compte #',
                                'reason'         => 'Motif',
                                'interval_days'  => 'Intervalle (j)',
                                'until'          => "Jusqu'au",
                                'target_username'=> 'Cible',
                                'shared_with'    => 'Partagé avec',
                                'guardian'       => 'Tuteur',
                                'minor'          => 'Mineur',
                            ];
                            foreach ($labelMap as $key => $lbl) {
                                if (!empty($details[$key])) {
                                    $val = $details[$key];
                                    if ($key === 'amount') {
                                        $val = number_format((float) $val, 2, ',', ' ') . ' €';
                                    }
                                    $parts[] = '<strong>' . e($lbl) . '</strong>&nbsp;' . e((string) $val);
                                }
                            }
                            ?>
                            <span class="text-muted text-small">
                                <?= implode(' &nbsp;·&nbsp; ', $parts) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-small">
                            <?php if ($e['target_name']): ?>
                                <span class="text-nowrap"><i class="bi bi-person"></i> <?= e($e['target_name']) ?></span><br>
                            <?php endif; ?>
                            <?php if ($e['target_account_id']): ?>
                                <a href="/accounts/<?= (int) $e['target_account_id'] ?>" class="text-nowrap" style="text-decoration:none;">
                                    <i class="bi bi-bank"></i> Compte #<?= (int) $e['target_account_id'] ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!$e['target_name'] && !$e['target_account_id']): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted text-nowrap text-small">
                            <?= $e['ip_address'] ? e($e['ip_address']) : '<span class="font-italic">CLI</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Pagination ─────────────────────────────────────────────────────────── -->
<?php if ($pages > 1): ?>
<nav>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= auditQueryString() ?>&page=<?= $page - 1 ?>">‹</a>
        <?php else: ?>
            <span>‹</span>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end   = min($pages, $page + 2);
        if ($start > 1): ?>
            <a href="<?= auditQueryString() ?>&page=1">1</a>
            <?php if ($start > 2): ?><span>…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= auditQueryString() ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?><span>…</span><?php endif; ?>
            <a href="<?= auditQueryString() ?>&page=<?= $pages ?>"><?= $pages ?></a>
        <?php endif; ?>

        <?php if ($page < $pages): ?>
            <a href="<?= auditQueryString() ?>&page=<?= $page + 1 ?>">›</a>
        <?php else: ?>
            <span>›</span>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>
<?php endif; ?>
