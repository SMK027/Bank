<?php
/** @var array<string,array<int,array<string,mixed>>> $grouped */
$categoryLabels = [
    'auth'         => "Authentification",
    'accounts'     => "Comptes bancaires",
    'transactions' => "Opérations",
    'transfers'    => "Virements",
    'deferred'     => "Débits différés",
    'cards'        => "Cartes bancaires",
    'loans'        => "Crédits",
];
$categoryIcons = [
    'auth'         => 'bi-shield-lock',
    'accounts'     => 'bi-bank',
    'transactions' => 'bi-cash-stack',
    'transfers'    => 'bi-arrow-left-right',
    'deferred'     => 'bi-clock-history',
    'cards'        => 'bi-credit-card',
    'loans'        => 'bi-piggy-bank',
];
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-toggles"></i> Fonctionnalités du site</h1>
        <p class="page-description">
            Activez ou désactivez des pans entiers de la simulation pour
            préparer un scénario pédagogique (incident, maintenance, mode
            consultation seule…). Les modérateurs ne sont jamais bloqués
            par ces interrupteurs.
        </p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="/moderation/events" class="btn btn-outline btn-sm"><i class="bi bi-calendar2-week"></i> Événements</a>
        <a href="/moderation/supervisors" class="btn btn-outline btn-sm"><i class="bi bi-person-badge"></i> Superviseurs</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-toggles"></i> Fonctionnalités</span>
    </div>
</div>

<div class="alert alert-warning" style="margin-bottom:1.5rem;">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Attention :</strong> désactiver <code>auth.login</code> empêche
    toute nouvelle connexion, y compris celle des modérateurs. Réactivez la
    fonctionnalité depuis une session déjà ouverte ou directement en base.
</div>

<?php if (empty($grouped)): ?>
    <div class="card"><div class="card-body">
        <p class="text-muted">Aucune fonctionnalité configurée. Vérifiez que la migration 054 a bien été appliquée.</p>
    </div></div>
<?php else: ?>
    <?php foreach ($grouped as $category => $flags): ?>
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <h3>
                    <i class="bi <?= e($categoryIcons[$category] ?? 'bi-toggle2-on') ?>"></i>
                    <?= e($categoryLabels[$category] ?? ucfirst($category)) ?>
                </h3>
            </div>
            <div class="card-body" style="padding:0;overflow-x:auto;-webkit-overflow-scrolling:touch;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fonctionnalité</th>
                            <th>Description</th>
                            <th style="width:120px;">État</th>
                            <th style="width:180px;">Dernière modif.</th>
                            <th style="width:160px;text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flags as $flag): ?>
                            <?php $enabled = (int) $flag['enabled'] === 1; ?>
                            <tr>
                                <td>
                                    <strong><?= e($flag['label']) ?></strong>
                                    <br><code style="font-size:0.75rem;color:var(--text-muted,#6b7280);"><?= e($flag['flag_key']) ?></code>
                                </td>
                                <td style="max-width:480px;"><?= e($flag['description'] ?? '') ?></td>
                                <td>
                                    <?php if ($enabled): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Activée</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Désactivée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($flag['updated_at'])): ?>
                                        <small class="text-muted">
                                            <?= e(date('d/m/Y H:i', strtotime((string) $flag['updated_at']))) ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">—</small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <form method="post" action="/moderation/features/<?= e($flag['flag_key']) ?>/toggle" style="display:inline;"
                                          onsubmit="return confirm('<?= $enabled ? 'Désactiver' : 'Activer' ?> la fonctionnalité « <?= e(addslashes($flag['label'])) ?> » ?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <?php if ($enabled): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-toggle-on"></i> Désactiver
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-toggle-off"></i> Activer
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
