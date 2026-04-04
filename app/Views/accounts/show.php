<div class="page-header">
    <div>
        <h1>
            <i class="bi bi-wallet2"></i> <?= e($account['name']) ?>
            <?php if ($isFrozen): ?>
                <span class="badge badge-frozen" style="font-size:0.55em;vertical-align:middle;"><i class="bi bi-snow"></i> Gelé</span>
            <?php endif; ?>
        </h1>
        <p class="page-description">
            <?php if ($isModerator && !$isOwner): ?>
                <span class="badge badge-mod" style="margin-right:0.4rem;"><i class="bi bi-shield-check"></i> Vue modérateur</span>
                Propriétaire : <?= e($owner['username'] ?? 'Inconnu') ?> — Devise : <?= e($account['currency']) ?>
            <?php elseif ($isOwner): ?>
                Mon compte — Devise : <?= e($account['currency']) ?>
            <?php else: ?>
                Compte partagé par <?= e($owner['username'] ?? 'Inconnu') ?> — Devise : <?= e($account['currency']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-group">
        <a href="<?= $isModerator && !$isOwner ? '/moderation' : '/dashboard' ?>" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
        <?php if ($isOwner): ?>
            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Modifier</a>
        <?php endif; ?>
        <?php if ($isModerator): ?>
            <?php if ($isFrozen): ?>
                <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/unfreeze" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Dégeler ce compte ?')">
                        <i class="bi bi-sun"></i> Dégeler
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/freeze" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-freeze btn-sm"
                            onclick="return confirm('Geler ce compte ? Les opérations sortantes seront bloquées.')">
                        <i class="bi bi-snow"></i> Geler
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($isFrozen): ?>
<div class="alert alert-frozen" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
    <i class="bi bi-snow" style="font-size:1.4rem;"></i>
    <div>
        <strong>Compte gelé.</strong>
        Les opérations sortantes et les virements débiteurs sont bloqués.
        Ce compte peut encore recevoir des versements.
    </div>
</div>
<?php endif; ?>

<?php if ($isOwner && empty($account['type'])): ?>
<div class="alert alert-warning" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <span>
        <i class="bi bi-tag"></i>
        <strong>Type de compte non défini.</strong>
        Définissez un type pour activer les règles de découvert adaptées.
    </span>
    <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="btn btn-warning btn-sm" style="white-space:nowrap;">
        <i class="bi bi-pencil"></i> Définir le type
    </a>
</div>
<?php endif; ?>

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

<?php
    $_od = (float) $account['overdraft'];
    if ($balance < 0):
        $_ratio = $_od > 0 ? min(100, round(abs($balance) / $_od * 100)) : 100;
        $_fillClass = $_ratio >= 100 ? 'overdraft-full' : ($_ratio >= 75 ? 'overdraft-critical' : '');
?>
<div class="card mb-2" style="border-left: 4px solid var(--danger);">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <div class="overdraft-gauge">
            <div class="overdraft-gauge-label">
                <span class="overdraft-icon">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?php if ($_od > 0): ?>
                        Découvert utilisé
                    <?php else: ?>
                        Compte en solde négatif — aucun découvert autorisé
                    <?php endif; ?>
                </span>
                <span><?= $_ratio ?> %</span>
            </div>
            <div class="overdraft-gauge-track">
                <div class="overdraft-gauge-fill <?= $_fillClass ?>" style="width: <?= $_ratio ?>%"></div>
            </div>
            <?php if ($_od > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-top:0.3rem; font-size:0.7rem; color: var(--text-muted);">
                <span><?= number_format(abs($balance), 2, ',', ' ') ?> <?= e($account['currency']) ?> utilisés</span>
                <span>Limite : <?= number_format($_od, 2, ',', ' ') ?> <?= e($account['currency']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="account-detail-grid">
    <!-- Formulaire d'ajout de transaction -->
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="bi bi-plus-circle"></i> Nouvelle opération</h3>
        </div>
        <div class="card-body">
            <?php if ($isFrozen): ?>
            <div class="alert alert-frozen" style="margin-bottom:1rem;">
                <i class="bi bi-snow"></i>
                <strong>Compte gelé.</strong> Seules les <strong>entrées</strong> sont autorisées sur ce compte.
            </div>
            <?php endif; ?>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions"
                  id="transaction-form"
                  data-balance="<?= e((string) $balance) ?>"
                  data-overdraft="<?= e((string) ((float) $account['overdraft'])) ?>"
                  data-no-overdraft="<?= \App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard') ? '0' : '1' ?>"
                  data-is-frozen="<?= $isFrozen ? '1' : '0' ?>">
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

                <!-- Avertissement découvert (affiché par JS) -->
                <div id="overdraft-warning" style="display:none; margin-bottom:0.75rem;">
                    <div class="alert <?= \App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard') ? 'alert-warning' : 'alert-danger' ?>" style="margin-bottom:0.5rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php if (\App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard')): ?>
                            <strong>Attention</strong> : cette opération dépasse le découvert autorisé.
                            Solde prévu&nbsp;: <strong id="overdraft-preview"></strong>.
                        <?php else: ?>
                            <strong>Opération impossible</strong> : ce type de compte
                            (<em><?= e(\App\Models\Account::TYPES[$account['type'] ?? 'standard']['label'] ?? '') ?></em>)
                            n'autorise pas le solde négatif.
                            Solde prévu&nbsp;: <strong id="overdraft-preview"></strong>.
                        <?php endif; ?>
                    </div>
                    <?php if (\App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard')): ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:600;color:var(--danger);">
                            <input type="checkbox" id="force-checkbox" name="force_overdraft" value="1">
                            Forcer l&rsquo;opération et accepter le dépassement
                        </label>
                    </div>
                    <?php else: ?>
                        <input type="hidden" id="force-checkbox" name="force_overdraft" value="0">
                    <?php endif; ?>
                </div>

                <button type="submit" id="transaction-submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Enregistrer
                </button>
            </form>

            <script>
            (function () {
                var form      = document.getElementById('transaction-form');
                var typeEl    = document.getElementById('type');
                var amountEl  = document.getElementById('amount');
                var warning   = document.getElementById('overdraft-warning');
                var preview   = document.getElementById('overdraft-preview');
                var checkbox  = document.getElementById('force-checkbox');
                var submitBtn = document.getElementById('transaction-submit');

                var balance    = parseFloat(form.dataset.balance)   || 0;
                var overdraft  = parseFloat(form.dataset.overdraft) || 0;
                var noOverdraft = form.dataset.noOverdraft === '1';
                var isFrozen   = form.dataset.isFrozen === '1';
                var currency   = '<?= e($account['currency']) ?>';

                // Si le compte est gelé, forcer le type sur "income" et désactiver la sélection
                if (isFrozen) {
                    typeEl.value = 'income';
                    for (var i = 0; i < typeEl.options.length; i++) {
                        if (typeEl.options[i].value === 'expense') {
                            typeEl.options[i].disabled = true;
                        }
                    }
                }

                function fmt(n) {
                    return n.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '\u00a0' + currency;
                }

                function check() {
                    var type   = typeEl.value;
                    var amount = parseFloat(amountEl.value) || 0;

                    if (type !== 'expense' || amount <= 0) {
                        warning.style.display = 'none';
                        checkbox.checked = false;
                        submitBtn.disabled = false;
                        return;
                    }

                    var newBalance = balance - amount;
                    var exceeds    = newBalance < -overdraft;

                    if (exceeds) {
                        preview.textContent = fmt(newBalance);
                        warning.style.display = 'block';
                        if (noOverdraft) {
                            // Type restreint : impossible de forcer
                            checkbox.style.display      = 'none';
                            checkbox.parentElement.style.display = 'none';
                            submitBtn.disabled = true;
                        } else {
                            checkbox.style.display      = '';
                            checkbox.parentElement.style.display = '';
                            submitBtn.disabled = !checkbox.checked;
                        }
                    } else {
                        warning.style.display = 'none';
                        checkbox.checked = false;
                        submitBtn.disabled = false;
                    }
                }

                checkbox.addEventListener('change', function () {
                    if (!noOverdraft) submitBtn.disabled = !this.checked;
                });

                typeEl.addEventListener('change', check);
                amountEl.addEventListener('input', check);
            })();
            </script>
        </div>
    </div>

    <!-- Partage de compte (propriétaire ou modérateur) -->
    <?php if ($isOwner || $isModerator): ?>
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="bi bi-person-plus"></i> Partager l'accès</h3>
        </div>
        <div class="card-body">
            <?php if ($isModerator && !$isOwner): ?>
            <div class="alert alert-info" style="margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
                <i class="bi bi-shield-check"></i>
                <span>Vous gérez ce compte en tant que <strong>modérateur</strong>.</span>
            </div>
            <?php endif; ?>
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
                            <th>Par</th>
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
                                <td>
                                    <span class="badge badge-secondary">
                                        <i class="bi bi-person"></i> <?= e($t['author_name']) ?>
                                    </span>
                                </td>
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
