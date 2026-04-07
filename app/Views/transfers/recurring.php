<?php
/** @var array  $items   Virements récurrents de l'utilisateur (enrichis avec noms de comptes) */

$items = $items ?? [];
?>

<div class="container" style="max-width:750px; margin:0 auto; padding:1.5rem 1rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
        <h2 style="margin:0;"><i class="bi bi-arrow-repeat"></i> Virements récurrents</h2>
        <a href="/transfers/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nouveau virement
        </a>
    </div>

    <?php if (empty($items)): ?>
        <div class="card">
            <div class="card-body" style="text-align:center; padding:2.5rem 1rem; color:var(--text-muted);">
                <i class="bi bi-arrow-repeat" style="font-size:2rem; opacity:0.3; display:block; margin-bottom:0.75rem;"></i>
                Aucun virement récurrent configuré.
                <div style="margin-top:1rem;">
                    <a href="/transfers/create" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Créer un virement récurrent
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Compte émetteur</th>
                        <th>Compte destinataire</th>
                        <th>Montant</th>
                        <th>Intervalle</th>
                        <th>Prochain virement</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $r): ?>
                    <tr>
                        <td style="color:var(--text-muted);font-size:0.8rem;">#<?= (int) $r['id'] ?></td>
                        <td>
                            <strong><?= e($r['from_account_name']) ?></strong>
                        </td>
                        <td>
                            <strong><?= e($r['to_account_name']) ?></strong>
                        </td>
                        <td class="text-right font-bold text-danger">
                            <?= number_format((float) $r['amount'], 2, ',', ' ') ?> €
                        </td>
                        <td style="white-space:nowrap;">
                            <i class="bi bi-arrow-clockwise" style="font-size:0.8rem;opacity:0.6;"></i>
                            tous les <?= (int) $r['interval_days'] ?> j
                        </td>
                        <td style="font-size:0.88rem; white-space:nowrap;">
                            <?php if (($r['status'] ?? '') === 'active'): ?>
                                <i class="bi bi-calendar-event" style="opacity:0.6;"></i>
                                <?= e(format_date($r['next_execution_at'])) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.88rem; color:var(--text-muted);">
                            <?= e($r['motif'] ?: '—') ?>
                        </td>
                        <td>
                            <?php if (($r['status'] ?? '') === 'active'): ?>
                                <span class="badge badge-success">Actif</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Annulé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($r['status'] ?? '') === 'active'): ?>
                            <form method="POST" action="/transfers/recurring/<?= (int) $r['id'] ?>/cancel"
                                  style="display:inline"
                                  onsubmit="return confirm('Annuler ce virement récurrent #<?= (int) $r['id'] ?> ?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        style="padding:0.2rem 0.55rem; font-size:0.78rem;"
                                        title="Annuler ce virement récurrent">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
