<div class="page-header">
    <div>
        <h1><i class="bi bi-percent"></i> Mes intérêts d'épargne</h1>
        <p class="page-description">Consultez et confirmez vos intérêts annuels sur vos comptes épargne.</p>
    </div>
</div>

<?php if (empty($pending)): ?>
    <div class="empty-state">
        <div class="empty-icon">💰</div>
        <p>Aucun intérêt en attente de confirmation pour le moment.</p>
        <p class="text-muted" style="font-size:0.9rem;">Les intérêts sont calculés automatiquement le 1er janvier de chaque année.</p>
        <a href="/dashboard" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-clock-history"></i> En attente de confirmation</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="table">
            <thead>
                <tr>
                    <th>Compte</th>
                    <th>Année</th>
                    <th>Taux annuel</th>
                    <th>Intérêts calculés</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $i): ?>
                <tr>
                    <td><strong><?= e($i['account_name']) ?></strong></td>
                    <td><?= (int) $i['year'] ?></td>
                    <td><?= number_format((float) $i['rate'] * 100, 2, ',', ' ') ?> %</td>
                    <td>
                        <strong class="text-success">
                            <?= fmt_amount_smart((float) $i['calculated_amount']) ?> <?= e($i['currency']) ?>
                        </strong>
                    </td>
                    <td>
                        <a href="/interests/<?= (int) $i['id'] ?>/confirm" class="btn btn-sm btn-primary">
                            <i class="bi bi-check-circle"></i> Confirmer
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
