<div class="page-header">
    <div>
        <h1><i class="bi bi-bank2"></i> Tableau de bord</h1>
        <p class="page-description">Bienvenue, <?= e(current_username()) ?> ! Voici un aperçu de vos comptes.</p>
    </div>
    <a href="/accounts/create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau compte</a>
</div>

<!-- Total global -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value <?= $totalBalance >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= number_format($totalBalance, 2, ',', ' ') ?> €
        </div>
        <div class="stat-label">Solde total (comptes personnels)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($ownAccounts) ?></div>
        <div class="stat-label">Mes comptes</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($sharedAccounts) ?></div>
        <div class="stat-label">Comptes partagés</div>
    </div>
</div>

<!-- Mes comptes -->
<h2 class="mb-2"><i class="bi bi-wallet2"></i> Mes comptes</h2>

<?php if (empty($ownAccounts)): ?>
    <div class="empty-state">
        <div class="empty-icon">🏦</div>
        <p>Vous n'avez pas encore de compte bancaire.</p>
        <a href="/accounts/create" class="btn btn-primary">Créer mon premier compte</a>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($ownAccounts as $account): ?>
            <a href="/accounts/<?= (int) $account['id'] ?>" class="card-link">
                <div class="card account-card <?= $account['balance'] >= 0 ? 'account-positive' : 'account-negative' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-between align-center mb-1">
                            <h3 style="margin:0"><?= e($account['name']) ?></h3>
                            <span class="badge <?= $account['balance'] >= 0 ? 'badge-success' : 'badge-danger' ?>">
                                <?= e($account['currency']) ?>
                            </span>
                        </div>
                        <div class="account-balance <?= $account['balance'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                            <?= number_format($account['balance'], 2, ',', ' ') ?> <?= e($account['currency']) ?>
                        </div>
                        <?php if ((float) $account['overdraft'] > 0): ?>
                            <div class="text-small text-muted mt-1">
                                <i class="bi bi-shield-check"></i> Découvert autorisé : <?= number_format((float) $account['overdraft'], 2, ',', ' ') ?> <?= e($account['currency']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Comptes partagés -->
<?php if (!empty($sharedAccounts)): ?>
    <h2 class="mt-3 mb-2"><i class="bi bi-people"></i> Comptes partagés avec moi</h2>
    <div class="card-grid">
        <?php foreach ($sharedAccounts as $account): ?>
            <a href="/accounts/<?= (int) $account['id'] ?>" class="card-link">
                <div class="card account-card account-shared">
                    <div class="card-body">
                        <div class="d-flex justify-between align-center mb-1">
                            <h3 style="margin:0"><?= e($account['name']) ?></h3>
                            <span class="badge badge-info">
                                <?= $account['_access_type'] === 'permanent' ? 'Permanent' : 'Temporaire' ?>
                            </span>
                        </div>
                        <div class="account-balance <?= $account['balance'] >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                            <?= number_format($account['balance'], 2, ',', ' ') ?> <?= e($account['currency']) ?>
                        </div>
                        <?php if (!empty($account['_access_expires'])): ?>
                            <div class="text-small text-muted mt-1">
                                <i class="bi bi-clock"></i> Expire le <?= date('d/m/Y', strtotime($account['_access_expires'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
