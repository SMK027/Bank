<div class="page-header">
    <div>
        <h1><i class="bi bi-wallet2"></i> <?= e($account['name']) ?></h1>
        <p class="page-description">
            <?php if ($isOwner): ?>
                Mon compte — Devise : <?= e($account['currency']) ?>
            <?php else: ?>
                Compte partagé par <?= e($owner['username'] ?? 'Inconnu') ?> — Devise : <?= e($account['currency']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-group">
        <a href="/dashboard" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
        <?php if ($isOwner): ?>
            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Modifier</a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques du compte -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= number_format($balance, 2, ',', ' ') ?>
        </div>
        <div class="stat-label">Solde actuel (<?= e($account['currency']) ?>)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-success"><?= number_format($totalIncome, 2, ',', ' ') ?></div>
        <div class="stat-label">Total entrées</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-danger"><?= number_format($totalExpense, 2, ',', ' ') ?></div>
        <div class="stat-label">Total dépenses</div>
    </div>
    <?php if ((float) $account['overdraft'] > 0): ?>
        <div class="stat-card">
            <div class="stat-value"><?= number_format((float) $account['overdraft'], 2, ',', ' ') ?></div>
            <div class="stat-label">Découvert autorisé (<?= e($account['currency']) ?>)</div>
        </div>
    <?php endif; ?>
</div>

<div class="account-detail-grid">
    <!-- Formulaire d'ajout de transaction -->
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="bi bi-plus-circle"></i> Nouvelle opération</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="type" class="form-label">Type</label>
                        <select id="type" name="type" class="form-control" required>
                            <option value="income">💰 Entrée</option>
                            <option value="expense">💸 Dépense</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount" class="form-label">Montant</label>
                        <input type="number" id="amount" name="amount" class="form-control"
                               placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category" class="form-label">Catégorie</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comment" class="form-label">Commentaire</label>
                        <input type="text" id="comment" name="comment" class="form-control"
                               placeholder="Ex : Courses supermarché">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <!-- Partage de compte (propriétaire uniquement) -->
    <?php if ($isOwner): ?>
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="bi bi-person-plus"></i> Partager l'accès</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/access">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email" class="form-label">Email de l'utilisateur</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="utilisateur@email.com" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="access_type" class="form-label">Type d'accès</label>
                        <select id="access_type" name="access_type" class="form-control" onchange="toggleExpires(this)">
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporaire</option>
                        </select>
                    </div>
                    <div class="form-group" id="expires_group" style="display:none">
                        <label for="expires_at" class="form-label">Expire le</label>
                        <input type="datetime-local" id="expires_at" name="expires_at" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-block">
                    <i class="bi bi-share"></i> Donner accès
                </button>
            </form>

            <?php if (!empty($accesses)): ?>
                <hr style="margin: 1.25rem 0; border-color: var(--gray-light);">
                <h4>Accès en cours</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Type</th>
                                <th>Expiration</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accesses as $access): ?>
                                <tr>
                                    <td><?= e($access['username']) ?></td>
                                    <td>
                                        <span class="badge <?= $access['type'] === 'permanent' ? 'badge-primary' : 'badge-warning' ?>">
                                            <?= $access['type'] === 'permanent' ? 'Permanent' : 'Temporaire' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($access['expires_at'])): ?>
                                            <?= date('d/m/Y H:i', strtotime($access['expires_at'])) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/access/<?= (int) $access['user_id'] ?>/revoke"
                                              style="display:inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Révoquer cet accès ?')">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Historique des transactions -->
<div class="card mt-2">
    <div class="card-header">
        <h3><i class="bi bi-clock-history"></i> Historique des opérations</h3>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>Aucune opération enregistrée pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th>Commentaire</th>
                            <th class="text-right">Montant</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                                <td>
                                    <?php if ($t['type'] === 'income'): ?>
                                        <span class="badge badge-success">Entrée</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Dépense</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($t['category']) ?></td>
                                <td><?= e($t['comment'] ?? '') ?></td>
                                <td class="text-right font-bold <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                    <?= $t['type'] === 'income' ? '+' : '-' ?><?= number_format((float) $t['amount'], 2, ',', ' ') ?>
                                </td>
                                <td>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/delete"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Supprimer cette opération ?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isOwner): ?>
<!-- Suppression du compte -->
<div class="card mt-2" style="border: 1px solid var(--danger);">
    <div class="card-body">
        <div class="d-flex justify-between align-center flex-wrap gap-1">
            <div>
                <h4 class="text-danger" style="margin:0">Supprimer ce compte</h4>
                <p class="text-small text-muted" style="margin:0">Cette action est irréversible. Toutes les données seront perdues.</p>
            </div>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/delete">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce compte et toutes ses données ?')">
                    <i class="bi bi-trash"></i> Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleExpires(select) {
    document.getElementById('expires_group').style.display = select.value === 'temporary' ? '' : 'none';
}
</script>
