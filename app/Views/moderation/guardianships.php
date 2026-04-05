<div class="page-header">
    <div>
        <h1><i class="bi bi-person-lock"></i> Tutelles légales</h1>
        <p class="page-description">Responsables légaux des comptes mineurs</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/minor-accounts/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nouveau compte mineur
        </a>
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-bank"></i> Comptes
        </a>
        <a href="/moderation/users" class="btn btn-outline btn-sm">
            <i class="bi bi-people"></i> Utilisateurs
        </a>
    </div>
</div>

<div class="alert" style="background:rgba(var(--info-rgb,59,130,246),0.08);border-left:4px solid #3b82f6;display:flex;align-items:flex-start;gap:0.75rem;padding:0.9rem 1.1rem;margin-bottom:1.5rem;">
    <i class="bi bi-info-circle" style="font-size:1.1rem;color:#3b82f6;flex-shrink:0;"></i>
    <div style="font-size:0.84rem;">
        Les responsables légaux ont <strong>procuration automatique</strong> sur tous les comptes du mineur.
        Cette procuration <strong>expire automatiquement</strong> dès que le mineur atteint 18 ans.
        Les historiques de tutelle sont conservés même après l'expiration.
    </div>
</div>

<?php if (empty($guardianships)): ?>
    <div class="empty-state">
        <div class="empty-icon">🔏</div>
        <p>Aucune tutelle légale enregistrée.</p>
        <a href="/moderation/minor-accounts/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Créer un compte mineur
        </a>
    </div>
<?php else: ?>

<?php
// Grouper les tutelles par mineur pour un affichage plus lisible
$byMinor = [];
foreach ($guardianships as $g) {
    $byMinor[$g['minor_user_id']][] = $g;
}
?>

<div style="display:flex;flex-direction:column;gap:1rem;">
<?php foreach ($byMinor as $minorId => $links): ?>
<?php $first = $links[0]; ?>
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <i class="bi bi-person-badge" style="font-size:1.1rem;"></i>
            <span class="font-bold"><?= e($first['minor_username']) ?></span>
            <?php if (!empty($first['minor_birth_date'])): ?>
                <span class="text-small text-muted">
                    — né(e) le <?= date('d/m/Y', strtotime($first['minor_birth_date'])) ?>
                </span>
            <?php endif; ?>
            <?php if ($first['is_still_minor']): ?>
                <span class="badge badge-warning" style="background:#f59e0b;color:#fff;">
                    <i class="bi bi-person-arms-up"></i> Mineur
                </span>
            <?php else: ?>
                <span class="badge badge-secondary" title="Ce membre est devenu majeur — les procurations sont expirées.">
                    <i class="bi bi-person-check"></i> Majeur (procurations expirées)
                </span>
            <?php endif; ?>
        </div>
        <?php if ($first['is_still_minor'] && count($links) < 2): ?>
        <form method="POST"
              action="/moderation/guardianships/<?= (int) $minorId ?>/add"
              style="display:flex;align-items:center;gap:0.4rem;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <select name="guardian_user_id" class="form-control"
                    style="width:auto;padding:0.3rem 0.6rem;font-size:0.82rem;" required>
                <option value="">— Ajouter un tuteur —</option>
                <?php foreach ($adultUsers as $u): ?>
                    <?php
                    // Ne pas proposer un adulte déjà tuteur de ce mineur
                    $alreadyTutor = false;
                    foreach ($links as $l) {
                        if ((int) $l['guardian_user_id'] === (int) $u['id']) {
                            $alreadyTutor = true;
                            break;
                        }
                    }
                    if ($alreadyTutor) continue;
                    ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Ajouter
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0">
        <table class="table" style="margin:0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Responsable légal</th>
                    <th>Ajouté le</th>
                    <th>Statut</th>
                    <th style="text-align:right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $g): ?>
                <tr>
                    <td class="text-muted text-small"><?= (int) $g['id'] ?></td>
                    <td class="font-bold">
                        <i class="bi bi-person-check text-success"></i>
                        <?= e($g['guardian_username']) ?>
                    </td>
                    <td class="text-small text-muted">
                        <?= date('d/m/Y', strtotime($g['created_at'])) ?>
                    </td>
                    <td>
                        <?php if ($g['is_still_minor']): ?>
                            <span class="badge badge-success">
                                <i class="bi bi-check-circle"></i> Procuration active
                            </span>
                        <?php else: ?>
                            <span class="badge badge-secondary">
                                <i class="bi bi-clock-history"></i> Expirée (mineur devenu majeur)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right">
                        <form method="POST"
                              action="/moderation/guardianships/<?= (int) $g['id'] ?>/remove"
                              style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Supprimer la tutelle de <?= e(addslashes($g['guardian_username'])) ?> sur <?= e(addslashes($g['minor_username'])) ?> ?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<?php if (!empty($minorUsers)): ?>
<div class="card mt-3" style="max-width:540px;">
    <div class="card-header">
        <h3 style="margin:0;font-size:0.95rem;">
            <i class="bi bi-person-plus"></i> Ajouter une tutelle
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="add-guardianship-form"
              onsubmit="this.action='/moderation/guardianships/'+document.getElementById('add-minor-select').value+'/add'">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="form-row" style="gap:0.75rem;align-items:flex-end;">
                <div class="form-group" style="margin:0;flex:1;">
                    <label class="form-label" style="font-size:0.8rem;">Mineur</label>
                    <select id="add-minor-select" class="form-control"
                            style="font-size:0.84rem;padding:0.35rem 0.6rem;" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($minorUsers as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;flex:1;">
                    <label class="form-label" style="font-size:0.8rem;">Responsable légal (adulte)</label>
                    <select name="guardian_user_id" class="form-control"
                            style="font-size:0.84rem;padding:0.35rem 0.6rem;" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($adultUsers as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="padding-bottom:0.05rem;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Ajouter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
