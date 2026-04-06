<div class="page-header">
    <div>
        <h1>
            <i class="bi bi-wallet2"></i> <?= e($account['name']) ?>
            <?php if ($isFrozen): ?>
                <span class="badge badge-frozen" style="font-size:0.55em;vertical-align:middle;"><i class="bi bi-snow"></i> Gelé</span>
            <?php endif; ?>
        </h1>
        <p class="page-description">
            <?php if ($isOwner): ?>
                Mon compte — Devise : <?= e($account['currency']) ?>
            <?php elseif ($isGuardian): ?>
                <span class="badge" style="background:#f59e0b;color:#fff;margin-right:0.4rem;"><i class="bi bi-person-lock"></i> Responsable légal</span>
                <?php if ($isModerator): ?>
                    <span class="badge badge-mod" style="margin-right:0.4rem;"><i class="bi bi-shield-check"></i> Modérateur</span>
                <?php endif; ?>
                Compte de <?= e($owner['username'] ?? 'Inconnu') ?> — Devise : <?= e($account['currency']) ?>
            <?php elseif ($isModerator && !$isOwner): ?>
                <span class="badge badge-mod" style="margin-right:0.4rem;"><i class="bi bi-shield-check"></i> Vue modérateur</span>
                Propriétaire : <?= e($owner['username'] ?? 'Inconnu') ?> — Devise : <?= e($account['currency']) ?>
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
    <?php if ($hasPending): ?>
    <div class="stat-card" style="border-left:3px solid var(--warning, #f59e0b);">
        <div class="stat-value <?= $futureBalance >= 0 ? 'text-success' : 'text-danger' ?>" style="display:flex;align-items:center;gap:0.4rem;">
            <?= number_format($futureBalance, 2, ',', ' ') ?>
            <i class="bi bi-clock" style="font-size:0.7em;opacity:0.7;"></i>
        </div>
        <div class="stat-label">Solde à venir (<?= e($account['currency']) ?>)</div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-value text-success"><?= number_format($totalIncome, 2, ',', ' ') ?></div>
        <div class="stat-label">Total entrées<?= $hasPending && $totalIncomeFuture > $totalIncome ? ' <span style="font-size:0.75em;opacity:0.7;">(' . number_format($totalIncomeFuture, 2, ',', ' ') . ' à venir)</span>' : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-danger"><?= number_format($totalExpense, 2, ',', ' ') ?></div>
        <div class="stat-label">Total dépenses<?= $hasPending && $totalExpenseFuture > $totalExpense ? ' <span style="font-size:0.75em;opacity:0.7;">(' . number_format($totalExpenseFuture, 2, ',', ' ') . ' à venir)</span>' : '' ?></div>
    </div>
    <?php if ((float) $account['overdraft'] > 0): ?>
        <div class="stat-card">
            <div class="stat-value"><?= number_format((float) $account['overdraft'], 2, ',', ' ') ?></div>
            <div class="stat-label">Découvert autorisé (<?= e($account['currency']) ?>)</div>
        </div>
    <?php endif; ?>
    <?php if (\App\Models\Account::typeHasCap($account['type'] ?? '') && (float) ($account['cap'] ?? 0) > 0): ?>
        <div class="stat-card" style="border-left:3px solid var(--primary)">
            <div class="stat-value"><?= number_format((float) $account['cap'], 2, ',', ' ') ?></div>
            <div class="stat-label">Plafond d'épargne (<?= e($account['currency']) ?>)</div>
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
            <?php if (\App\Models\Account::typeHasCap($account['type'] ?? '') && (float) ($account['cap'] ?? 0) > 0 && !$isModerator): ?>
            <div class="alert alert-info" style="margin-bottom:1rem;">
                <i class="bi bi-piggy-bank"></i>
                Plafond d'épargne : <strong><?= number_format((float) $account['cap'], 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>.
                Les crédits dépassant ce plafond sont bloqués.
            </div>
            <?php endif; ?>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions"
                  id="transaction-form"
                  data-balance="<?= e((string) $balance) ?>"
                  data-overdraft="<?= e((string) ((float) $account['overdraft'])) ?>"
                  data-no-overdraft="<?= \App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard') ? '0' : '1' ?>"
                  data-is-frozen="<?= $isFrozen ? '1' : '0' ?>"
                  data-is-moderator="<?= $isModerator ? '1' : '0' ?>">
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
                <div class="form-row">
                    <div class="form-group">
                        <label for="scheduled_at" class="form-label">
                            <i class="bi bi-clock"></i> Date programmée <span class="text-muted" style="font-weight:400;font-size:0.85em;">(optionnel — laisser vide pour maintenant)</span>
                        </label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control">
                    </div>
                </div>

                <!-- Avertissement découvert (affiché par JS) -->
                <div id="overdraft-warning" style="display:none; margin-bottom:0.75rem;">
                    <?php $typeAllows = \App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard'); ?>
                    <div class="alert <?= ($typeAllows || $isModerator) ? 'alert-warning' : 'alert-danger' ?>" style="margin-bottom:0.5rem;display:flex;align-items:baseline;gap:0.5rem;">
                        <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
                        <span>
                        <?php if ($typeAllows): ?>
                            <strong>Attention</strong> : cette opération dépasse le découvert autorisé.
                            Solde prévu&nbsp;: <strong id="overdraft-preview"></strong>.
                        <?php elseif ($isModerator): ?>
                            <strong>Exception modérateur</strong> : le type «&nbsp;<?= e(\App\Models\Account::TYPES[$account['type'] ?? 'standard']['label'] ?? '') ?>&nbsp;» n'autorise pas normalement le solde négatif.
                            Solde prévu&nbsp;: <strong id="overdraft-preview"></strong>.
                        <?php else: ?>
                            <strong>Opération impossible</strong> : le type «&nbsp;<?= e(\App\Models\Account::TYPES[$account['type'] ?? 'standard']['label'] ?? '') ?>&nbsp;» n'autorise pas le solde négatif.
                            Solde prévu&nbsp;: <strong id="overdraft-preview"></strong>.
                        <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($typeAllows || $isModerator): ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:600;color:var(--danger);">
                            <input type="checkbox" id="force-checkbox" name="force_overdraft" value="1">
                            <?= $isModerator && !$typeAllows ? 'Confirmer l&rsquo;exception et forcer l&rsquo;opération' : 'Forcer l&rsquo;opération et accepter le dépassement' ?>
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

                var balance      = parseFloat(form.dataset.balance)   || 0;
                var overdraft    = parseFloat(form.dataset.overdraft) || 0;
                var noOverdraft  = form.dataset.noOverdraft === '1';
                var isFrozen     = form.dataset.isFrozen === '1';
                var isModerator  = form.dataset.isModerator === '1';
                var canForce     = !noOverdraft || isModerator;
                var currency     = '<?= e($account['currency']) ?>';

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
                        if (canForce) {
                            checkbox.style.display      = '';
                            checkbox.parentElement.style.display = '';
                            submitBtn.disabled = !checkbox.checked;
                        } else {
                            // Type restreint et non modérateur : impossible de forcer
                            checkbox.style.display      = 'none';
                            checkbox.parentElement.style.display = 'none';
                            submitBtn.disabled = true;
                        }
                    } else {
                        warning.style.display = 'none';
                        checkbox.checked = false;
                        submitBtn.disabled = false;
                    }
                }

                checkbox.addEventListener('change', function () {
                    if (canForce) submitBtn.disabled = !this.checked;
                });

                typeEl.addEventListener('change', check);
                amountEl.addEventListener('input', check);
            })();
            </script>
        </div>
    </div>

    <!-- Partage de compte (propriétaire ou modérateur, interdit pour comptes mineurs sauf modérateur) -->
    <?php if ($isMinorAccount && $isOwner && !$isModerator): ?>
    <div class="card mb-2" style="border-left:4px solid #f59e0b;">
        <div class="card-header">
            <h3><i class="bi bi-person-plus"></i> Partager l'accès</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning" style="margin:0;display:flex;align-items:center;gap:0.6rem;">
                <i class="bi bi-lock-fill" style="font-size:1.2rem;"></i>
                <span>Le partage d'accès est <strong>interdit</strong> pour les comptes mineurs.
                Seul un <strong>modérateur</strong> peut accorder des accès exceptionnels.</span>
            </div>
        </div>
    </div>
    <?php elseif ($isOwner || $isModerator): ?>
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
            <?php if (!$isModerator && \App\Models\Account::isAdultOnlyAccount($account)): ?>
            <div class="alert alert-warning" style="margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
                <i class="bi bi-person-x-fill" style="flex-shrink:0;font-size:1.1rem;"></i>
                <span>Ce type de compte (<strong><?= e(\App\Models\Account::TYPES[$account['type']]['label'] ?? $account['type']) ?></strong>)
                est <strong>réservé aux majeurs</strong>. L'ajout d'un utilisateur mineur sera
                automatiquement refusé. Seul un <strong>modérateur</strong> peut accorder une dérogation.</span>
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

    <!-- Responsables légaux (compte mineur) -->
    <?php if ($isMinorAccount): ?>
    <div class="card mb-2" style="border-left:4px solid #f59e0b;">
        <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;">
            <h3 style="margin:0;"><i class="bi bi-person-lock" style="color:#f59e0b;"></i> Responsables légaux</h3>
            <span class="badge" style="background:#f59e0b;color:#fff;">Compte mineur</span>
        </div>
        <div class="card-body">
            <?php if (empty($guardians)): ?>
                <div class="alert alert-warning" style="margin:0;font-size:0.88rem;">
                    <i class="bi bi-exclamation-triangle"></i>
                    Aucun responsable légal désigné pour ce compte.
                    <?php if ($isModerator): ?>
                        <a href="/moderation/guardianships" style="margin-left:0.4rem;">
                            <i class="bi bi-person-plus"></i> Ajouter un tuteur
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php foreach ($guardians as $guardian): ?>
                    <div style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.75rem;
                                background:rgba(245,158,11,0.06);border-radius:var(--border-radius,6px);">
                        <i class="bi bi-person-check-fill" style="color:#f59e0b;font-size:1.1rem;"></i>
                        <span class="font-bold"><?= e($guardian['username']) ?></span>
                        <span class="badge badge-secondary text-small">Responsable légal</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:0.75rem;font-size:0.78rem;color:var(--text-muted);">
                    <i class="bi bi-info-circle"></i>
                    La procuration expire automatiquement à la majorité du titulaire.
                </div>
                <?php if ($isModerator): ?>
                <div style="margin-top:0.6rem;">
                    <a href="/moderation/guardianships" class="btn btn-outline btn-sm">
                        <i class="bi bi-pencil"></i> Gérer les tutelles
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ======================================================= -->
<!-- Section : Opérations à venir                          -->
<!-- ======================================================= -->
<?php $hasUpcoming = !empty($pendingTransactions) || !empty($upcomingDebits) || !empty($upcomingMandates); ?>
<div class="card mt-2" style="border-left: 3px solid var(--warning, #f59e0b);">
    <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;">
        <h3 style="margin:0;"><i class="bi bi-clock" style="color:var(--warning,#f59e0b);"></i> Opérations à venir</h3>
        <?php if ($hasUpcoming): ?>
            <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;">
                <?= count($pendingTransactions) + count($upcomingDebits) + count($upcomingMandates) ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$hasUpcoming): ?>
            <div class="empty-state">
                <div class="empty-icon">🕐</div>
                <p>Aucune opération à venir.</p>
            </div>
        <?php else: ?>

            <?php if (!empty($pendingTransactions)): ?>
            <!-- Transactions programmées (income / expense programmés) -->
            <h4 style="margin-bottom:0.6rem;font-size:0.95rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-calendar-event"></i> Débits / Crédits programmés
                <span class="badge badge-secondary"><?= count($pendingTransactions) ?></span>
            </h4>
            <div class="table-responsive" style="margin-bottom:1.25rem;">
                <table class="table" id="pending-tx-table">
                    <thead>
                        <tr>
                            <th>Date prévue</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th>Par</th>
                            <th>Commentaire</th>
                            <th class="text-right">Montant</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingTransactions as $t): ?>
                            <tr style="opacity:0.85;font-style:italic;">
                                <td>
                                    <i class="bi bi-clock" style="color:var(--warning,#f59e0b);"></i>
                                    <?= date('d/m/Y H:i', strtotime($t['scheduled_at'])) ?>
                                    <br><small class="text-muted" style="font-style:normal;">Créée le <?= date('d/m/Y', strtotime($t['created_at'])) ?></small>
                                </td>
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
                                    <?php if ($isModerator): ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/delete"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Supprimer cette opération programmée ?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($upcomingDebits)): ?>
            <!-- Prélèvements planifiés -->
            <h4 style="margin-bottom:0.6rem;font-size:0.95rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-bank"></i> Prélèvements planifiés
                <span class="badge badge-secondary"><?= count($upcomingDebits) ?></span>
            </h4>
            <div class="table-responsive">
                <table class="table" id="upcoming-debits-table">
                    <thead>
                        <tr>
                            <th>Date prévue</th>
                            <th>N° mandat</th>
                            <th>Motif</th>
                            <th class="text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingDebits as $d): ?>
                            <?php $isDue = strtotime($d['scheduled_at']) <= time(); ?>
                            <tr style="opacity:0.85;font-style:italic;">
                                <td>
                                    <?php if ($isDue): ?>
                                        <i class="bi bi-hourglass-split" style="color:var(--danger);"></i>
                                        <strong style="color:var(--danger);"><?= date('d/m/Y H:i', strtotime($d['scheduled_at'])) ?></strong>
                                        <br><small class="text-danger" style="font-style:normal;">En attente d'exécution</small>
                                    <?php else: ?>
                                        <i class="bi bi-clock" style="color:var(--warning,#f59e0b);"></i>
                                        <?= date('d/m/Y H:i', strtotime($d['scheduled_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= e($d['mandate_number']) ?></span>
                                </td>
                                <td><?= e($d['motif'] ?? '—') ?></td>
                                <td class="text-right font-bold text-danger">
                                    -<?= number_format((float) $d['amount'], 2, ',', ' ') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($upcomingMandates)): ?>
            <!-- Mandats à venir -->
            <h4 style="margin-bottom:0.6rem;font-size:0.95rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-file-earmark-text"></i> Mandats à venir
                <span class="badge badge-secondary"><?= count($upcomingMandates) ?></span>
            </h4>
            <div class="table-responsive">
                <table class="table" id="upcoming-mandates-table">
                    <thead>
                        <tr>
                            <th>Prochaine exéc.</th>
                            <th>N° Mandat</th>
                            <th>Opération</th>
                            <th>Contrepartie</th>
                            <th>Descriptif</th>
                            <th class="text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingMandates as $um): ?>
                            <?php
                                $isDue    = strtotime($um['next_execution_at']) <= time();
                                $isEmitter = (int) $um['emitter_account_id'] === (int) $account['id'];
                            ?>
                            <tr style="opacity:0.85;font-style:italic;">
                                <td>
                                    <?php if ($isDue): ?>
                                        <i class="bi bi-hourglass-split" style="color:var(--danger);"></i>
                                        <strong style="color:var(--danger);"><?= date('d/m/Y H:i', strtotime($um['next_execution_at'])) ?></strong>
                                        <br><small class="text-danger" style="font-style:normal;">En attente d'exécution</small>
                                    <?php else: ?>
                                        <i class="bi bi-clock" style="color:var(--warning,#f59e0b);"></i>
                                        <?= date('d/m/Y H:i', strtotime($um['next_execution_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= e($um['number']) ?></span>
                                    <?php if ($um['type'] === 'recurring'): ?>
                                        <br><small class="text-muted" style="font-style:normal;"><i class="bi bi-arrow-repeat"></i> tous les <?= (int) $um['interval_days'] ?> j</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isEmitter): ?>
                                        <span class="badge badge-success">Crédit</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Débit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isEmitter): ?>
                                        <?= e($um['recipient_name']) ?> <small class="text-muted">(<?= e($um['recipient_owner']) ?>)</small>
                                    <?php else: ?>
                                        <?= e($um['emitter_name']) ?> <small class="text-muted">(<?= e($um['emitter_owner']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($um['description'] ?: '—') ?></td>
                                <td class="text-right font-bold <?= $isEmitter ? 'text-success' : 'text-danger' ?>">
                                    <?= $isEmitter ? '+' : '-' ?><?= number_format((float) $um['amount'], 2, ',', ' ') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- ======================================================= -->
<!-- Section : Opérations effectuées                         -->
<!-- ======================================================= -->
<div class="card mt-2">
    <div class="card-header">
        <h3><i class="bi bi-clock-history"></i> Opérations effectuées</h3>
    </div>
    <div class="card-body">
        <?php if (empty($executedTransactions)): ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>Aucune opération effectuée pour le moment.</p>
            </div>
        <?php else: ?>
            <?php
                $txAuthors = array_values(array_unique(array_column($executedTransactions, 'author_name')));
                sort($txAuthors);
            ?>
            <!-- Barre de filtres -->
            <div class="filter-bar" id="tx-filter-bar" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;margin-bottom:1rem;">
                <div class="form-group" style="margin:0;min-width:150px;">
                    <label class="form-label" style="font-size:0.8rem;">Type</label>
                    <select id="tx-filter-type" class="form-control form-control-sm">
                        <option value="">Tous</option>
                        <option value="income">Entrées</option>
                        <option value="expense">Dépenses</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;min-width:110px;">
                    <label class="form-label" style="font-size:0.8rem;">Montant min</label>
                    <input type="number" id="tx-filter-min" class="form-control form-control-sm" min="0" step="0.01" placeholder="0,00">
                </div>
                <div class="form-group" style="margin:0;min-width:110px;">
                    <label class="form-label" style="font-size:0.8rem;">Montant max</label>
                    <input type="number" id="tx-filter-max" class="form-control form-control-sm" min="0" step="0.01" placeholder="∞">
                </div>
                <div class="form-group" style="margin:0;min-width:150px;">
                    <label class="form-label" style="font-size:0.8rem;">Auteur</label>
                    <select id="tx-filter-author" class="form-control form-control-sm">
                        <option value="">Tous</option>
                        <?php foreach ($txAuthors as $a): ?>
                            <option value="<?= e($a) ?>"><?= e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-size:0.8rem;">&nbsp;</label>
                    <button type="button" id="tx-filter-reset" class="btn btn-outline btn-sm" style="display:block;">
                        <i class="bi bi-x-lg"></i> Réinitialiser
                    </button>
                </div>
                <div style="margin-left:auto;align-self:flex-end;">
                    <span id="tx-filter-count" class="text-muted" style="font-size:0.82rem;"></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="tx-table">
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
                        <?php foreach ($executedTransactions as $t): ?>
                            <tr data-type="<?= e($t['type']) ?>"
                                data-amount="<?= e((string) (float) $t['amount']) ?>"
                                data-author="<?= e($t['author_name']) ?>">
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
                                    <?php if ($isModerator): ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/delete"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Supprimer cette opération ?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p id="tx-empty-filtered" style="display:none;text-align:center;color:var(--text-muted);padding:1rem 0;">
                    Aucune opération ne correspond aux filtres.
                </p>
            </div>
            <script>
            (function () {
                var typeEl   = document.getElementById('tx-filter-type');
                var minEl    = document.getElementById('tx-filter-min');
                var maxEl    = document.getElementById('tx-filter-max');
                var authorEl = document.getElementById('tx-filter-author');
                var resetBtn = document.getElementById('tx-filter-reset');
                var countEl  = document.getElementById('tx-filter-count');
                var emptyMsg = document.getElementById('tx-empty-filtered');
                var rows     = document.querySelectorAll('#tx-table tbody tr');

                function applyFilters() {
                    var type   = typeEl.value;
                    var min    = minEl.value !== '' ? parseFloat(minEl.value) : null;
                    var max    = maxEl.value !== '' ? parseFloat(maxEl.value) : null;
                    var author = authorEl.value.toLowerCase();
                    var visible = 0;

                    rows.forEach(function (row) {
                        var rType   = row.dataset.type;
                        var rAmount = parseFloat(row.dataset.amount);
                        var rAuthor = row.dataset.author.toLowerCase();

                        var ok = true;
                        if (type   && rType !== type)     ok = false;
                        if (min !== null && rAmount < min) ok = false;
                        if (max !== null && rAmount > max) ok = false;
                        if (author && rAuthor !== author)  ok = false;

                        row.style.display = ok ? '' : 'none';
                        if (ok) visible++;
                    });

                    countEl.textContent = visible + ' / ' + rows.length + ' opération' + (rows.length > 1 ? 's' : '');
                    emptyMsg.style.display = visible === 0 ? '' : 'none';
                }

                typeEl.addEventListener('change', applyFilters);
                minEl.addEventListener('input', applyFilters);
                maxEl.addEventListener('input', applyFilters);
                authorEl.addEventListener('change', applyFilters);

                resetBtn.addEventListener('click', function () {
                    typeEl.value   = '';
                    minEl.value    = '';
                    maxEl.value    = '';
                    authorEl.value = '';
                    applyFilters();
                });

                applyFilters();
            })();
            </script>
        <?php endif; ?>
    </div>
</div>

<?php if ($account['type'] === 'pro' && !empty($mandates)): ?>
<!-- Mandats professionnels -->
<div class="card mt-2">
    <div class="card-body">
        <h3><i class="bi bi-file-earmark-text"></i> Mandats</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Mandat</th>
                        <th>Rôle</th>
                        <th>Contrepartie</th>
                        <th>Montant</th>
                        <th>Type</th>
                        <th>Intervalle</th>
                        <th>Prochaine exéc.</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mandates as $mandate): ?>
                        <tr>
                            <td><strong><?= e($mandate['number']) ?></strong></td>
                            <td>
                                <?php if ((int) $mandate['emitter_account_id'] === (int) $account['id']): ?>
                                    <span class="badge" style="background:#16a34a;color:#fff;">Émetteur</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#2563eb;color:#fff;">Destinataire</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $mandate['emitter_account_id'] === (int) $account['id']): ?>
                                    <?= e($mandate['recipient_name']) ?> (<?= e($mandate['recipient_owner']) ?>)
                                <?php else: ?>
                                    <?= e($mandate['emitter_name']) ?> (<?= e($mandate['emitter_owner']) ?>)
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float) $mandate['amount'], 2, ',', ' ') ?> €</td>
                            <td><?= $mandate['type'] === 'recurring' ? 'Récurrent' : 'Ponctuel' ?></td>
                            <td><?= $mandate['type'] === 'recurring' && $mandate['interval_days'] ? $mandate['interval_days'] . ' j' : '—' ?></td>
                            <td style="font-size:0.88rem;">
                                <?php if (!empty($mandate['next_execution_at'])): ?>
                                    <?= e(date('d/m/Y H:i', strtotime($mandate['next_execution_at']))) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($mandate['status'] === 'active'): ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php elseif ($mandate['status'] === 'executed'): ?>
                                    <span class="badge badge-info">Exécuté</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Révoqué</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isOwner || $isModerator): ?>
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
