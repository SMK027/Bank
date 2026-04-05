<?php
/** @var array  $tickets  Liste des tickets de l'utilisateur */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-ticket-perforated"></i> Mes demandes</h1>
        <p class="page-description">Suivez l'avancement de vos demandes auprès de l'équipe de modération.</p>
    </div>
    <a href="/tickets/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nouvelle demande
    </a>
</div>

<?php if (empty($tickets)): ?>
<div class="card" style="text-align:center;padding:2.5rem 1rem;">
    <div style="font-size:3rem;color:var(--gray-light);margin-bottom:1rem;"><i class="bi bi-inbox"></i></div>
    <p style="color:var(--text-secondary);margin:0 0 1.25rem;">Vous n'avez aucune demande pour le moment.</p>
    <a href="/tickets/create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Créer une demande</a>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>Sujet</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Dernière mise à jour</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td style="color:var(--text-secondary);font-size:0.8rem;"><?= (int) $t['id'] ?></td>
                    <td>
                        <a href="/tickets/<?= (int) $t['id'] ?>" style="font-weight:500;color:var(--text-primary);text-decoration:none;">
                            <?= e($t['subject']) ?>
                        </a>
                    </td>
                    <td style="font-size:0.82rem;color:var(--text-secondary);">
                        <?= e(\App\Models\Ticket::typeLabel($t['type'])) ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= e(\App\Models\Ticket::statusColor($t['status'])) ?>">
                            <?= e(\App\Models\Ticket::statusLabel($t['status'])) ?>
                        </span>
                    </td>
                    <td style="font-size:0.82rem;color:var(--text-secondary);">
                        <?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?>
                    </td>
                    <td>
                        <a href="/tickets/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline">
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
