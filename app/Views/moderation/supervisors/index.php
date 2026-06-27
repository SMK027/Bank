<?php
/** @var array $supervisors */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-person-badge"></i> Superviseurs</h1>
        <p class="page-description">Comptes permettant de contourner provisoirement les fonctionnalités désactivées.</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/features" class="btn btn-outline btn-sm"><i class="bi bi-toggles"></i> Fonctionnalités</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-person-badge"></i> Superviseurs</span>
        <a href="/moderation/supervisors/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nouveau superviseur
        </a>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom:1.25rem;font-size:0.88rem;">
    <i class="bi bi-info-circle"></i>
    Un superviseur peut débloquer une fonctionnalité désactivée pour sa session uniquement,
    via le bouton <strong>Superviseur</strong> affiché sur la page d'erreur 503.
    Chaque utilisation est journalisée dans le <a href="/moderation/audit-log">journal d'audit</a>.
</div>

<?php if (empty($supervisors)): ?>
<div class="card"><div class="card-body text-center" style="padding:2rem;">
    <i class="bi bi-person-badge" style="font-size:2.5rem;color:var(--text-muted);display:block;margin-bottom:0.75rem;"></i>
    <p class="text-muted">Aucun superviseur configuré.</p>
    <a href="/moderation/supervisors/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Créer le premier superviseur
    </a>
</div></div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table" style="margin:0;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Identifiant</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($supervisors as $sv): ?>
                    <?php $isActive = $sv['status'] === 'active'; ?>
                    <tr style="<?= $isActive ? '' : 'opacity:0.6;' ?>">
                        <td class="text-muted text-small">#<?= (int) $sv['id'] ?></td>
                        <td>
                            <strong><?= e($sv['first_name']) ?> <?= e($sv['last_name']) ?></strong>
                        </td>
                        <td><code><?= e($sv['supervisor_id']) ?></code></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge badge-success">Actif</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Désactivé</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-small"><?= e(date('d/m/Y', strtotime($sv['created_at']))) ?></td>
                        <td style="text-align:right;white-space:nowrap;">
                            <!-- Réinitialiser PIN -->
                            <form method="POST"
                                  action="/moderation/supervisors/<?= (int) $sv['id'] ?>/pin/reset"
                                  style="display:inline;"
                                  onsubmit="return confirm('Réinitialiser le PIN de ce superviseur ?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm" title="Réinitialiser le PIN">
                                    <i class="bi bi-key"></i> PIN
                                </button>
                            </form>
                            <!-- Activer / Désactiver -->
                            <form method="POST"
                                  action="/moderation/supervisors/<?= (int) $sv['id'] ?>/toggle"
                                  style="display:inline;"
                                  onsubmit="return confirm('<?= $isActive ? 'Désactiver' : 'Réactiver' ?> ce superviseur ?');">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        class="btn btn-sm <?= $isActive ? 'btn-warning' : 'btn-success' ?>"
                                        title="<?= $isActive ? 'Désactiver' : 'Réactiver' ?>">
                                    <i class="bi bi-<?= $isActive ? 'pause-fill' : 'play-fill' ?>"></i>
                                    <?= $isActive ? 'Désactiver' : 'Réactiver' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
