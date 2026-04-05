<?php
/**
 * @var array  $tickets       Liste de tous les tickets
 * @var array  $statuses      Ticket::STATUSES
 * @var string $statusFilter  Filtre actif
 * @var int    $openCount     Nb tickets actifs
 */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-ticket-perforated"></i> Tickets
            <?php if ($openCount > 0): ?>
            <span class="badge badge-danger" style="font-size:0.7rem;vertical-align:middle;margin-left:0.3rem;">
                <?= $openCount ?>
            </span>
            <?php endif; ?>
        </h1>
        <p class="page-description">Demandes soumises par les utilisateurs</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-bank"></i> Comptes</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
    </div>
</div>

<!-- Filtres statut -->
<div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    <a href="/moderation/tickets"
       class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline' ?>">
        Tous
    </a>
    <?php foreach ($statuses as $val => $label): ?>
    <a href="/moderation/tickets?status=<?= urlencode($val) ?>"
       class="btn btn-sm <?= $statusFilter === $val ? 'btn-primary' : 'btn-outline' ?>"
       style="font-size:0.8rem;">
        <span class="badge badge-<?= e(\App\Models\Ticket::statusColor($val)) ?>"
              style="font-size:0.7rem;margin-right:0.3rem;">&nbsp;</span>
        <?= e($label) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($tickets)): ?>
<div class="card" style="text-align:center;padding:2.5rem 1rem;">
    <div style="font-size:3rem;color:var(--gray-light);margin-bottom:1rem;"><i class="bi bi-inbox"></i></div>
    <p style="color:var(--text-secondary);margin:0;">Aucun ticket<?= $statusFilter !== '' ? ' avec ce statut' : '' ?>.</p>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>Sujet</th>
                    <th>Utilisateur</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Màj</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td style="color:var(--text-secondary);font-size:0.8rem;"><?= (int) $t['id'] ?></td>
                    <td style="max-width:260px;">
                        <a href="/moderation/tickets/<?= (int) $t['id'] ?>"
                           style="font-weight:<?= in_array($t['status'], ['open','in_progress'], true) ? '600' : '400' ?>;
                                  color:var(--text-primary);text-decoration:none;"
                           title="<?= e($t['subject']) ?>">
                            <?= e(mb_strimwidth($t['subject'], 0, 55, '…')) ?>
                        </a>
                    </td>
                    <td style="font-size:0.85rem;"><?= e($t['username']) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-secondary);">
                        <?= e(\App\Models\Ticket::typeLabel($t['type'])) ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= e(\App\Models\Ticket::statusColor($t['status'])) ?>">
                            <?= e(\App\Models\Ticket::statusLabel($t['status'])) ?>
                        </span>
                    </td>
                    <td style="font-size:0.78rem;color:var(--text-secondary);white-space:nowrap;">
                        <?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?>
                    </td>
                    <td>
                        <a href="/moderation/tickets/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
