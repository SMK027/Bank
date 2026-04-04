<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-check"></i> Modération — Comptes</h1>
        <p class="page-description">Vue globale de tous les comptes bancaires</p>
    </div>
    <a href="/moderation/users" class="btn btn-outline btn-sm">
        <i class="bi bi-people"></i> Gérer les utilisateurs
    </a>
</div>

<?php if (empty($allAccounts)): ?>
    <div class="empty-state"><div class="empty-icon">🏦</div><p>Aucun compte enregistré.</p></div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="table" style="margin:0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Propriétaire</th>
                        <th>Type</th>
                        <th>Devise</th>
                        <th class="text-right">Solde</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAccounts as $acc): ?>
                        <?php $frozen = !empty($acc['frozen']); ?>
                        <tr class="<?= $frozen ? 'row-frozen' : '' ?>">
                            <td class="text-muted text-small">#<?= (int) $acc['id'] ?></td>
                            <td>
                                <a href="/accounts/<?= (int) $acc['id'] ?>" class="font-bold">
                                    <?= e($acc['name']) ?>
                                </a>
                            </td>
                            <td><?= e($acc['owner_name']) ?></td>
                            <td>
                                <?php
                                    $typeLabel = \App\Models\Account::TYPES[$acc['type'] ?? '']['label'] ?? '—';
                                ?>
                                <span class="text-small"><?= e($typeLabel) ?></span>
                            </td>
                            <td><?= e($acc['currency']) ?></td>
                            <td class="text-right font-bold <?= $acc['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($acc['balance'], 2, ',', ' ') ?>
                            </td>
                            <td>
                                <?php if ($frozen): ?>
                                    <span class="badge badge-frozen"><i class="bi bi-snow"></i> Gelé</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                                    <a href="/accounts/<?= (int) $acc['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($frozen): ?>
                                        <form method="POST" action="/moderation/accounts/<?= (int) $acc['id'] ?>/unfreeze" style="display:inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success btn-sm"
                                                    title="Dégeler"
                                                    onclick="return confirm('Dégeler le compte « <?= e(addslashes($acc['name'])) ?> » ?')">
                                                <i class="bi bi-sun"></i> Dégeler
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/moderation/accounts/<?= (int) $acc['id'] ?>/freeze" style="display:inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-freeze"
                                                    title="Geler"
                                                    onclick="return confirm('Geler le compte « <?= e(addslashes($acc['name'])) ?> » ? Les opérations sortantes seront bloquées.')">
                                                <i class="bi bi-snow"></i> Geler
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
