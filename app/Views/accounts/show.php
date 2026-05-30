<?php
/**
 * @var array       $account
 * @var array       $transactions
 * @var array       $pendingTransactions
 * @var array       $executedTransactions
 * @var int         $txPage
 * @var int         $txTotalPages
 * @var int         $txTotalCount
 * @var int         $txPerPage
 * @var array       $upcomingDebits
 * @var array       $upcomingLoanInstallments
 * @var float       $balance
 * @var float       $futureBalance
 * @var bool        $hasPending
 * @var float       $totalIncome
 * @var float       $totalExpense
 * @var float       $totalIncomeFuture
 * @var float       $totalExpenseFuture
 * @var bool        $isOwner
 * @var bool        $isModerator
 * @var bool        $isFrozen
 * @var bool        $isDisabled
 * @var array       $accesses
 * @var array|null  $owner
 * @var array       $guardians
 * @var bool        $isMinorAccount
 * @var bool        $isGuardian
 * @var array       $categories
 * @var array       $expenseCategories
 * @var array       $incomeCategories
 * @var array       $mandates
 * @var array       $upcomingMandates
 * @var array       $linkedTxIds
 * @var array       $recurringTransfers
 * @var float|null  $accruedInterest
 * @var bool        $deferredDebitEnabled
 * @var array       $pendingDeferredDebits
 * @var array       $executedDeferredDebits
 * @var int|null    $deferredDebitDay
 * @var array       $posPayments
 * @var array       $activeCheckbooks
 * @var array       $pendingChecks
 */
?>
<div class="account-show-page">
<div class="page-header">
    <div>
        <h1>
            <i class="bi bi-wallet2"></i> <?= e($account['name']) ?>
            <?php if (!empty($account['internal'])): ?>
                <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;font-size:0.55em;vertical-align:middle;" title="Compte interne de modération (test) — non partageable"><i class="bi bi-tools"></i> Interne</span>
            <?php endif; ?>
            <?php if ($isFrozen): ?>
                <span class="badge badge-frozen" style="font-size:0.55em;vertical-align:middle;"><i class="bi bi-snow"></i> Gelé</span>
            <?php endif; ?>
            <?php if ($isDisabled): ?>
                <span class="badge" style="background:var(--danger);color:#fff;font-size:0.55em;vertical-align:middle;"><i class="bi bi-slash-circle"></i> En résiliation</span>
            <?php endif; ?>
            <?php if (!empty($account['hidden_from_owner'])): ?>
                <span class="badge" style="background:#6b7280;color:#fff;font-size:0.55em;vertical-align:middle;" title="Ce compte est masqué pour son propriétaire"><i class="bi bi-eye-slash"></i> Masqué</span>
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
        <a href="/accounts/<?= (int) $account['id'] ?>/statement" class="btn btn-outline btn-sm" title="Générer un relevé PDF">
            <i class="bi bi-file-earmark-pdf"></i> Relevé
        </a>
        <?php
            $activeRecurring = count(array_filter($recurringTransfers ?? [], fn($r) => ($r['status'] ?? '') === 'active'));
        ?>
        <a href="#recurring-transfers" class="btn btn-outline btn-sm" title="Virements permanents">
            <i class="bi bi-arrow-repeat"></i> Permanents
            <?php if ($activeRecurring > 0): ?>
                <span style="display:inline-flex;align-items:center;justify-content:center;
                             background:var(--primary);color:#fff;border-radius:999px;
                             font-size:0.7em;min-width:1.35em;height:1.35em;padding:0 0.3em;
                             line-height:1;margin-left:0.25rem;font-style:normal;"><?= $activeRecurring ?></span>
            <?php endif; ?>
        </a>
        <?php if (($account['type'] ?? '') === 'pro' && !empty($posPayments)): ?>
        <a href="#pos-payments-section" class="btn btn-outline btn-sm" style="border-color:var(--success,#22c55e);color:var(--success,#22c55e);" title="Encaissements TPE">
            <i class="bi bi-credit-card-2-front"></i> Encaissements TPE
            <span style="display:inline-flex;align-items:center;justify-content:center;
                         background:var(--success,#22c55e);color:#fff;border-radius:999px;
                         font-size:0.7em;min-width:1.35em;height:1.35em;padding:0 0.3em;
                         line-height:1;margin-left:0.25rem;font-style:normal;"><?= count(array_filter($posPayments, fn($p) => empty($p['cancelled_at']))) ?></span>
        </a>
        <?php endif; ?>
        <?php if ($isOwner): ?>
            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Modifier</a>
        <?php endif; ?>
        <?php if ($isGuardian): ?>
            <a href="/accounts/<?= (int) $account['id'] ?>/edit" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Modifier</a>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/toggle-hidden" style="display:inline">
                <?= csrf_field() ?>
                <?php if (!empty($account['hidden_from_owner'])): ?>
                    <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Rendre ce compte visible pour <?= e(addslashes($owner['username'] ?? 'le mineur')) ?> ?')">
                        <i class="bi bi-eye"></i> Afficher à l’enfant
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-secondary btn-sm"
                            onclick="return confirm('Masquer ce compte à <?= e(addslashes($owner['username'] ?? 'le mineur')) ?> ?\nIl restera propriétaire mais ne pourra plus le consulter.')">
                        <i class="bi bi-eye-slash"></i> Masquer à l’enfant
                    </button>
                <?php endif; ?>
            </form>
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
                <button type="button" class="btn btn-freeze btn-sm"
                        onclick="openFreezeModal(<?= (int) $account['id'] ?>, <?= htmlspecialchars(json_encode($account['name']), ENT_QUOTES) ?>)">
                    <i class="bi bi-snow"></i> Geler
                </button>
            <?php endif; ?>
            <?php if ($isDisabled): ?>
                <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/enable" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Réactiver ce compte et annuler la résiliation ?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Réactiver
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/disable" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Désactiver ce compte ? Il sera supprimé définitivement en fin de mois.')">
                        <i class="bi bi-slash-circle"></i> Désactiver
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!empty($account['internal']) && ($account['type'] ?? '') === 'savings' && !empty($account['interest_rate'])): ?>
                <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/compute-interests" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm"
                            style="background:#10b981;color:#fff;border:none;"
                            onclick="return confirm('Recalculer et créer une entrée d\'intérêts en attente pour ce compte interne ?')">
                        <i class="bi bi-graph-up-arrow"></i> Simuler intérêts
                    </button>
                </form>
            <?php endif; ?>
            <?php
                $typeAllowsOd     = \App\Models\Account::typeAllowsOverdraft($account['type'] ?? 'standard');
                $overdraftLimit   = $typeAllowsOd ? -(float)($account['overdraft'] ?? 0) : 0.0;
                $isOverdraftExceed = $balance < $overdraftLimit;
            ?>
            <button type="button" class="btn <?= $isOverdraftExceed ? 'btn-danger' : 'btn-warning' ?> btn-sm"
                    onclick="openAgiosModal()">
                <i class="bi bi-exclamation-triangle-fill"></i> Prélever agios
            </button>
            <a href="/moderation/accounts/<?= (int) $account['id'] ?>/agios"
               class="btn btn-outline btn-sm"
               title="Rapport des épisodes de dépassement de découvert et rejets associés">
                <i class="bi bi-graph-down-arrow"></i> Rapport agios
            </a>
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
        <?php if (!empty($account['frozen_reason'])): ?>
            <br><span style="font-size:0.85em;"><i class="bi bi-chat-left-text"></i> <strong>Motif :</strong> <?= e($account['frozen_reason']) ?></span>
        <?php endif; ?>
        <?php if (!empty($account['frozen_until'])): ?>
            <br><span style="font-size:0.85em;"><i class="bi bi-clock"></i> Dégel automatique prévu le
                <strong><?= e((new DateTime($account['frozen_until']))->format('d/m/Y à H\hi')) ?></strong>.
            </span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($isDisabled): ?>
<div class="alert alert-danger" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
    <i class="bi bi-slash-circle" style="font-size:1.4rem;"></i>
    <div>
        <strong>Compte en cours de résiliation.</strong>
        Désactivé le <?= date('d/m/Y', strtotime($account['disabled_at'] ?? '')) ?>.
        Aucune nouvelle opération ne peut être enregistrée. Les virements sortants sont bloqués.
        La réception de virements reste possible. Ce compte sera définitivement supprimé à la fin du mois.
        <?php if ($isOwner && !$isModerator): ?>
        <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/enable" style="display:inline;margin-left:0.75rem;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Annuler la résiliation et réactiver ce compte ?')">
                <i class="bi bi-arrow-counterclockwise"></i> Annuler la résiliation
            </button>
        </form>
        <?php endif; ?>
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
            <?= fmt_amount_smart($balance) ?>
        </div>
        <div class="stat-label">Solde actuel (<?= e($account['currency']) ?>)</div>
    </div>
    <?php if ($hasPending): ?>
    <div class="stat-card" style="border-left:3px solid var(--warning, #f59e0b);">
        <div class="stat-value <?= $futureBalance >= 0 ? 'text-success' : 'text-danger' ?>" style="display:flex;align-items:center;gap:0.4rem;">
            <?= fmt_amount_smart($futureBalance) ?>
            <i class="bi bi-clock" style="font-size:0.7em;opacity:0.7;"></i>
        </div>
        <div class="stat-label">Solde à venir (<?= e($account['currency']) ?>)</div>
    </div>
    <?php endif; ?>
    <div class="stat-card stat-hide-mobile">
        <div class="stat-value text-success"><?= fmt_amount_smart($totalIncome) ?></div>
        <div class="stat-label">Total entrées<?= $hasPending && $totalIncomeFuture > $totalIncome ? ' <span style="font-size:0.75em;opacity:0.7;">(' . fmt_amount_smart($totalIncomeFuture) . ' à venir)</span>' : '' ?></div>
    </div>
    <div class="stat-card stat-hide-mobile">
        <div class="stat-value text-danger"><?= fmt_amount_smart($totalExpense) ?></div>
        <div class="stat-label">Total dépenses<?= $hasPending && $totalExpenseFuture > $totalExpense ? ' <span style="font-size:0.75em;opacity:0.7;">(' . fmt_amount_smart($totalExpenseFuture) . ' à venir)</span>' : '' ?></div>
    </div>
    <?php if ((float) $account['overdraft'] > 0): ?>
        <div class="stat-card">
            <div class="stat-value"><?= fmt_amount_smart((float) $account['overdraft']) ?></div>
            <div class="stat-label">Découvert autorisé (<?= e($account['currency']) ?>)</div>
        </div>
    <?php endif; ?>
    <?php if (\App\Models\Account::typeHasCap($account['type'] ?? '') && (float) ($account['cap'] ?? 0) > 0): ?>
        <div class="stat-card" style="border-left:3px solid var(--primary)">
            <div class="stat-value"><?= fmt_amount_smart((float) $account['cap']) ?></div>
            <div class="stat-label">Plafond d'épargne (<?= e($account['currency']) ?>)</div>
        </div>
    <?php endif; ?>
    <?php if ($accruedInterest !== null): ?>
        <div class="stat-card" style="border-left:3px solid #10b981;" title="Intérêts calculés au prorata temporis depuis le 1er janvier. Remis à zéro chaque 1er janvier.">
            <div class="stat-value text-success" style="display:flex;align-items:center;gap:0.4rem;">
                +<?= fmt_amount_smart($accruedInterest) ?>
                <i class="bi bi-graph-up-arrow" style="font-size:0.7em;opacity:0.7;"></i>
            </div>
            <div class="stat-label">
                Intérêts en cours <?= date('Y') ?> (<?= e($account['currency']) ?>)
                <span style="font-size:0.7em;opacity:0.65;display:block;">
                    Taux : <?= number_format((float) $account['interest_rate'] * 100, 2, ',', ' ') ?> % — remis à zéro le 1<sup>er</sup> janv.
                </span>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($account['balance_alert_threshold'])): ?>
        <div class="stat-card" style="border-left:3px solid var(--warning, #f59e0b)">
            <div class="stat-value" style="font-size:1.1rem;"><?= fmt_amount_smart((float) $account['balance_alert_threshold']) ?></div>
            <div class="stat-label"><i class="bi bi-bell-fill"></i> Seuil d'alerte (<?= e($account['currency']) ?>)</div>
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
                <span><?= fmt_amount_smart(abs($balance)) ?> <?= e($account['currency']) ?> utilisés</span>
                <span>Limite : <?= fmt_amount_smart($_od) ?> <?= e($account['currency']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($account['balance_alert_threshold']) && $balance < (float) $account['balance_alert_threshold']): ?>
<div class="alert" style="border-left:4px solid var(--warning);display:flex;align-items:center;gap:0.75rem;">
    <i class="bi bi-bell-fill text-warning" style="font-size:1.3rem;"></i>
    <div>
        <strong>Solde sous le seuil d'alerte.</strong>
        Le solde actuel (<?= fmt_amount_smart($balance) ?> <?= e($account['currency']) ?>)
        est inférieur au seuil configuré
        (<?= fmt_amount_smart((float) $account['balance_alert_threshold']) ?> <?= e($account['currency']) ?>).
    </div>
</div>
<?php endif; ?>

<?php if (!empty($overdraftAuthorization)): ?>
<?php
    $__auth       = $overdraftAuthorization;
    $__authStatus = \App\Models\OverdraftAuthorization::computeStatus($__auth);
?>
<div class="alert" style="border-left:4px solid var(--success);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <i class="bi bi-shield-plus" style="font-size:1.3rem;color:var(--success);flex-shrink:0;"></i>
        <div>
            <strong>Autorisation de dépassement active.</strong>
            Limite supplémentaire de <strong><?= fmt_amount_smart((float) $__auth['extra_limit']) ?> <?= e($account['currency']) ?></strong>
            accordée à partir du <?= e(date('d/m/Y', strtotime($__auth['start_date']))) ?>
            <?= $__auth['end_date'] ? 'jusqu\'au ' . e(date('d/m/Y', strtotime($__auth['end_date']))) : '(sans date de fin)' ?>.
            <?php if (!empty($__auth['reason'])): ?>
                — <em><?= e($__auth['reason']) ?></em>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($isModerator): ?>
    <form method="POST" action="/moderation/overdraft-authorizations/<?= (int) $__auth['id'] ?>/revoke"
          onsubmit="return confirm('Révoquer cette autorisation ?');" style="flex-shrink:0;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-x-circle"></i> Révoquer
        </button>
    </form>
    <?php endif; ?>
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
            <?php if ($isDisabled && !$isModerator): ?>
            <div class="alert alert-danger" style="margin-bottom:0;display:flex;align-items:center;gap:0.6rem;">
                <i class="bi bi-slash-circle" style="font-size:1.2rem;flex-shrink:0;"></i>
                <span><strong>Compte en résiliation.</strong> L'enregistrement de nouvelles opérations est désactivé.</span>
            </div>
            <?php else: ?>
            <?php if ($isDisabled && $isModerator): ?>
            <div class="alert alert-warning" style="margin-bottom:1rem;display:flex;align-items:center;gap:0.6rem;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:1.2rem;flex-shrink:0;"></i>
                <span><strong>Compte en résiliation</strong> — vous agissez en tant que modérateur.</span>
            </div>
            <?php endif; ?>
            <?php if (\App\Models\Account::typeHasCap($account['type'] ?? '') && (float) ($account['cap'] ?? 0) > 0 && !$isModerator): ?>
            <div class="alert alert-info" style="margin-bottom:1rem;">
                <i class="bi bi-piggy-bank"></i>
                Plafond d'épargne : <strong><?= fmt_amount_smart((float) $account['cap']) ?> <?= e($account['currency']) ?></strong>.
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
                               inputmode="decimal" placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category" class="form-label">Catégorie</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">-- Choisir --</option>
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
                        <input type="text" id="scheduled_at" name="scheduled_at" class="form-control"
                               placeholder="jj/mm/aaaa hh:mm">
                    </div>
                </div>
                <?php
                    $hasCheckbooks = !empty($activeCheckbooks) && \App\Models\Checkbook::typeAllowsCheckbook($account['type'] ?? '');
                    $hasCards      = !empty($txCards);
                    $showPaymentMethod = $hasCheckbooks || $hasCards;
                ?>
                <?php if ($showPaymentMethod): ?>
                <div class="form-group tx-expense-only" id="tx-payment-method-group" style="display:none;">
                    <label class="form-label"><i class="bi bi-wallet2"></i> Moyen de paiement</label>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;padding:0.4rem 0.8rem;border:1px solid var(--gray-light);border-radius:6px;font-size:0.9rem;">
                            <input type="radio" name="payment_method" value="direct" checked
                                   onchange="updatePaymentMethod()"> Aucun (débit direct)
                        </label>
                        <?php if ($hasCards): ?>
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;padding:0.4rem 0.8rem;border:1px solid var(--gray-light);border-radius:6px;font-size:0.9rem;">
                            <input type="radio" name="payment_method" value="card"
                                   onchange="updatePaymentMethod()">
                            <i class="bi bi-credit-card"></i> Carte bancaire
                        </label>
                        <?php endif; ?>
                        <?php if ($hasCheckbooks): ?>
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;padding:0.4rem 0.8rem;border:1px solid var(--gray-light);border-radius:6px;font-size:0.9rem;">
                            <input type="radio" name="payment_method" value="check"
                                   onchange="updatePaymentMethod()">
                            <i class="bi bi-journal-check"></i> Chèque
                        </label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($hasCards): ?>
                <div class="form-group tx-expense-only" id="tx-card-group" style="display:none;">
                    <label for="tx-card" class="form-label">
                        <i class="bi bi-credit-card"></i> Carte bancaire
                        <span class="text-muted" style="font-weight:400;font-size:0.85em;">(optionnel)</span>
                    </label>
                    <select id="tx-card" name="card_id" class="form-control">
                        <option value="">-- Aucune carte --</option>
                        <?php foreach ($txCards as $tc): ?>
                            <option value="<?= (int) $tc['id'] ?>"><?= e(trim(($tc['label'] ? $tc['label'] . ' ' : '') . $tc['masked'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint" id="tx-card-limit-hint" style="display:none;"></span>
                </div>
                <?php endif; ?>

                <?php if ($hasCheckbooks): ?>
                <div class="form-group tx-expense-only" id="tx-check-group" style="display:none;">
                    <label for="tx-checkbook" class="form-label">
                        <i class="bi bi-journal-check"></i> Chéquier
                        <span class="text-muted" style="font-weight:400;font-size:0.85em;">(la dépense sera enregistrée « à venir » jusqu'à confirmation)</span>
                    </label>
                    <select id="tx-checkbook" name="checkbook_id" class="form-control">
                        <option value="">-- Sélectionner un chéquier --</option>
                        <?php foreach ($activeCheckbooks as $cb): ?>
                            <option value="<?= (int) $cb['id'] ?>"><?= e($cb['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top:0.5rem;">
                        <label for="tx-check-payee" class="form-label" style="font-size:0.85em;margin-bottom:0.25rem;">
                            Bénéficiaire du chèque <span class="text-muted" style="font-weight:400;">(optionnel)</span>
                        </label>
                        <input type="text" id="tx-check-payee" name="check_payee" class="form-control"
                               placeholder="Ex : EDF, Mairie de Paris…" maxlength="150">
                    </div>
                    <div class="alert alert-info" style="margin-top:0.6rem;padding:0.5rem 0.75rem;font-size:0.85rem;">
                        <i class="bi bi-info-circle"></i>
                        Le montant sera débité uniquement lorsque vous confirmerez l'encaissement du chèque.
                    </div>
                </div>
                <?php endif; ?>

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

                // Catégories dynamiques par type
                var expenseCategories = <?= json_encode($expenseCategories, JSON_UNESCAPED_UNICODE) ?>;
                var incomeCategories  = <?= json_encode($incomeCategories, JSON_UNESCAPED_UNICODE) ?>;
                var categoryEl = document.getElementById('category');

                function updateCategories() {
                    var cats = typeEl.value === 'income' ? incomeCategories : expenseCategories;
                    var current = categoryEl.value;
                    categoryEl.innerHTML = '<option value="">-- Choisir --</option>';
                    for (var name in cats) {
                        var opt = document.createElement('option');
                        opt.value = name;
                        opt.textContent = cats[name] + ' ' + name;
                        if (name === current) opt.selected = true;
                        categoryEl.appendChild(opt);
                    }
                }
                typeEl.addEventListener('change', updateCategories);
                updateCategories();

                typeEl.addEventListener('change', check);
                amountEl.addEventListener('input', check);

                // Affichage conditionnel des champs réservés aux dépenses
                var expenseOnlyEls = form.querySelectorAll('.tx-expense-only');
                function updateExpenseFields() {
                    var isExpense = typeEl.value === 'expense';
                    expenseOnlyEls.forEach(function (el) {
                        el.style.display = isExpense ? '' : 'none';
                    });
                    // Vider la carte et le chéquier si on bascule vers une entrée
                    if (!isExpense) {
                        var cardSel = document.getElementById('tx-card');
                        if (cardSel) cardSel.value = '';
                        var txHint = document.getElementById('tx-card-limit-hint');
                        if (txHint) { txHint.style.display = 'none'; txHint.innerHTML = ''; }
                        var cbSel = document.getElementById('tx-checkbook');
                        if (cbSel) cbSel.value = '';
                        // Réinitialiser les radios
                        var pmRadios = form.querySelectorAll('[name="payment_method"]');
                        pmRadios.forEach(function(r) { if (r.value === 'direct') r.checked = true; });
                    }
                    // Toujours synchroniser l'affichage carte/chèque avec le radio sélectionné
                    updatePaymentMethod();
                }
                typeEl.addEventListener('change', updateExpenseFields);
                updateExpenseFields();

                // Gestion du moyen de paiement (carte / chèque / direct)
                function updatePaymentMethod() {
                    var selectedMethod = 'direct';
                    var pmRadios = form.querySelectorAll('[name="payment_method"]');
                    pmRadios.forEach(function(r) { if (r.checked) selectedMethod = r.value; });

                    var cardGroup  = document.getElementById('tx-card-group');
                    var checkGroup = document.getElementById('tx-check-group');
                    var cbSel      = document.getElementById('tx-checkbook');
                    var cardSel    = document.getElementById('tx-card');
                    var txHint     = document.getElementById('tx-card-limit-hint');

                    if (cardGroup)  cardGroup.style.display  = (selectedMethod === 'card')  ? '' : 'none';
                    if (checkGroup) checkGroup.style.display = (selectedMethod === 'check') ? '' : 'none';

                    // Vider les champs non actifs
                    if (selectedMethod !== 'card') {
                        if (cardSel) cardSel.value = '';
                        if (txHint) { txHint.style.display = 'none'; txHint.innerHTML = ''; }
                    }
                    if (selectedMethod !== 'check') {
                        if (cbSel) cbSel.value = '';
                    }
                }
                // Appel initial
                updatePaymentMethod();

                <?php if (!empty($txCards)): ?>
                // Hint plafond en temps réel pour le sélecteur carte
                var txCardSel  = document.getElementById('tx-card');
                var txCardHint = document.getElementById('tx-card-limit-hint');
                var txCardsData = <?= json_encode(array_column(
                    array_filter($txCards, fn($c) => $c['monthly_limit'] !== null),
                    null,
                    'id'
                ), JSON_UNESCAPED_UNICODE) ?>;

                function updateTxCardHint() {
                    if (!txCardSel || !txCardHint) return;
                    var cardId = parseInt(txCardSel.value, 10);
                    var amount = parseFloat(amountEl.value) || 0;
                    var card   = txCardsData[cardId];
                    if (!card) { txCardHint.style.display = 'none'; txCardHint.innerHTML = ''; return; }
                    var limit   = parseFloat(card.monthly_limit);
                    var already = parseFloat(card.monthly_total) || 0;
                    var after   = already + amount;
                    var remain  = Math.max(0, limit - already);
                    var fmtC = function(n) {
                        return n.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '\u00a0<?= e($account['currency']) ?>';
                    };
                    if (after > limit) {
                        txCardHint.style.color = 'var(--danger)';
                        txCardHint.innerHTML   = '<i class="bi bi-exclamation-triangle-fill"></i> Plafond dépassé\u00a0: '
                            + fmtC(after) + ' / ' + fmtC(limit) + ' (disponible\u00a0: ' + fmtC(remain) + ').';
                    } else {
                        txCardHint.style.color = after / limit >= 0.8 ? 'var(--warning,#f59e0b)' : 'var(--success,#22c55e)';
                        txCardHint.innerHTML   = '<i class="bi bi-check-circle"></i> Après cette opération\u00a0: '
                            + fmtC(after) + ' / ' + fmtC(limit) + ' (disponible\u00a0: ' + fmtC(Math.max(0, limit - after)) + ').';
                    }
                    txCardHint.style.display = 'block';
                }

                if (txCardSel)  txCardSel.addEventListener('change', updateTxCardHint);
                amountEl.addEventListener('input', function () { if (typeEl.value === 'expense') updateTxCardHint(); });
                typeEl.addEventListener('change', function () { if (typeEl.value !== 'expense') { txCardHint.style.display = 'none'; } });
                <?php endif; ?>
            })();
            </script>
            <?php endif; // fin du bloc conditionnel compte non désactivé (ou modérateur) ?>
        </div>
    </div>

    <!-- ── Opérations modération (modérateurs uniquement) ─────────────── -->
    <?php if ($isModerator): ?>
    <?php
        $modCats = \App\Models\Transaction::MODERATION_CATEGORIES;
    ?>
    <div class="card mb-2" style="border-left:3px solid var(--warning,#f59e0b);">
        <div class="card-header" style="display:flex;align-items:center;gap:0.5rem;">
            <i class="bi bi-shield-lock-fill" style="color:var(--warning,#f59e0b);"></i>
            <h3 style="margin:0;">Opérations modération</h3>
        </div>
        <div class="card-body">
            <p style="font-size:0.83rem;color:var(--text-muted);margin-bottom:0.85rem;">
                Opérations réservées à la modération. Elles ne peuvent pas être modifiées ou supprimées par les utilisateurs.
                Un montant <strong>positif</strong> créditera le compte ; un montant <strong>négatif</strong> le débitera.
            </p>
            <form method="POST"
                  action="/moderation/accounts/<?= (int) $account['id'] ?>/transactions"
                  id="moderation-tx-form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="mod-category" class="form-label">Catégorie</label>
                        <select id="mod-category" name="category" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($modCats as $name => $emoji): ?>
                                <option value="<?= e($name) ?>"><?= e($emoji . ' ' . $name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mod-amount" class="form-label">
                            Montant <span style="font-weight:400;font-size:0.83em;color:var(--text-muted);">(+ crédit, − débit)</span>
                        </label>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="number" id="mod-amount" name="amount"
                                   step="0.01" required
                                   placeholder="ex. -25.00 ou 50.00"
                                   style="flex:1;"
                                   class="form-control"
                                   oninput="modTxPreview()">
                            <span style="font-size:0.85rem;color:var(--text-muted);white-space:nowrap;"><?= e($account['currency']) ?></span>
                        </div>
                        <div id="mod-amount-preview" style="margin-top:0.3rem;font-size:0.82rem;display:none;"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label for="mod-comment" class="form-label">Commentaire <span style="font-weight:400;color:var(--text-muted)">(facultatif)</span></label>
                        <input type="text" id="mod-comment" name="comment"
                               maxlength="255" class="form-control"
                               placeholder="Motif de l'opération">
                    </div>
                </div>
                <button type="submit" class="btn btn-warning btn-block"
                        onclick="return modTxConfirm()">
                    <i class="bi bi-shield-check"></i> Enregistrer l'opération de modération
                </button>
            </form>
        </div>
    </div>
    <script>
    function modTxPreview() {
        var val = parseFloat(document.getElementById('mod-amount').value);
        var previewEl = document.getElementById('mod-amount-preview');
        var currency = '<?= e($account['currency']) ?>';
        if (!isNaN(val) && val !== 0) {
            var isCredit = val > 0;
            previewEl.style.display = '';
            previewEl.style.color = isCredit ? 'var(--success,#22c55e)' : 'var(--danger)';
            previewEl.innerHTML = isCredit
                ? '<i class="bi bi-arrow-up-circle-fill"></i> Crédit de <strong>' + Math.abs(val).toFixed(2).replace('.', ',') + '\u00a0' + currency + '</strong>'
                : '<i class="bi bi-arrow-down-circle-fill"></i> Débit de <strong>' + Math.abs(val).toFixed(2).replace('.', ',') + '\u00a0' + currency + '</strong>';
        } else {
            previewEl.style.display = 'none';
        }
    }
    function modTxConfirm() {
        var val = parseFloat(document.getElementById('mod-amount').value);
        var cat = document.getElementById('mod-category').value;
        if (!cat) { alert('Veuillez choisir une catégorie.'); return false; }
        if (isNaN(val) || val === 0) { alert('Le montant doit être non nul.'); return false; }
        var currency = '<?= e($account['currency']) ?>';
        var dir = val > 0 ? 'crédit' : 'débit';
        return confirm('Enregistrer une opération de modération (' + cat + ') : ' + dir + ' de ' + Math.abs(val).toFixed(2).replace('.', ',') + '\u00a0' + currency + ' ?');
    }
    </script>
    <?php endif; ?>

    <!-- Formulaire débit différé (si activé sur le compte) -->
    <?php if (!empty($deferredDebitEnabled)): ?>
    <div class="card mb-2" style="border-left:3px solid var(--info, #3b82f6);">
        <div class="card-header">
            <h3><i class="bi bi-credit-card"></i> Opération à débit différé</h3>
        </div>
        <div class="card-body">
            <?php if ($isFrozen): ?>
            <div class="alert alert-frozen" style="margin-bottom:0;">
                <i class="bi bi-snow"></i>
                <strong>Compte gelé.</strong> Les opérations sortantes sont bloquées.
            </div>
            <?php elseif ($isDisabled && !$isModerator): ?>
            <div class="alert alert-danger" style="margin-bottom:0;display:flex;align-items:center;gap:0.6rem;">
                <i class="bi bi-slash-circle" style="font-size:1.2rem;flex-shrink:0;"></i>
                <span><strong>Compte en résiliation.</strong> L'enregistrement de nouvelles opérations est désactivé.</span>
            </div>
            <?php else: ?>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.8rem;">
                <i class="bi bi-info-circle"></i>
                Enregistrez une dépense par carte de crédit. Le débit sera effectué à la date de fin de période.
            </p>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/deferred-debits" id="deferred-debit-form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dd-amount" class="form-label">Montant</label>
                        <input type="number" id="dd-amount" name="amount" class="form-control"
                               inputmode="decimal" placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="dd-category" class="form-label">Catégorie</label>
                        <select id="dd-category" name="category" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($expenseCategories as $cat => $emoji): ?>
                                <option value="<?= e($cat) ?>"><?= $emoji ?> <?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dd-comment" class="form-label">Commentaire</label>
                        <input type="text" id="dd-comment" name="comment" class="form-control"
                               placeholder="Ex : Achat en magasin">
                    </div>
                </div>
                <?php
                $cardRequiredSinceDisplay = date('d/m/Y', strtotime($cardRequiredSince));
                ?>
                <?php if (!empty($accountCards)): ?>
                <div class="form-group" id="dd-card-group">
                    <label for="dd-card" class="form-label">
                        <i class="bi bi-credit-card"></i> Carte bancaire associée
                        <span id="dd-card-required-badge" style="color:var(--danger);font-weight:600;"> *</span>
                        <span id="dd-card-optional-hint" class="text-muted" style="font-weight:400;font-size:0.85em;display:none;">(optionnel pour les opérations antérieures au <?= e($cardRequiredSinceDisplay) ?>)</span>
                    </label>
                    <select id="dd-card" name="card_id" class="form-control" required>
                        <option value="">-- Choisir une carte --</option>
                        <?php foreach ($accountCards as $ac): ?>
                            <?php
                            $acLabel = trim(($ac['label'] ? $ac['label'] . ' ' : '') . $ac['masked']);
                            if ($ac['monthly_limit'] !== null) {
                                $acRemaining = max(0.0, $ac['monthly_limit'] - ($ac['monthly_total'] ?? 0));
                                $acSpentFmt  = number_format($ac['monthly_total'] ?? 0, 2, ',', ' ');
                                $acLimitFmt  = number_format($ac['monthly_limit'], 2, ',', ' ');
                                $acRemainFmt = number_format($acRemaining, 2, ',', ' ');
                                $acLimitInfo = " — plafond : {$acSpentFmt} € / {$acLimitFmt} € (reste {$acRemainFmt} €)";
                            } else {
                                $acLimitInfo = '';
                            }
                            ?>
                            <option value="<?= (int) $ac['id'] ?>"><?= e($acLabel . $acLimitInfo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint" id="dd-card-limit-hint" style="display:none;"></span>
                </div>
                <?php else: ?>
                <div class="alert alert-warning" id="dd-no-card-warning" style="display:none;padding:0.6rem 0.9rem;margin-bottom:0.5rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Aucune carte active n'est associée à ce compte. Pour les opérations datant du <strong><?= e($cardRequiredSinceDisplay) ?></strong> ou après, une carte bancaire est obligatoire.
                    <a href="/cards" style="margin-left:0.4em;">Configurer une carte</a>
                </div>
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dd-operation-date" class="form-label">
                            <i class="bi bi-clock"></i> Date de l'opération
                            <span class="text-muted" style="font-weight:400;font-size:0.85em;">(laisser vide pour maintenant)</span>
                        </label>
                        <input type="text" id="dd-operation-date" name="operation_date" class="form-control"
                               placeholder="jj/mm/aaaa hh:mm">
                    </div>
                    <div class="form-group">
                        <label for="dd-period-end" class="form-label">
                            <i class="bi bi-calendar-event"></i> Date de fin de période <span style="color:var(--danger);">*</span>
                        </label>
                        <?php
                        $ddSuggest = '';
                        $ddMinDays = 5; // délai minimum (en jours) avant la date de fin de période
                        if (!empty($deferredDebitDay)) {
                            $day   = (int) $deferredDebitDay;
                            $now   = new DateTime('today');

                            // Construit une date candidate dans le mois donné (en clampant au dernier jour du mois)
                            $buildCandidate = static function (DateTime $monthRef, int $configuredDay): DateTime {
                                $lastDay   = (int) $monthRef->format('t');
                                $actualDay = min($configuredDay, $lastDay);
                                return new DateTime($monthRef->format('Y-m-') . str_pad((string) $actualDay, 2, '0', STR_PAD_LEFT));
                            };

                            // Candidate du mois en cours
                            $candidate = $buildCandidate($now, $day);
                            $daysLeft  = (int) $now->diff($candidate)->format('%r%a');

                            // Si le jour configuré est déjà passé ou trop proche, on bascule au mois suivant
                            if ($daysLeft < $ddMinDays) {
                                $nextMonth = (clone $now)->modify('first day of next month');
                                $candidate = $buildCandidate($nextMonth, $day);
                            }
                            $ddSuggest = $candidate->format('d/m/Y');
                        }
                        ?>
                        <input type="text" id="dd-period-end" name="period_end_date" class="form-control"
                               placeholder="jj/mm/aaaa" required
                               value="<?= e($ddSuggest) ?>">
                        <span class="form-hint">
                            Date à laquelle l'opération sera débitée.
                            Un délai minimum de <strong><?= (int) ($ddMinDays ?? 5) ?> jours</strong> avant cette date est requis.
                            <?php if ($ddSuggest): ?>
                                <br><i class="bi bi-info-circle"></i> Pré-remplie au <strong><?= (int) $deferredDebitDay ?></strong> du mois
                                d'après vos <a href="/accounts/<?= (int) $account['id'] ?>/edit">préférences</a>.
                            <?php else: ?>
                                <br><i class="bi bi-lightbulb"></i> <a href="/accounts/<?= (int) $account['id'] ?>/edit">Définissez votre jour de débit mensuel</a> pour pré-remplir ce champ.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php if ($isModerator): ?>
                <div class="form-group" style="margin-top:0.5rem;">
                    <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.88rem;cursor:pointer;">
                        <input type="checkbox" name="force_override" value="1">
                        <i class="bi bi-shield-exclamation" style="color:var(--warning);"></i>
                        Forcer l'opération même si les fonds sont insuffisants
                    </label>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-credit-card"></i> Enregistrer le débit différé
                </button>
            </form>
            <?php if (!empty($accountCards)): ?>
            <script>
            (function () {
                var cardSelect    = document.getElementById('dd-card');
                var amountInput   = document.getElementById('dd-amount');
                var opDateInput   = document.getElementById('dd-operation-date');
                var hint          = document.getElementById('dd-card-limit-hint');
                var requiredBadge = document.getElementById('dd-card-required-badge');
                var optionalHint  = document.getElementById('dd-card-optional-hint');

                // Seuil : carte obligatoire si operation_date >= cette valeur (sync TransactionController::CARD_REQUIRED_SINCE)
                var CARD_REQUIRED_SINCE = '<?= date('Y-m-d', strtotime($cardRequiredSince)) ?>';

                var cards = <?= json_encode(array_column(
                    array_filter($accountCards, fn($c) => $c['monthly_limit'] !== null),
                    null,
                    'id'
                ), JSON_UNESCAPED_UNICODE) ?>;

                /**
                 * Parse une date saisie en jj/mm/aaaa (hh:mm) → objet Date ou null.
                 * Renvoie null si vide (= maintenant, toujours >= seuil).
                 */
                function parseOpDate(raw) {
                    if (!raw || !raw.trim()) return null; // vide = maintenant
                    var m = raw.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
                    if (!m) return null;
                    return new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
                }

                function isCardRequired() {
                    var raw = opDateInput ? opDateInput.value : '';
                    var opDate = parseOpDate(raw);
                    if (opDate === null) return true; // date vide = maintenant >= seuil
                    var parts = CARD_REQUIRED_SINCE.split('-');
                    var cutoff = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    return opDate >= cutoff;
                }

                function updateCardRequired() {
                    if (!cardSelect) return;
                    var required = isCardRequired();
                    cardSelect.required = required;
                    if (requiredBadge) requiredBadge.style.display = required ? '' : 'none';
                    if (optionalHint)  optionalHint.style.display  = required ? 'none' : '';
                }

                function updateHint() {
                    if (!cardSelect) return;
                    var cardId = parseInt(cardSelect.value, 10);
                    var amount = parseFloat(amountInput ? amountInput.value : 0) || 0;
                    var card   = cards[cardId];
                    if (!card || card.monthly_limit === null) {
                        hint.style.display = 'none';
                        hint.textContent   = '';
                        return;
                    }
                    var limit   = parseFloat(card.monthly_limit);
                    var already = parseFloat(card.monthly_total) || 0;
                    var after   = already + amount;
                    var remain  = Math.max(0, limit - already);
                    var fmt = function(n) {
                        return n.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '\u00a0€';
                    };
                    if (after > limit) {
                        hint.style.color   = 'var(--danger)';
                        hint.innerHTML     = '<i class="bi bi-exclamation-triangle-fill"></i> Plafond dépassé : '
                            + fmt(after) + ' / ' + fmt(limit) + ' (disponible\u00a0: ' + fmt(remain) + ').';
                    } else {
                        hint.style.color   = after / limit >= 0.8 ? 'var(--warning,#f59e0b)' : 'var(--success,#22c55e)';
                        hint.innerHTML     = '<i class="bi bi-check-circle"></i> Après cette opération\u00a0: '
                            + fmt(after) + ' / ' + fmt(limit) + ' (disponible\u00a0: ' + fmt(Math.max(0, limit - after)) + ').';
                    }
                    hint.style.display = 'block';
                }

                if (cardSelect)  cardSelect.addEventListener('change', updateHint);
                if (amountInput) amountInput.addEventListener('input', updateHint);
                if (opDateInput) opDateInput.addEventListener('input', updateCardRequired);

                // Initialisation au chargement
                updateCardRequired();
            })();
            </script>
            <?php else: ?>
            <script>
            (function () {
                // Aucune carte disponible : afficher l'avertissement si la date d'opération >= seuil
                var opDateInput = document.getElementById('dd-operation-date');
                var warning     = document.getElementById('dd-no-card-warning');
                var CARD_REQUIRED_SINCE = '<?= date('Y-m-d', strtotime($cardRequiredSince)) ?>';

                function parseOpDate(raw) {
                    if (!raw || !raw.trim()) return null;
                    var m = raw.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
                    if (!m) return null;
                    return new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
                }

                function updateWarning() {
                    if (!warning) return;
                    var raw    = opDateInput ? opDateInput.value : '';
                    var opDate = parseOpDate(raw);
                    var parts  = CARD_REQUIRED_SINCE.split('-');
                    var cutoff = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    var required = (opDate === null) || (opDate >= cutoff);
                    warning.style.display = required ? '' : 'none';
                }

                if (opDateInput) opDateInput.addEventListener('input', updateWarning);
                updateWarning(); // état initial
            })();
            </script>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

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
<?php $hasUpcoming = !empty($pendingTransactions) || !empty($upcomingDebits) || !empty($pendingDeferredDebits) || !empty($upcomingLoanInstallments); ?>
<?php $hasPendingChecks = !empty($pendingChecks); ?>
<?php $totalUpcomingCount = count($pendingTransactions) + count($upcomingDebits) + count($pendingDeferredDebits ?? []) + count($upcomingLoanInstallments ?? []) + count($pendingChecks ?? []); ?>
<div class="card mt-2" style="border-left: 3px solid var(--warning, #f59e0b);">
    <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;">
        <h3 style="margin:0;"><i class="bi bi-clock" style="color:var(--warning,#f59e0b);"></i> Opérations à venir</h3>
        <?php if ($hasUpcoming || $hasPendingChecks): ?>
            <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;">
                <?= $totalUpcomingCount ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$hasUpcoming && !$hasPendingChecks): ?>
            <div class="empty-state">
                <div class="empty-icon">🕐</div>
                <p>Aucune opération à venir.</p>
            </div>
        <?php else: ?>

            <?php if ($hasPendingChecks): ?>
            <!-- Chèques émis en attente d'encaissement -->
            <h4 style="margin-bottom:0.6rem;font-size:0.95rem;display:flex;align-items:center;gap:0.4rem;">
                <i class="bi bi-journal-check" style="color:var(--warning,#f59e0b);"></i>
                Chèques en attente d'encaissement
                <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;font-size:0.75em;"><?= count($pendingChecks) ?></span>
                <a href="/checkbooks" class="text-small text-muted" style="margin-left:auto;font-weight:400;text-decoration:none;">
                    <i class="bi bi-journal-check"></i> Gérer les chéquiers
                </a>
            </h4>
            <div class="table-responsive" style="margin-bottom:1.25rem;">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Chéquier</th>
                            <th>N° chèque</th>
                            <th>Bénéficiaire</th>
                            <th>Montant</th>
                            <th>Émis le</th>
                            <?php if ($isOwner || $isGuardian || $isModerator): ?>
                            <th style="text-align:right;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingChecks as $pchk): ?>
                    <tr>
                        <td class="text-small"><?= e($pchk['checkbook_label'] ?? '—') ?></td>
                        <td><strong>#<?= (int) $pchk['check_number'] ?></strong></td>
                        <td><?= e($pchk['payee'] ?: '—') ?></td>
                        <td style="font-weight:600;color:var(--warning,#f59e0b);">
                            <?= number_format((float) $pchk['amount'], 2, ',', ' ') ?>&nbsp;<?= e($account['currency']) ?>
                        </td>
                        <td class="text-muted text-small"><?= date('d/m/Y', strtotime($pchk['created_at'])) ?></td>
                        <?php if ($isOwner || $isGuardian || $isModerator): ?>
                        <td style="text-align:right;">
                            <div class="btn-group btn-group-sm">
                                <form method="POST"
                                      action="/accounts/<?= (int) $account['id'] ?>/checks/<?= (int) $pchk['id'] ?>/confirm">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm"
                                            style="color:var(--success,#22c55e);border-color:var(--success,#22c55e);"
                                            onclick="return confirm('Confirmer l\'encaissement du chèque #<?= (int) $pchk['check_number'] ?> (<?= number_format((float) $pchk['amount'], 2, ',', ' ') ?>&nbsp;<?= e($account['currency']) ?>) ? Le débit sera effectué immédiatement.')">
                                        <i class="bi bi-check2-circle"></i> Encaissé
                                    </button>
                                </form>
                                <form method="POST"
                                      action="/accounts/<?= (int) $account['id'] ?>/checks/<?= (int) $pchk['id'] ?>/oppose">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm"
                                            style="color:var(--danger);border-color:var(--danger);"
                                            onclick="return confirm('Mettre en opposition le chèque #<?= (int) $pchk['check_number'] ?> ? Le débit en attente sera annulé.')">
                                        <i class="bi bi-slash-circle"></i> Opposition
                                    </button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

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
                            <?php $tIsTpe = \App\Models\Transaction::isModerationOnly($t); ?>
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
                                    <?= $t['type'] === 'income' ? '+' : '-' ?><?= fmt_amount_smart((float) $t['amount']) ?>
                                </td>
                                <td>
                                    <?php if ($tIsTpe): ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;" title="Opération de modération — non modifiable">
                                        <i class="bi bi-shield-lock"></i> Modération
                                    </span>
                                    <?php elseif ($isModerator && !in_array((int) $t['id'], $linkedTxIds ?? [])): ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/delete"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Supprimer cette opération programmée ?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php elseif ($isModerator): ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;opacity:0.7" title="Liée à un virement ou prélèvement — annuler l'opération parente">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($upcomingDebits)): ?>
            <!-- Prélèvements planifiés (compte débité ou émetteur d'un mandat) -->
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
                            <th>Sens</th>
                            <th>Contrepartie</th>
                            <th>Motif</th>
                            <th class="text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingDebits as $d): ?>
                            <?php
                                $isDue    = strtotime($d['scheduled_at']) <= time();
                                $isCredit = ($d['direction'] ?? 'debit') === 'credit';
                            ?>
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
                                <td>
                                    <?php if ($isCredit): ?>
                                        <span class="badge badge-success" title="Ce compte sera crédité"><i class="bi bi-arrow-down-left"></i> Crédit</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" title="Ce compte sera débité"><i class="bi bi-arrow-up-right"></i> Débit</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($d['counterparty_name'] ?? '—') ?></td>
                                <td><?= e($d['motif'] ?? '—') ?></td>
                                <td class="text-right font-bold <?= $isCredit ? 'text-success' : 'text-danger' ?>">
                                    <?= $isCredit ? '+' : '-' ?><?= fmt_amount_smart((float) $d['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($upcomingLoanInstallments)): ?>
            <!-- Échéances de crédit à venir -->
            <h4 style="margin-bottom:0.6rem;font-size:0.95rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-cash-coin"></i> Mensualités de crédit
                <span class="badge badge-secondary"><?= count($upcomingLoanInstallments) ?></span>
            </h4>
            <div class="table-responsive" style="margin-bottom:1.25rem;">
                <table class="table" id="upcoming-installments-table">
                    <thead>
                        <tr>
                            <th>Date d'échéance</th>
                            <th>Crédit</th>
                            <th class="text-right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingLoanInstallments as $inst): ?>
                        <?php
                            $isDue     = strtotime($inst['due_date']) <= time();
                            $loanTypes = \App\Models\LoanSimulation::getTypes();
                            $typeInfo  = $loanTypes[$inst['loan_type']] ?? null;
                        ?>
                        <tr style="opacity:0.85;font-style:italic;">
                            <td>
                                <?php if ($isDue): ?>
                                    <i class="bi bi-hourglass-split" style="color:var(--danger);"></i>
                                    <strong style="color:var(--danger);"><?= date('d/m/Y', strtotime($inst['due_date'])) ?></strong>
                                    <br><small class="text-danger" style="font-style:normal;">En attente de prélèvement</small>
                                <?php else: ?>
                                    <i class="bi bi-clock" style="color:var(--warning,#f59e0b);"></i>
                                    <?= date('d/m/Y', strtotime($inst['due_date'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($typeInfo): ?>
                                    <i class="bi <?= htmlspecialchars($typeInfo['icon']) ?>"></i>
                                    <?= htmlspecialchars($typeInfo['label']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($inst['loan_type']) ?>
                                <?php endif; ?>
                                <br><small class="text-muted" style="font-style:normal;">Crédit #<?= (int)$inst['loan_id'] ?></small>
                            </td>
                            <td class="text-right font-bold text-danger">
                                -<?= fmt_amount_smart((float)$inst['amount']) ?> €
                                <?php if ((float)($inst['penalty'] ?? 0) > 0): ?>
                                <div style="font-size:0.72rem;font-weight:400;color:var(--danger);margin-top:2px;white-space:nowrap">
                                    <i class="bi bi-exclamation-triangle-fill" style="font-size:0.65rem"></i>
                                    dont <?= fmt_amount_smart((float)$inst['penalty']) ?> € de pénalité de retard
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($pendingDeferredDebits)): ?>
            <!-- Encours — Débits différés -->
            <h4 style="margin-bottom:0.6rem;margin-top:1rem;font-size:0.95rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-credit-card"></i> Encours
                <span class="badge badge-secondary"><?= count($pendingDeferredDebits) ?></span>
                <?php
                    $totalEncours = 0;
                    foreach ($pendingDeferredDebits as $dd) $totalEncours += (float) $dd['amount'];
                ?>
                <span style="font-size:0.8rem;font-weight:400;color:var(--danger);margin-left:0.4rem;">
                    Total : <?= fmt_amount_smart($totalEncours) ?> <?= e($account['currency']) ?>
                </span>
            </h4>
            <?php
                // Regroupement des débits différés par période (mois/année de la fin de période)
                $deferredGroups = [];
                foreach ($pendingDeferredDebits as $dd) {
                    $key = date('Y-m', strtotime($dd['period_end_date']));
                    $deferredGroups[$key][] = $dd;
                }
                ksort($deferredGroups);
                $frenchMonths = [
                    '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
                    '05' => 'Mai',     '06' => 'Juin',    '07' => 'Juillet', '08' => 'Août',
                    '09' => 'Septembre','10' => 'Octobre','11' => 'Novembre','12' => 'Décembre',
                ];
            ?>
            <?php foreach ($deferredGroups as $groupKey => $groupItems): ?>
                <?php
                    [$gYear, $gMonth] = explode('-', $groupKey);
                    $groupLabel = $frenchMonths[$gMonth] . ' ' . $gYear;
                    $groupTotal = 0;
                    foreach ($groupItems as $gi) $groupTotal += (float) $gi['amount'];
                ?>
                <h5 style="margin:0.75rem 0 0.4rem;font-size:0.85rem;color:var(--text-muted);font-weight:600;display:flex;align-items:center;gap:0.4rem;">
                    <i class="bi bi-calendar3"></i>
                    <span><?= e($groupLabel) ?></span>
                    <span class="badge badge-secondary"><?= count($groupItems) ?></span>
                    <span style="font-weight:400;color:var(--danger);">
                        Total : <?= fmt_amount_smart($groupTotal) ?> <?= e($account['currency']) ?>
                    </span>
                </h5>
            <div class="table-responsive" style="margin-bottom:1.25rem;">
                <table class="table dd-table" id="deferred-debits-table-<?= e($groupKey) ?>">
                    <thead>
                        <tr>
                            <th>Date opération</th>
                            <th>Fin de période</th>
                            <th>Catégorie</th>
                            <th>Par</th>
                            <th>Commentaire</th>
                            <th class="text-right">Montant</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupItems as $dd): ?>
                            <?php
                                $isDue = $dd['period_end_date'] <= date('Y-m-d');
                                $periodEndTs = strtotime($dd['period_end_date'] ?? '');
                                $ddLocked = $periodEndTs && (time() - $periodEndTs) > 7 * 86400;
                                $ddIsTpe  = str_starts_with((string) ($dd['comment'] ?? ''), '[TPE');
                                // Les opérations TPE ne sont modifiables que par les modérateurs.
                                if ($ddIsTpe && !is_moderator()) { $ddLocked = true; }
                            ?>
                            <tr style="opacity:0.85;font-style:italic;">
                                <td>
                                    <?php if ($ddLocked): ?>
                                        <i class="bi bi-credit-card" style="color:var(--info,#3b82f6);"></i>
                                        <?= e(date('d/m/Y H:i', strtotime($dd['operation_date']))) ?>
                                    <?php else: ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/deferred-debits/<?= (int) $dd['id'] ?>/edit"
                                          style="display:flex;align-items:center;gap:0.3rem;">
                                        <?= csrf_field() ?>
                                        <i class="bi bi-credit-card" style="color:var(--info,#3b82f6);flex-shrink:0;"></i>
                                        <input type="text" name="operation_date"
                                               value="<?= e(date('d/m/Y H:i', strtotime($dd['operation_date']))) ?>"
                                               placeholder="jj/mm/aaaa hh:mm"
                                               style="font-size:0.78rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:125px">
                                        <button type="submit" class="btn btn-outline btn-sm" style="padding:0.15rem 0.35rem;font-size:0.72rem;" title="Modifier la date d'opération">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ddLocked): ?>
                                        <i class="bi bi-lock" style="color:var(--text-muted);"></i>
                                        <?= e(date('d/m/Y', strtotime($dd['period_end_date']))) ?>
                                        <?php if ($ddIsTpe): ?>
                                            <br><small class="text-muted" style="font-style:normal;" title="Opération TPE — gérée par la modération">Gérée par la modération</small>
                                        <?php else: ?>
                                            <br><small class="text-muted" style="font-style:normal;" title="Verrouill&eacute; : plus de 7 jours apr&egrave;s la fin de p&eacute;riode">Verrouillé</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/deferred-debits/<?= (int) $dd['id'] ?>/edit"
                                          style="display:flex;align-items:center;gap:0.3rem;">
                                        <?= csrf_field() ?>
                                        <?php if ($isDue): ?>
                                            <i class="bi bi-hourglass-split" style="color:var(--danger);flex-shrink:0;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-clock" style="color:var(--warning,#f59e0b);flex-shrink:0;"></i>
                                        <?php endif; ?>
                                        <input type="text" name="period_end_date"
                                               value="<?= e(date('d/m/Y', strtotime($dd['period_end_date']))) ?>"
                                               placeholder="jj/mm/aaaa"
                                               style="font-size:0.78rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:95px">
                                        <button type="submit" class="btn btn-outline btn-sm" style="padding:0.15rem 0.35rem;font-size:0.72rem;" title="Modifier la date de fin de période">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <?php if ($isDue): ?>
                                            <br><small class="text-danger" style="font-style:normal;">En attente</small>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($dd['category']) ?></td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <i class="bi bi-person"></i> <?= e($dd['author_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($dd['card_id'])): ?>
                                        <i class="bi bi-credit-card-2-front" style="color:var(--info,#3b82f6);" title="Carte associée"></i>
                                    <?php endif; ?>
                                    <?= e($dd['comment'] ?? '') ?: '<span style="color:var(--text-muted)">—</span>' ?>
                                </td>
                                <td class="text-right font-bold text-danger">
                                    -<?= fmt_amount_smart((float) $dd['amount']) ?>
                                </td>
                                <td>
                                    <?php if ($ddIsTpe): ?>
                                    <a href="/moderation/pos-payments" class="badge badge-secondary" style="font-size:0.7rem;text-decoration:none;" title="Débit différé issu d'un paiement TPE — annulation réservée à la page de modération des paiements TPE">
                                        <i class="bi bi-shield-lock"></i> TPE
                                    </a>
                                    <?php elseif (!$ddLocked): ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/deferred-debits/<?= (int) $dd['id'] ?>/cancel"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Annuler cette opération à débit différé ?')"
                                                title="Annuler">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
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
        <?php if (!empty($executedDeferredDebits)): ?>
        <!-- Débits différés exécutés (modifiables) -->
        <h4 style="margin-bottom:0.6rem;font-size:0.95rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
            <i class="bi bi-credit-card-2-back"></i> Débits différés exécutés
            <span class="badge badge-secondary"><?= count($executedDeferredDebits) ?></span>
        </h4>
        <div class="table-responsive" style="margin-bottom:1.25rem;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date opération</th>
                        <th>Fin de période</th>
                        <th>Catégorie</th>
                        <th>Par</th>
                        <th>Commentaire</th>
                        <th class="text-right">Montant</th>
                        <th>Exécuté le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($executedDeferredDebits as $dd): ?>
                        <?php
                            $periodEndTs = strtotime($dd['period_end_date'] ?? '');
                            $ddLocked = $periodEndTs && (time() - $periodEndTs) > 7 * 86400;
                            $ddIsTpe  = str_starts_with((string) ($dd['comment'] ?? ''), '[TPE');
                            // Les opérations TPE ne sont modifiables que par les modérateurs.
                            if ($ddIsTpe && !is_moderator()) { $ddLocked = true; }
                        ?>
                        <tr>
                            <td>
                                <?php if ($ddLocked): ?>
                                    <i class="bi bi-credit-card-2-back" style="color:var(--success,#22c55e);"></i>
                                    <?= e(date('d/m/Y H:i', strtotime($dd['operation_date']))) ?>
                                <?php else: ?>
                                <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/deferred-debits/<?= (int) $dd['id'] ?>/edit"
                                      style="display:flex;align-items:center;gap:0.3rem;">
                                    <?= csrf_field() ?>
                                    <i class="bi bi-credit-card-2-back" style="color:var(--success,#22c55e);flex-shrink:0;"></i>
                                    <input type="text" name="operation_date"
                                           value="<?= e(date('d/m/Y H:i', strtotime($dd['operation_date']))) ?>"
                                           placeholder="jj/mm/aaaa hh:mm"
                                           style="font-size:0.78rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:125px">
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding:0.15rem 0.35rem;font-size:0.72rem;" title="Modifier la date d'opération">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ddLocked): ?>
                                    <i class="bi bi-lock" style="color:var(--text-muted);"></i>
                                    <?= e(date('d/m/Y', strtotime($dd['period_end_date']))) ?>
                                    <?php if ($ddIsTpe): ?>
                                        <br><small class="text-muted" title="Opération TPE — gérée par la modération">Gérée par la modération</small>
                                    <?php else: ?>
                                        <br><small class="text-muted" title="Verrouill&eacute; : plus de 7 jours apr&egrave;s la fin de p&eacute;riode">Verrouillé</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/deferred-debits/<?= (int) $dd['id'] ?>/edit"
                                      style="display:flex;align-items:center;gap:0.3rem;">
                                    <?= csrf_field() ?>
                                    <i class="bi bi-check-circle" style="color:var(--success,#22c55e);flex-shrink:0;"></i>
                                    <input type="text" name="period_end_date"
                                           value="<?= e(date('d/m/Y', strtotime($dd['period_end_date']))) ?>"
                                           placeholder="jj/mm/aaaa"
                                           style="font-size:0.78rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:95px">
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding:0.15rem 0.35rem;font-size:0.72rem;" title="Modifier la date de fin de période">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td><?= e($dd['category']) ?></td>
                            <td>
                                <span class="badge badge-secondary">
                                    <i class="bi bi-person"></i> <?= e($dd['author_name']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($dd['card_id'])): ?>
                                    <i class="bi bi-credit-card-2-front" style="color:var(--info,#3b82f6);" title="Carte associée"></i>
                                <?php endif; ?>
                                <?= e($dd['comment'] ?? '') ?: '<span style="color:var(--text-muted)">—</span>' ?>
                            </td>
                            <td class="text-right font-bold text-danger">
                                -<?= fmt_amount_smart((float) $dd['amount']) ?>
                            </td>
                            <td style="font-size:0.8rem;color:var(--text-muted);">
                                <?= $dd['executed_at'] ? date('d/m/Y H:i', strtotime($dd['executed_at'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($executedTransactions) && empty($executedDeferredDebits)): ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>Aucune opération effectuée pour le moment.</p>
            </div>
        <?php else: ?>
        <?php if (!empty($executedTransactions)): ?>
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
                <div class="form-group" style="margin:0;min-width:90px;">
                    <label class="form-label" style="font-size:0.8rem;">Par page</label>
                    <select id="tx-per-page" class="form-control form-control-sm">
                        <option value="10"  <?= $txPerPage === 10  ? 'selected' : '' ?>>10</option>
                        <option value="25"  <?= $txPerPage === 25  ? 'selected' : '' ?>>25</option>
                        <option value="50"  <?= $txPerPage === 50  ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $txPerPage === 100 ? 'selected' : '' ?>>100</option>
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
                        <?php
                        // Récupère en une seule requête les transactions déjà réparties sur cette page
                        $__splitModel  = new \App\Models\ExpenseSplit();
                        $__txIdsPage   = array_column($executedTransactions, 'id');
                        $__splitTxIds  = array_flip($__splitModel->getSplitTransactionIds($__txIdsPage));
                        ?>
                        <?php foreach ($executedTransactions as $t):
                            $txIsLinked  = in_array((int) $t['id'], $linkedTxIds ?? []);
                            $txIsTpe     = \App\Models\Transaction::isModerationOnly($t);
                            $txIsAlreadySplit = isset($__splitTxIds[(int) $t['id']]);
                            $txAge       = time() - strtotime($t['created_at']);
                            $txEditable  = !$txIsLinked && !$txIsTpe && ($isModerator || $txAge <= 7 * 86400);
                            $txHasScheduled = !empty($t['scheduled_at']);
                        ?>
                            <tr data-type="<?= e($t['type']) ?>"
                                data-amount="<?= e((string) (float) $t['amount']) ?>"
                                data-author="<?= e($t['author_name']) ?>">
                                <td>
                                    <?php if ($txEditable): ?>
                                        <span class="tx-date-display-<?= (int) $t['id'] ?>">
                                            <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                                        </span>
                                        <form method="POST"
                                              action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/edit"
                                              class="tx-date-form-<?= (int) $t['id'] ?>" style="display:none;">
                                            <?= csrf_field() ?>
                                            <div style="display:flex;flex-direction:column;gap:0.3rem;">
                                                <label style="font-size:0.72rem;color:var(--text-muted);margin:0;">Enregistrement</label>
                                                <input type="text" name="created_at" class="form-control form-control-sm"
                                                       placeholder="jj/mm/aaaa hh:mm"
                                                       value="<?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>"
                                                       style="width:145px;font-size:0.82rem;">
                                                <?php if ($txHasScheduled): ?>
                                                    <label style="font-size:0.72rem;color:var(--text-muted);margin:0;">Exécution</label>
                                                    <input type="text" name="scheduled_at" class="form-control form-control-sm"
                                                           placeholder="jj/mm/aaaa hh:mm"
                                                           value="<?= date('d/m/Y H:i', strtotime($t['scheduled_at'])) ?>"
                                                           style="width:145px;font-size:0.82rem;">
                                                <?php endif; ?>
                                                <div style="display:flex;gap:0.3rem;margin-top:0.15rem;">
                                                    <button type="submit" class="btn btn-primary btn-sm" style="padding:0.15rem 0.5rem;font-size:0.78rem;">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline btn-sm tx-date-cancel" data-tx-id="<?= (int) $t['id'] ?>"
                                                            style="padding:0.15rem 0.5rem;font-size:0.78rem;">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                                    <?php endif; ?>
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
                                    <?= $t['type'] === 'income' ? '+' : '-' ?><?= fmt_amount_smart((float) $t['amount']) ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?php if ($t['type'] === 'expense' && !$txIsTpe && empty($t['pending'])): ?>
                                    <button type="button"
                                            class="btn btn-outline btn-sm"
                                            style="padding:0.15rem 0.4rem;font-size:0.82rem;<?= $txIsAlreadySplit ? 'opacity:0.45;' : '' ?>"
                                            title="<?= $txIsAlreadySplit ? 'Dépense déjà répartie' : 'Répartir cette dépense entre amis' ?>"
                                            <?php if (!$txIsAlreadySplit): ?>
                                            onclick="openSplitModal(<?= (int) $t['id'] ?>, '<?= e(addslashes($t['comment'] ?? '')) ?>', <?= (float) $t['amount'] ?>, '<?= e($account['currency']) ?>')"
                                            <?php else: ?>
                                            disabled
                                            <?php endif; ?>
                                            >
                                        <i class="bi bi-<?= $txIsAlreadySplit ? 'people-fill' : 'people' ?>"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($t['type'] === 'expense' && !$txIsTpe): ?>
                                    <form method="POST"
                                          action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/toggle-budget-exclusion"
                                          style="display:inline;">
                                        <?= csrf_field() ?>
                                        <?php if (!empty($t['excluded_from_budget'])): ?>
                                        <button type="submit" class="btn btn-outline btn-sm"
                                                title="Exclue du budget — cliquer pour réintégrer"
                                                style="padding:0.15rem 0.4rem;font-size:0.82rem;opacity:0.55;color:var(--danger);border-color:var(--danger);">
                                            <i class="bi bi-pie-chart"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" class="btn btn-outline btn-sm"
                                                title="Incluse dans le budget — cliquer pour exclure"
                                                style="padding:0.15rem 0.4rem;font-size:0.82rem;">
                                            <i class="bi bi-pie-chart-fill"></i>
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($txIsTpe): ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;" title="Opération de modération — non modifiable">
                                        <i class="bi bi-shield-lock"></i> Modération
                                    </span>
                                    <?php else: ?>
                                    <?php if ($txEditable): ?>
                                    <button type="button" class="btn btn-outline btn-sm tx-date-edit" data-tx-id="<?= (int) $t['id'] ?>"
                                            title="Modifier les dates" style="padding:0.15rem 0.4rem;font-size:0.82rem;">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($isModerator && !$txIsLinked): ?>
                                    <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transactions/<?= (int) $t['id'] ?>/delete"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Supprimer cette opération ?')"
                                                style="padding:0.15rem 0.4rem;font-size:0.82rem;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php elseif ($txIsLinked): ?>
                                    <span class="badge badge-secondary" style="font-size:0.7rem;opacity:0.7" title="Liée à un virement ou prélèvement — annuler l'opération parente">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <?php endif; ?>
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
            <!-- Navigation de pagination -->
            <?php
                $txBaseUrl  = '/accounts/' . (int) $account['id'] . '?per_page=' . $txPerPage . '&page=';
                $txWinStart = max(1, $txPage - 2);
                $txWinEnd   = min($txTotalPages, $txPage + 2);
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.75rem;flex-wrap:wrap;gap:0.5rem;">
                <p class="text-muted" style="margin:0;font-size:0.82rem;">
                    <?php if ($txTotalCount > 0): ?>
                        <?= number_format((($txPage - 1) * $txPerPage) + 1) ?>–<?= number_format(min($txPage * $txPerPage, $txTotalCount)) ?>
                        sur <?= number_format($txTotalCount) ?> opération<?= $txTotalCount > 1 ? 's' : '' ?>
                    <?php endif; ?>
                </p>
                <?php if ($txTotalPages > 1): ?>
                <div style="display:flex;gap:0.3rem;flex-wrap:wrap;align-items:center;">
                    <?php if ($txPage > 1): ?>
                        <a href="<?= e($txBaseUrl . 1) ?>" class="btn btn-outline btn-sm" title="Première"><i class="bi bi-chevron-double-left"></i></a>
                        <a href="<?= e($txBaseUrl . ($txPage - 1)) ?>" class="btn btn-outline btn-sm"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php if ($txWinStart > 1): ?>
                        <a href="<?= e($txBaseUrl . 1) ?>" class="btn btn-outline btn-sm">1</a>
                        <?php if ($txWinStart > 2): ?><span class="btn btn-sm" style="pointer-events:none;opacity:0.45">…</span><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($p = $txWinStart; $p <= $txWinEnd; $p++): ?>
                        <?php if ($p === $txPage): ?>
                            <span class="btn btn-primary btn-sm"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= e($txBaseUrl . $p) ?>" class="btn btn-outline btn-sm"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($txWinEnd < $txTotalPages): ?>
                        <?php if ($txWinEnd < $txTotalPages - 1): ?><span class="btn btn-sm" style="pointer-events:none;opacity:0.45">…</span><?php endif; ?>
                        <a href="<?= e($txBaseUrl . $txTotalPages) ?>" class="btn btn-outline btn-sm"><?= $txTotalPages ?></a>
                    <?php endif; ?>
                    <?php if ($txPage < $txTotalPages): ?>
                        <a href="<?= e($txBaseUrl . ($txPage + 1)) ?>" class="btn btn-outline btn-sm"><i class="bi bi-chevron-right"></i></a>
                        <a href="<?= e($txBaseUrl . $txTotalPages) ?>" class="btn btn-outline btn-sm" title="Dernière"><i class="bi bi-chevron-double-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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

                    countEl.textContent = visible < rows.length
                        ? visible + ' / ' + rows.length + ' sur cette page'
                        : '';
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

                var perPageEl = document.getElementById('tx-per-page');
                if (perPageEl) {
                    perPageEl.addEventListener('change', function () {
                        window.location.href = '/accounts/<?= (int) $account['id'] ?>?per_page=' + this.value + '&page=1';
                    });
                }

                applyFilters();

                // Toggle inline date editing per transaction
                document.querySelectorAll('.tx-date-edit').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var txId = this.dataset.txId;
                        document.querySelector('.tx-date-display-' + txId).style.display = 'none';
                        var form = document.querySelector('.tx-date-form-' + txId);
                        form.style.display = '';
                        if (typeof initFlatpickrs === 'function') initFlatpickrs();
                    });
                });
                document.querySelectorAll('.tx-date-cancel').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var txId = this.dataset.txId;
                        document.querySelector('.tx-date-display-' + txId).style.display = '';
                        document.querySelector('.tx-date-form-' + txId).style.display = 'none';
                    });
                });
            })();
            </script>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php /* Section "Mandats" supprimée — les prélèvements liés aux mandats sont désormais affichés dans "Opérations à venir > Prélèvements planifiés". */ ?>

<!-- ── Modale de répartition de dépense ───────────────────────────────── -->
<div id="split-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center;">
    <div class="card" style="max-width:540px;width:100%;margin:1rem;max-height:90vh;display:flex;flex-direction:column;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h3 style="margin:0;"><i class="bi bi-people-fill"></i> Répartir la dépense</h3>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeSplitModal()" style="padding:0.2rem 0.5rem;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="card-body" style="overflow-y:auto;">
            <p id="split-tx-label" style="font-size:0.88rem;color:var(--text-muted);margin-bottom:0.75rem;"></p>

            <!-- Résumé montants -->
            <div style="display:flex;gap:0.75rem;margin-bottom:1.1rem;flex-wrap:wrap;">
                <div style="background:var(--bg-secondary,#f3f4f6);border-radius:var(--border-radius-sm);padding:0.45rem 0.85rem;flex:1;min-width:100px;">
                    <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Total</div>
                    <div style="font-weight:700;font-size:0.95rem;" id="split-total-amount"></div>
                </div>
                <div style="background:var(--bg-secondary,#f3f4f6);border-radius:var(--border-radius-sm);padding:0.45rem 0.85rem;flex:1;min-width:100px;">
                    <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Réparti</div>
                    <div style="font-weight:700;font-size:0.95rem;" id="split-assigned-amount"></div>
                </div>
                <div style="background:var(--bg-secondary,#f3f4f6);border-radius:var(--border-radius-sm);padding:0.45rem 0.85rem;flex:1;min-width:100px;">
                    <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">Restant</div>
                    <div style="font-weight:700;font-size:0.95rem;" id="split-remaining-amount"></div>
                </div>
            </div>

            <!-- Champ de recherche d'amis -->
            <div style="position:relative;margin-bottom:1rem;">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.3rem;">
                    <i class="bi bi-person-plus"></i> Ajouter un participant
                </label>
                <input type="text"
                       id="split-search-input"
                       class="form-control"
                       placeholder="Rechercher un ami…"
                       autocomplete="off"
                       style="padding-right:2.2rem;">
                <div id="split-search-dropdown"
                     style="display:none;position:absolute;z-index:200;background:#fff;border:1px solid var(--gray-light);border-radius:var(--border-radius-sm);width:100%;box-shadow:var(--shadow);max-height:200px;overflow-y:auto;">
                </div>
                <div id="split-search-hint" style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem;display:none;">
                    Cet utilisateur n'est pas dans vos amis. <a href="/friends">Ajouter des amis</a>.
                </div>
            </div>

            <!-- Liste des participants sélectionnés -->
            <form id="split-form" method="POST" action="">
                <?= csrf_field() ?>
                <div id="split-participants-list" style="margin-bottom:0.5rem;"></div>

                <div id="split-empty-hint" style="text-align:center;color:var(--text-muted);font-size:0.85rem;padding:0.75rem 0;">
                    <i class="bi bi-people"></i> Aucun participant ajouté.
                </div>

                <div id="split-error" style="display:none;padding:0.5rem 0.75rem;background:rgba(239,71,111,0.1);border-radius:var(--border-radius-sm);color:var(--danger);font-size:0.85rem;margin-top:0.5rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="split-error-msg"></span>
                </div>

                <div style="display:flex;gap:0.75rem;margin-top:1.1rem;">
                    <button type="submit" class="btn btn-primary" id="split-submit-btn" disabled>
                        <i class="bi bi-send"></i> Envoyer les demandes
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeSplitModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal      = document.getElementById('split-modal');
    var form       = document.getElementById('split-form');
    var listEl     = document.getElementById('split-participants-list');
    var emptyHint  = document.getElementById('split-empty-hint');
    var errorEl    = document.getElementById('split-error');
    var errorMsg   = document.getElementById('split-error-msg');
    var submitBtn  = document.getElementById('split-submit-btn');
    var txLabel    = document.getElementById('split-tx-label');
    var totalAmtEl = document.getElementById('split-total-amount');
    var assignedEl = document.getElementById('split-assigned-amount');
    var remainEl   = document.getElementById('split-remaining-amount');
    var searchInput    = document.getElementById('split-search-input');
    var searchDropdown = document.getElementById('split-search-dropdown');
    var searchHint     = document.getElementById('split-search-hint');

    var currentTxAmount = 0;
    var currency        = '';
    // Map userId → {username, index} pour les participants déjà ajoutés
    var selectedUsers   = {};
    var participantIndex = 0;

    function fmt(n) {
        return n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '\u00a0' + currency;
    }

    function updateTotals() {
        var inputs = listEl.querySelectorAll('input[type="number"]');
        var total  = 0;
        inputs.forEach(function (inp) { total += parseFloat(inp.value) || 0; });
        total = Math.round(total * 100) / 100;
        assignedEl.textContent = fmt(total);
        assignedEl.style.color = total > currentTxAmount + 0.001 ? 'var(--danger)' : 'var(--primary)';
        var remaining = Math.round((currentTxAmount - total) * 100) / 100;
        remainEl.textContent = fmt(remaining);
        remainEl.style.color = remaining < -0.001 ? 'var(--danger)' : (Math.abs(remaining) < 0.001 ? 'var(--success)' : '');
        // Activer le bouton uniquement si au moins un participant avec montant
        var hasParticipant = listEl.querySelectorAll('.split-participant-row').length > 0;
        submitBtn.disabled = !hasParticipant;
    }

    function addParticipant(userId, username) {
        if (selectedUsers[userId]) return; // déjà ajouté

        var idx = participantIndex++;
        selectedUsers[userId] = {username: username, index: idx};

        var row = document.createElement('div');
        row.className = 'split-participant-row';
        row.dataset.userId = userId;
        row.style.cssText = 'display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;padding:0.45rem 0.6rem;background:var(--bg-secondary,#f3f4f6);border-radius:var(--border-radius-sm);';

        // Avatar/icône
        var icon = document.createElement('span');
        icon.innerHTML = '<i class="bi bi-person-circle" style="font-size:1.15rem;color:var(--primary);flex-shrink:0;"></i>';

        // Nom
        var nameSpan = document.createElement('span');
        nameSpan.style.cssText = 'font-weight:600;flex:1;font-size:0.9rem;';
        nameSpan.textContent = username;

        // Input montant
        var amtInput = document.createElement('input');
        amtInput.type        = 'number';
        amtInput.name        = 'participants[' + idx + '][amount]';
        amtInput.min         = '0.01';
        amtInput.step        = '0.01';
        amtInput.placeholder = '0,00';
        amtInput.className   = 'form-control';
        amtInput.style.cssText = 'width:110px;text-align:right;';
        amtInput.addEventListener('input', updateTotals);

        // Devise
        var curSpan = document.createElement('span');
        curSpan.style.cssText = 'color:var(--text-muted);font-size:0.83rem;flex-shrink:0;';
        curSpan.textContent = currency;

        // Champ caché user_id
        var hiddenUid = document.createElement('input');
        hiddenUid.type  = 'hidden';
        hiddenUid.name  = 'participants[' + idx + '][user_id]';
        hiddenUid.value = userId;

        // Bouton supprimer
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-outline btn-sm';
        removeBtn.style.cssText = 'padding:0.15rem 0.4rem;flex-shrink:0;color:var(--danger);border-color:var(--danger);';
        removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        removeBtn.title = 'Retirer';
        removeBtn.addEventListener('click', function () {
            delete selectedUsers[userId];
            row.remove();
            emptyHint.style.display = listEl.querySelectorAll('.split-participant-row').length === 0 ? '' : 'none';
            updateTotals();
        });

        row.appendChild(icon);
        row.appendChild(nameSpan);
        row.appendChild(amtInput);
        row.appendChild(curSpan);
        row.appendChild(hiddenUid);
        row.appendChild(removeBtn);
        listEl.appendChild(row);

        emptyHint.style.display = 'none';
        amtInput.focus();
        updateTotals();
    }

    // ── Autocomplete ──────────────────────────────────────────────────────

    var searchTimer = null;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchHint.style.display = 'none';
        var q = searchInput.value.trim();
        if (q.length < 2) { searchDropdown.style.display = 'none'; return; }

        searchTimer = setTimeout(function () {
            fetch('/friends/search-users?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (users) {
                    searchDropdown.innerHTML = '';
                    var friends = users.filter(function (u) { return u.is_friend; });

                    if (!users.length || !friends.length) {
                        if (users.length && !friends.length) {
                            // L'utilisateur existe mais n'est pas ami
                            searchHint.style.display = '';
                        }
                        searchDropdown.style.display = 'none';
                        return;
                    }

                    friends.forEach(function (u) {
                        var item = document.createElement('div');
                        var alreadyAdded = !!selectedUsers[u.id];
                        item.style.cssText = 'padding:0.45rem 0.75rem;cursor:pointer;display:flex;align-items:center;gap:0.5rem;'
                            + (alreadyAdded ? 'opacity:0.45;' : '');
                        item.innerHTML = '<i class="bi bi-person-check" style="color:var(--success);"></i><span style="flex:1;">' + u.username + '</span>'
                            + (alreadyAdded ? '<span style="font-size:0.75em;color:var(--text-muted);">Déjà ajouté</span>' : '');

                        if (!alreadyAdded) {
                            item.addEventListener('mouseenter', function () { item.style.background = 'var(--gray-lighter,#f3f4f6)'; });
                            item.addEventListener('mouseleave', function () { item.style.background = ''; });
                            item.addEventListener('mousedown', function (e) {
                                e.preventDefault(); // évite le blur sur searchInput
                                addParticipant(u.id, u.username);
                                searchInput.value = '';
                                searchDropdown.style.display = 'none';
                                searchHint.style.display = 'none';
                            });
                        }
                        searchDropdown.appendChild(item);
                    });
                    searchDropdown.style.display = 'block';
                });
        }, 220);
    });

    searchInput.addEventListener('blur', function () {
        setTimeout(function () { searchDropdown.style.display = 'none'; }, 150);
    });

    // ── Ouverture / fermeture ─────────────────────────────────────────────

    window.openSplitModal = function (txId, comment, amount, cur) {
        currentTxAmount  = amount;
        currency         = cur;
        selectedUsers    = {};
        participantIndex = 0;

        txLabel.textContent    = comment ? '\u00ab\u00a0' + comment + '\u00a0\u00bb' : 'Op\u00e9ration #' + txId;
        totalAmtEl.textContent = fmt(amount);
        assignedEl.textContent = fmt(0);
        assignedEl.style.color = 'var(--primary)';
        remainEl.textContent   = fmt(amount);
        remainEl.style.color   = '';

        form.action           = '/accounts/<?= (int) $account['id'] ?>/transactions/' + txId + '/split';
        listEl.innerHTML      = '';
        emptyHint.style.display   = '';
        errorEl.style.display     = 'none';
        searchInput.value         = '';
        searchDropdown.style.display = 'none';
        searchHint.style.display  = 'none';
        submitBtn.disabled        = true;
        submitBtn.innerHTML       = '<i class="bi bi-send"></i> Envoyer les demandes';

        modal.style.display = 'flex';
        setTimeout(function () { searchInput.focus(); }, 80);
    };

    window.closeSplitModal = function () {
        modal.style.display = 'none';
    };

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeSplitModal();
    });

    // ── Validation à la soumission ────────────────────────────────────────

    form.addEventListener('submit', function (e) {
        var rows = listEl.querySelectorAll('.split-participant-row');
        if (!rows.length) {
            e.preventDefault();
            errorMsg.textContent  = 'Veuillez ajouter au moins un participant.';
            errorEl.style.display = '';
            return;
        }
        var total = 0;
        rows.forEach(function (row) {
            var inp = row.querySelector('input[type="number"]');
            total  += parseFloat(inp ? inp.value : 0) || 0;
        });
        total = Math.round(total * 100) / 100;
        if (total <= 0) {
            e.preventDefault();
            errorMsg.textContent  = 'Veuillez saisir au moins un montant.';
            errorEl.style.display = '';
            return;
        }
        if (total > Math.round((currentTxAmount + 0.001) * 100) / 100) {
            e.preventDefault();
            errorMsg.textContent  = 'Le total (' + fmt(total) + ') d\u00e9passe le montant de la d\u00e9pense (' + fmt(currentTxAmount) + ').';
            errorEl.style.display = '';
            return;
        }
        errorEl.style.display = 'none';
        submitBtn.disabled    = true;
        submitBtn.innerHTML   = '<i class="bi bi-hourglass-split"></i> Envoi\u2026';
    });
})();
</script>

<?php if (($account['type'] ?? '') === 'pro' && isset($posPayments)): ?>
<!-- ======================================================= -->
<!-- Section : Encaissements TPE                             -->
<!-- ======================================================= -->
<div class="card mt-2" id="pos-payments-section" style="border-left:3px solid var(--success,#22c55e);">
    <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
        <h3 style="margin:0;"><i class="bi bi-credit-card-2-front" style="color:var(--success,#22c55e);"></i> Encaissements TPE</h3>
        <?php
            $totalNet  = 0.0;
            $cntOk     = 0;
            $cntCancel = 0;
            foreach ($posPayments as $_pp) {
                if (!empty($_pp['cancelled_at'])) { $cntCancel++; continue; }
                $totalNet += (float) $_pp['net_amount'];
                $cntOk++;
            }
        ?>
        <?php if ($cntOk > 0): ?>
            <span class="badge" style="background:var(--success,#22c55e);color:#fff;"><?= $cntOk ?> encaissement<?= $cntOk > 1 ? 's' : '' ?></span>
        <?php endif; ?>
        <?php if ($cntCancel > 0): ?>
            <span class="badge bg-secondary"><?= $cntCancel ?> annulé<?= $cntCancel > 1 ? 's' : '' ?></span>
        <?php endif; ?>
        <?php if ($isModerator): ?>
            <a href="/moderation/pos-payments?merchant=<?= (int) $account['id'] ?>"
               class="btn btn-outline btn-sm" style="margin-left:auto;font-size:0.82rem;"
               title="Gérer les encaissements depuis la modération">
                <i class="bi bi-shield-check"></i> Modération TPE
            </a>
        <?php else: ?>
            <span style="margin-left:auto;font-size:0.9rem;font-weight:600;color:var(--success,#22c55e);">
                Net encaissé : <?= number_format($totalNet, 2, ',', ' ') ?> <?= e($account['currency']) ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body" style="<?= empty($posPayments) ? 'padding:1.5rem;' : 'padding:0;' ?>">
        <?php if (empty($posPayments)): ?>
            <div style="text-align:center;color:var(--text-muted);padding:1rem 0;">
                <i class="bi bi-credit-card-2-front" style="font-size:1.8rem;opacity:0.25;display:block;margin-bottom:0.5rem;"></i>
                Aucun encaissement TPE enregistré sur ce compte.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin:0;">
                    <thead>
                        <tr>
                            <th style="width:3.5rem;">#</th>
                            <th>Date</th>
                            <th>Carte</th>
                            <th>Client</th>
                            <th class="text-right">Montant</th>
                            <th class="text-right">Remboursé</th>
                            <th class="text-right">Net</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($posPayments as $pp):
                        $ppCancelled = !empty($pp['cancelled_at']);
                        $ppDeferred  = !empty($pp['deferred_debit_id']);
                        $ppRefunded  = (float) $pp['total_refunded'] > 0;
                        $ppNet       = (float) $pp['net_amount'];
                        $ppAmount    = (float) $pp['amount'];
                    ?>
                        <tr class="<?= $ppCancelled ? 'text-muted' : '' ?>">
                            <td class="text-small"><?= (int) $pp['id'] ?></td>
                            <td class="text-small"><?= date('d/m/Y H:i', strtotime($pp['created_at'])) ?></td>
                            <td>
                                <?php if (!empty($pp['comment'])): ?>
                                    <span class="text-small text-muted" title="<?= e($pp['comment']) ?>">
                                        <?= e(substr($pp['comment'], 0, 30)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($pp['client_name'])): ?>
                                    <strong class="text-small"><?= e($pp['client_name']) ?></strong>
                                    <?php if (!empty($pp['client_account_name'])): ?>
                                        <br><span class="text-small text-muted"><?= e($pp['client_account_name']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <strong><?= number_format($ppAmount, 2, ',', ' ') ?></strong>
                                <span class="text-muted text-small"><?= e($pp['currency']) ?></span>
                            </td>
                            <td class="text-right">
                                <?php if ($ppRefunded): ?>
                                    <span style="color:var(--warning,#d97706);">
                                        <?= number_format((float) $pp['total_refunded'], 2, ',', ' ') ?>
                                    </span>
                                    <span class="text-muted text-small"><?= e($pp['currency']) ?></span>
                                    <!-- Détail remboursements -->
                                    <details style="display:inline-block;margin-top:0.2rem;font-size:0.78rem;">
                                        <summary style="cursor:pointer;color:var(--text-muted);">
                                            <?= count($pp['refunds']) ?> remb.
                                        </summary>
                                        <div style="padding:0.4rem 0.5rem;background:var(--bg-secondary,#f8f9fa);border-radius:4px;margin-top:0.2rem;white-space:nowrap;">
                                            <?php foreach ($pp['refunds'] as $rf): ?>
                                                <div>
                                                    <?= date('d/m/Y H:i', strtotime($rf['refunded_at'])) ?>
                                                    — <?= number_format((float) $rf['amount'], 2, ',', ' ') ?> <?= e($pp['currency']) ?>
                                                    <?php if (!empty($rf['refunded_by_name'])): ?>
                                                        <span class="text-muted">(par <?= e($rf['refunded_by_name']) ?>)</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($rf['reason'])): ?>
                                                        <br><span class="text-muted" style="padding-left:0.5rem;"><?= e($rf['reason']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($ppCancelled): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <strong style="color:<?= $ppNet > 0 ? 'var(--success,#22c55e)' : 'var(--text-muted)' ?>;">
                                        <?= number_format($ppNet, 2, ',', ' ') ?>
                                    </strong>
                                    <span class="text-muted text-small"><?= e($pp['currency']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ppCancelled): ?>
                                    <span class="badge bg-secondary">Annulé</span>
                                    <?php if (!empty($pp['cancel_reason'])): ?>
                                        <br><small class="text-muted" title="<?= e($pp['cancel_reason']) ?>"><?= e(mb_substr($pp['cancel_reason'], 0, 35)) ?><?= mb_strlen($pp['cancel_reason']) > 35 ? '…' : '' ?></small>
                                    <?php endif; ?>
                                <?php elseif ($ppDeferred): ?>
                                    <span class="badge bg-warning text-dark">Différé</span>
                                <?php elseif ($ppRefunded && $ppNet <= 0.001): ?>
                                    <span class="badge badge-success">Succès</span>
                                    <br><span class="badge bg-secondary text-small" style="margin-top:0.2rem;">Remboursé intégralement</span>
                                <?php elseif ($ppRefunded): ?>
                                    <span class="badge badge-success">Succès</span>
                                    <br><span class="badge bg-warning text-dark text-small" style="margin-top:0.2rem;">Remb. partiel</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Succès</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--border-color);font-weight:600;">
                            <td colspan="4" class="text-right text-small text-muted">Total</td>
                            <td class="text-right">
                                <?= number_format(array_sum(array_column($posPayments, 'amount')), 2, ',', ' ') ?>
                                <span class="text-muted text-small"><?= e($account['currency']) ?></span>
                            </td>
                            <td class="text-right" style="color:var(--warning,#d97706);">
                                <?= number_format(array_sum(array_column($posPayments, 'total_refunded')), 2, ',', ' ') ?>
                                <span class="text-muted text-small"><?= e($account['currency']) ?></span>
                            </td>
                            <td class="text-right" style="color:var(--success,#22c55e);">
                                <?= number_format($totalNet, 2, ',', ' ') ?>
                                <span class="text-muted text-small"><?= e($account['currency']) ?></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- Section : Virements permanents (récurrents)             -->
<!-- ======================================================= -->
<div class="card mt-2" id="recurring-transfers" style="border-left:3px solid var(--primary);">
    <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
        <h3 style="margin:0;"><i class="bi bi-arrow-repeat" style="color:var(--primary);"></i> Virements permanents</h3>
        <?php $activeCount = count(array_filter($recurringTransfers ?? [], fn($r) => ($r['status'] ?? '') === 'active')); ?>
        <?php if ($activeCount > 0): ?>
            <span class="badge" style="background:var(--primary);color:#fff;"><?= $activeCount ?> actif<?= $activeCount > 1 ? 's' : '' ?></span>
        <?php endif; ?>
        <a href="/transfers/create" class="btn btn-primary btn-sm" style="margin-left:auto;">
            <i class="bi bi-plus-lg"></i> Nouveau
        </a>
    </div>
    <div class="card-body" style="<?= empty($recurringTransfers) ? 'padding:1.5rem;' : 'padding:0;' ?>">
        <?php if (empty($recurringTransfers)): ?>
            <div style="text-align:center;color:var(--text-muted);padding:1rem 0;">
                <i class="bi bi-arrow-repeat" style="font-size:1.8rem;opacity:0.25;display:block;margin-bottom:0.5rem;"></i>
                Aucun virement permanent lié à ce compte.
                <div style="margin-top:0.75rem;">
                    <a href="/transfers/create" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Créer un virement récurrent
                    </a>
                </div>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table" id="recurring-transfers-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Sens</th>
                        <th>De</th>
                        <th>Vers</th>
                        <th class="text-right">Montant</th>
                        <th>Intervalle</th>
                        <th>Prochain</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recurringTransfers as $r): ?>
                    <?php
                        $isEmitter = (int) $r['from_account_id'] === (int) $account['id'];
                    ?>
                    <tr>
                        <td>
                            <?php if ($isEmitter): ?>
                                <span class="badge badge-danger" title="Ce compte est émetteur">
                                    <i class="bi bi-arrow-up-right"></i> Sortant
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success" title="Ce compte est destinataire">
                                    <i class="bi bi-arrow-down-left"></i> Entrant
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.88rem;">
                            <?php if ($isEmitter): ?>
                                <strong><?= e($r['from_account_name']) ?></strong>
                                <small class="text-muted">(ce compte)</small>
                            <?php else: ?>
                                <?= e($r['from_account_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.88rem;">
                            <?php if (!$isEmitter): ?>
                                <strong><?= e($r['to_account_name']) ?></strong>
                                <small class="text-muted">(ce compte)</small>
                            <?php else: ?>
                                <?= e($r['to_account_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-bold <?= $isEmitter ? 'text-danger' : 'text-success' ?>">
                            <?= $isEmitter ? '−' : '+' ?>
                            <?= fmt_amount_smart((float) $r['amount']) ?>
                            <?= e($account['currency']) ?>
                        </td>
                        <td style="white-space:nowrap;font-size:0.88rem;">
                            <i class="bi bi-arrow-clockwise" style="opacity:0.5;"></i>
                            <?= (int) $r['interval_days'] ?> j
                        </td>
                        <td style="font-size:0.85rem;white-space:nowrap;">
                            <?php if (($r['status'] ?? '') === 'active'): ?>
                                <i class="bi bi-calendar-event" style="opacity:0.5;"></i>
                                <?= e(format_date($r['next_execution_at'])) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem;color:var(--text-muted);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
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
                            <?php if (($r['status'] ?? '') === 'active' && ($isOwner || $isModerator)): ?>
                            <form method="POST" action="/transfers/recurring/<?= (int) $r['id'] ?>/cancel"
                                  style="display:inline"
                                  onsubmit="return confirm('Annuler ce virement récurrent #<?= (int) $r['id'] ?> ?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_to" value="/accounts/<?= (int) $account['id'] ?>#recurring-transfers">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        style="padding:0.2rem 0.5rem;font-size:0.78rem;"
                                        title="Annuler ce virement récurrent">
                                    <i class="bi bi-x-circle"></i>
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
    </div>
</div>

<?php if ($isOwner || $isModerator): ?>
<!-- Zone résiliation du compte -->
<div class="card mt-2" style="border: 1px solid var(--danger);">
    <div class="card-body">
        <?php if ($isDisabled): ?>
        <div class="d-flex justify-between align-center flex-wrap gap-1">
            <div>
                <h4 style="margin:0;color:var(--danger)"><i class="bi bi-slash-circle"></i> Compte en cours de résiliation</h4>
                <p class="text-small text-muted" style="margin:0">
                    Désactivé le <?= date('d/m/Y à H:i', strtotime($account['disabled_at'] ?? '')) ?>.
                    Ce compte sera définitivement supprimé à la fin du mois.
                </p>
            </div>
            <form method="POST" action="/<?= $isModerator ? 'moderation/' : '' ?>accounts/<?= (int) $account['id'] ?>/enable">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('Annuler la résiliation et réactiver ce compte ?')">
                    <i class="bi bi-arrow-counterclockwise"></i> Annuler la résiliation
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="d-flex justify-between align-center flex-wrap gap-1">
            <div>
                <h4 class="text-danger" style="margin:0">Désactiver ce compte</h4>
                <p class="text-small text-muted" style="margin:0">
                    Le compte sera définitivement supprimé à la fin du mois calendaire.
                    Les prélèvements en cours restent effectifs jusqu'à la fin du mois.
                </p>
            </div>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/disable">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Désactiver ce compte ? Il sera définitivement supprimé à la fin du mois. Cette action peut être annulée avant la fin du mois.')">
                    <i class="bi bi-slash-circle"></i> Désactiver
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function toggleExpires(select) {
    document.getElementById('expires_group').style.display = select.value === 'temporary' ? '' : 'none';
}
</script>

<!-- ── Modal Agios ────────────────────────────────────────────── -->
<?php if ($isModerator): ?>
<div id="agiosModalOverlay" class="agios-modal-overlay">
    <div class="agios-modal">
        <div class="agios-modal__header">
            <h3 class="agios-modal__title">
                <i class="bi bi-bank2"></i>
                Prélever des agios
            </h3>
            <button type="button" onclick="closeAgiosModal()" class="agios-modal__close" title="Fermer">&times;</button>
        </div>

        <?php if (!$isOverdraftExceed): ?>
        <div class="alert alert-warning" style="font-size:0.84rem;margin-bottom:1rem;">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Solde actuellement régularisé</strong> — le découvert a été compensé
            (solde&nbsp;: <strong><?= number_format($balance, 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>).
            Vous pouvez tout de même prélever des agios en raison d'un dépassement antérieur.
        </div>
        <?php else: ?>
        <div class="alert alert-danger" style="font-size:0.84rem;margin-bottom:1rem;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Solde actuel&nbsp;: <strong><?= number_format($balance, 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>.
            <?php if ($typeAllowsOd && (float)($account['overdraft'] ?? 0) > 0): ?>
                Découvert autorisé&nbsp;: <strong><?= number_format((float)$account['overdraft'], 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>.
                Dépassement actif&nbsp;: <strong><?= number_format(abs($balance + (float)$account['overdraft']), 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>.
            <?php else: ?>
                Aucun découvert autorisé. Dépassement actif&nbsp;: <strong><?= number_format(abs($balance), 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php $currentOverdraft = round(max(0.0, $overdraftLimit - $balance), 2); ?>
        <form method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/charge-agios" id="agiosModalForm">
            <?= csrf_field() ?>
            <input type="hidden" name="taeg_rate"    id="agiosHiddenRate"    value="">
            <input type="hidden" name="taeg_capital" id="agiosHiddenCapital" value="">
            <input type="hidden" name="taeg_days"    id="agiosHiddenDays"    value="">

            <!-- Toggle Manuel / TAEG -->
            <div class="agios-mode-toggle">
                <button type="button" id="agiosBtnManuel" onclick="agiosSwitchMode('manuel')"
                        class="agios-mode-toggle__btn active">
                    <i class="bi bi-pencil"></i> Manuel
                </button>
                <button type="button" id="agiosBtnTaeg" onclick="agiosSwitchMode('taeg')"
                        class="agios-mode-toggle__btn">
                    <i class="bi bi-calculator"></i> Par TAEG
                </button>
            </div>

            <!-- Panel Manuel -->
            <div id="agiosPanelManuel" class="agios-panel">
                <label style="display:block;font-size:0.83rem;font-weight:600;margin-bottom:0.35rem;">
                    Montant des agios <span style="color:var(--danger)">*</span>
                </label>
                <div class="agios-amount-row">
                    <input type="number" name="amount" id="agiosAmount"
                           min="0.01" step="0.01" required placeholder="0.00">
                    <span class="agios-currency-tag"><?= e($account['currency']) ?></span>
                </div>
            </div>

            <!-- Panel TAEG -->
            <div id="agiosPanelTaeg" class="agios-panel" style="display:none;">
                <div class="agios-taeg-grid">
                    <div class="agios-field">
                        <label>TAEG&nbsp;(%)</label>
                        <input type="number" id="agiosTaegRate" min="0.01" step="0.01"
                               placeholder="ex.&nbsp;15.00" oninput="agiosCalcTaeg()">
                    </div>
                    <div class="agios-field">
                        <label>Capital&nbsp;(<?= e($account['currency']) ?>)</label>
                        <input type="number" id="agiosTaegCapital" min="0.01" step="0.01"
                               value="<?= $currentOverdraft > 0 ? $currentOverdraft : '' ?>"
                               placeholder="0.00" oninput="agiosCalcTaeg()">
                    </div>
                    <div class="agios-field">
                        <label>Durée&nbsp;(jours)</label>
                        <input type="number" id="agiosTaegDays" min="1" step="1"
                               placeholder="ex.&nbsp;30" oninput="agiosCalcTaeg()">
                    </div>
                </div>
                <div class="agios-formula-hint">
                    Formule&nbsp;: Capital &times; TAEG&nbsp;% &divide; 100 &times; Jours &divide; 365
                </div>
                <div class="agios-result-box">
                    <span>Agios calculés</span>
                    <span class="agios-result-box__value" id="agiosTaegResult">—</span>
                </div>
            </div>

            <!-- Commentaire (commun aux deux modes) -->
            <div class="agios-comment-field" style="margin-top:1rem;">
                <label>Commentaire <span style="font-weight:400;color:var(--text-muted)">(facultatif)</span></label>
                <input type="text" name="comment" maxlength="255"
                       placeholder="Agios — dépassement du découvert autorisé">
            </div>

            <div class="agios-form-footer">
                <button type="button" onclick="closeAgiosModal()" class="btn btn-outline btn-sm">Annuler</button>
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="bi bi-exclamation-triangle-fill"></i> Confirmer le prélèvement
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function agiosSwitchMode(mode) {
    var isManuel = mode === 'manuel';
    document.getElementById('agiosPanelManuel').style.display = isManuel ? '' : 'none';
    document.getElementById('agiosPanelTaeg').style.display   = isManuel ? 'none' : '';
    document.getElementById('agiosBtnManuel').classList.toggle('active', isManuel);
    document.getElementById('agiosBtnTaeg').classList.toggle('active', !isManuel);
    var amt = document.getElementById('agiosAmount');
    amt.required = isManuel;
    if (isManuel) {
        document.getElementById('agiosHiddenRate').value    = '';
        document.getElementById('agiosHiddenCapital').value = '';
        document.getElementById('agiosHiddenDays').value    = '';
    } else {
        agiosCalcTaeg();
    }
}
function agiosCalcTaeg() {
    var rate    = parseFloat(document.getElementById('agiosTaegRate').value);
    var capital = parseFloat(document.getElementById('agiosTaegCapital').value);
    var days    = parseFloat(document.getElementById('agiosTaegDays').value);
    var resultEl = document.getElementById('agiosTaegResult');
    var amt      = document.getElementById('agiosAmount');
    if (rate > 0 && capital > 0 && days > 0) {
        var amount = Math.round(capital * (rate / 100) * (days / 365) * 100) / 100;
        resultEl.textContent = amount.toFixed(2).replace('.', ',') + '\u00a0<?= e($account['currency']) ?>';
        resultEl.classList.add('ready');
        amt.value = amount;
        document.getElementById('agiosHiddenRate').value    = rate;
        document.getElementById('agiosHiddenCapital').value = capital;
        document.getElementById('agiosHiddenDays').value    = days;
    } else {
        resultEl.textContent = '—';
        resultEl.classList.remove('ready');
        amt.value = '';
        document.getElementById('agiosHiddenRate').value    = '';
        document.getElementById('agiosHiddenCapital').value = '';
        document.getElementById('agiosHiddenDays').value    = '';
    }
}
document.getElementById('agiosModalForm').addEventListener('submit', function (e) {
    var amount = parseFloat(document.getElementById('agiosAmount').value);
    if (!amount || amount <= 0) {
        e.preventDefault();
        alert('Veuillez saisir un montant ou renseigner les trois champs TAEG.');
    }
});
function openAgiosModal() {
    var overlay = document.getElementById('agiosModalOverlay');
    overlay.classList.add('open');
    agiosSwitchMode('manuel');
    var inp = document.getElementById('agiosAmount');
    if (inp) { inp.value = ''; inp.focus(); }
}
function closeAgiosModal() {
    document.getElementById('agiosModalOverlay').classList.remove('open');
}
document.getElementById('agiosModalOverlay').addEventListener('click', function (e) {
    if (e.target === this) closeAgiosModal();
});
</script>
<?php endif; ?>

<!-- ── Modal Gel de compte ──────────────────────────────────────── -->
<?php if ($isModerator && !$isFrozen): ?>
<div id="freezeModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--card-bg,#fff);border-radius:var(--border-radius);box-shadow:0 8px 32px rgba(0,0,0,0.18);padding:1.5rem 1.75rem;width:100%;max-width:460px;margin:1rem;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.15rem;gap:0.5rem;">
            <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:0.45rem;flex:1;min-width:0;">
                <i class="bi bi-snow" style="color:#3b82f6;flex-shrink:0;"></i>
                <span>Geler &laquo;&nbsp;<span id="freezeModalName" style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;display:inline-block;vertical-align:bottom;"><?= e($account['name']) ?></span>&nbsp;&raquo;</span>
            </h3>
            <button type="button" onclick="closeFreezeModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.3rem;line-height:1;padding:0 0.2rem;flex-shrink:0;" title="Fermer">&times;</button>
        </div>
        <p style="font-size:0.82rem;color:var(--text-muted);margin:0.1rem 0 1.1rem;">Les opérations sortantes et les virements débiteurs seront bloqués.</p>

        <form id="freezeModalForm" method="POST" action="/moderation/accounts/<?= (int) $account['id'] ?>/freeze">
            <?= csrf_field() ?>

            <!-- Motif -->
            <div style="margin-bottom:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.35rem;">
                    <label style="font-size:0.83rem;font-weight:600;">
                        Motif <span style="font-weight:400;color:var(--text-muted)">(facultatif)</span>
                    </label>
                    <span id="modalCharCounter" style="font-size:0.76rem;color:var(--text-muted);">0 / 500</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.45rem;">
                    <button type="button" class="freeze-reason-chip" data-reason="Fraude suspectée"><i class="bi bi-exclamation-triangle"></i> Fraude</button>
                    <button type="button" class="freeze-reason-chip" data-reason="Demande judiciaire"><i class="bi bi-bank"></i> Judiciaire</button>
                    <button type="button" class="freeze-reason-chip" data-reason="Vérification en cours"><i class="bi bi-search"></i> Vérification</button>
                    <button type="button" class="freeze-reason-chip" data-reason="Blocage préventif"><i class="bi bi-shield-lock"></i> Préventif</button>
                </div>
                <textarea id="modalReason" name="reason" rows="2" maxlength="500"
                    placeholder="Ou saisissez un motif personnalisé…"
                    style="width:100%;font-size:0.84rem;padding:0.4rem 0.6rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);resize:vertical;box-sizing:border-box;"></textarea>
            </div>

            <!-- Durée -->
            <div style="margin-bottom:1.2rem;">
                <label style="display:block;font-size:0.83rem;font-weight:600;margin-bottom:0.4rem;">Durée du gel</label>
                <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.5rem;">
                    <button type="button" class="freeze-chip active" data-days="">Indéfini</button>
                    <button type="button" class="freeze-chip" data-days="1">1 jour</button>
                    <button type="button" class="freeze-chip" data-days="3">3 jours</button>
                    <button type="button" class="freeze-chip" data-days="7">7 jours</button>
                    <button type="button" class="freeze-chip" data-days="30">1 mois</button>
                    <button type="button" class="freeze-chip" data-days="90">3 mois</button>
                    <button type="button" class="freeze-chip" data-days="custom"><i class="bi bi-calendar3"></i> Personnalisé</button>
                </div>
                <input type="datetime-local" id="modalFrozenUntil" name="frozen_until"
                    style="display:none;font-size:0.84rem;padding:0.4rem 0.6rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:100%;box-sizing:border-box;margin-bottom:0.4rem;">
                <div id="modalFreezePreview" class="freeze-preview">
                    <i class="bi bi-infinity"></i> Gel indéfini — jusqu'à révocation manuelle.
                </div>
            </div>

            <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
                <button type="button" onclick="closeFreezeModal()" class="btn btn-outline btn-sm">Annuler</button>
                <button type="submit" class="btn btn-freeze btn-sm"><i class="bi bi-snow"></i> Confirmer le gel</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function formatLocal(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function friendlyDate(iso) {
        if (!iso) return null;
        var d = new Date(iso);
        if (isNaN(d)) return null;
        var months = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear()
            + ' à ' + pad(d.getHours()) + 'h' + pad(d.getMinutes());
    }

    function initFreezeForm(opts) {
        var chips       = opts.form.querySelectorAll('.freeze-chip[data-days]');
        var customInput = document.getElementById(opts.inputId);
        var preview     = document.getElementById(opts.previewId);
        var textarea    = document.getElementById(opts.reasonId);
        var counter     = document.getElementById(opts.counterId);
        var reasonChips = opts.form.querySelectorAll('.freeze-reason-chip');

        function updatePreview() {
            var val = customInput.value;
            if (!val) {
                preview.innerHTML = '<i class="bi bi-infinity"></i> Gel indéfini — jusqu\'à révocation manuelle.';
                preview.style.color = '';
            } else {
                var fd = friendlyDate(val);
                preview.innerHTML = '<i class="bi bi-calendar-check" style="color:#3b82f6"></i> Dégel automatique le <strong>' + fd + '</strong>.';
                preview.style.color = '#1d4ed8';
            }
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                var days = chip.dataset.days;
                if (days === '') {
                    customInput.value = '';
                    customInput.style.display = 'none';
                } else if (days === 'custom') {
                    customInput.style.display = 'block';
                    customInput.focus();
                } else {
                    var d = new Date();
                    d.setDate(d.getDate() + parseInt(days, 10));
                    customInput.value = formatLocal(d);
                    customInput.style.display = 'none';
                }
                updatePreview();
            });
        });

        customInput.addEventListener('input', updatePreview);

        if (textarea && counter) {
            textarea.addEventListener('input', function () {
                counter.textContent = textarea.value.length + ' / 500';
            });
        }

        reasonChips.forEach(function (rc) {
            rc.addEventListener('click', function () {
                if (textarea) {
                    textarea.value = rc.dataset.reason;
                    if (counter) counter.textContent = textarea.value.length + ' / 500';
                    textarea.focus();
                }
            });
        });

        updatePreview();
        return { reset: function () {
            chips.forEach(function (c) { c.classList.remove('active'); });
            var first = opts.form.querySelector('.freeze-chip[data-days=""]');
            if (first) first.classList.add('active');
            customInput.value = '';
            customInput.style.display = 'none';
            if (textarea) textarea.value = '';
            if (counter)  counter.textContent = '0 / 500';
            updatePreview();
        }};
    }

    var modalForm = document.getElementById('freezeModalForm');
    var modalCtrl = initFreezeForm({
        form:      modalForm,
        inputId:   'modalFrozenUntil',
        previewId: 'modalFreezePreview',
        reasonId:  'modalReason',
        counterId: 'modalCharCounter',
    });

    window.openFreezeModal = function (accountId, accountName) {
        document.getElementById('freezeModalName').textContent = accountName;
        modalForm.action = '/moderation/accounts/' + accountId + '/freeze';
        modalCtrl.reset();
        document.getElementById('freezeModalOverlay').style.display = 'flex';
    };

    window.closeFreezeModal = function () {
        document.getElementById('freezeModalOverlay').style.display = 'none';
    };

    document.getElementById('freezeModalOverlay').addEventListener('click', function (e) {
        if (e.target === this) window.closeFreezeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeFreezeModal();
    });
}());
</script>
<?php endif; ?>


</div><!-- /.account-show-page -->
