<?php
/** @var array|null $supervisor */
$isEdit = $supervisor !== null;
?>
<div class="page-header">
    <div>
        <h1>
            <i class="bi bi-person-badge"></i>
            <?= $isEdit ? 'Modifier un superviseur' : 'Nouveau superviseur' ?>
        </h1>
    </div>
    <a href="/moderation/supervisors" class="btn btn-outline btn-sm">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="card" style="max-width:520px;">
    <div class="card-body">
        <form method="POST"
              action="<?= $isEdit ? '/moderation/supervisors/' . (int) $supervisor['id'] . '/update' : '/moderation/supervisors' ?>"
              autocomplete="off">
            <?= csrf_field() ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                <div>
                    <label class="form-label" for="first_name">Prénom <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name"
                           class="form-control" required maxlength="100"
                           value="<?= e($supervisor['first_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label" for="last_name">Nom <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name"
                           class="form-control" required maxlength="100"
                           value="<?= e($supervisor['last_name'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-bottom:0.75rem;">
                <label class="form-label" for="supervisor_id">
                    Identifiant de supervision <span class="text-danger">*</span>
                </label>
                <input type="text" id="supervisor_id" name="supervisor_id"
                       class="form-control" required maxlength="64"
                       pattern="[a-zA-Z0-9_\-]{3,64}"
                       title="3–64 caractères : lettres, chiffres, tiret, underscore"
                       <?= $isEdit ? 'readonly' : '' ?>
                       value="<?= e($supervisor['supervisor_id'] ?? '') ?>">
                <small class="text-muted">
                    Lettres, chiffres, tiret, underscore (3–64 car.).<?= $isEdit ? ' Non modifiable.' : '' ?>
                </small>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label class="form-label" for="pin">
                    Code PIN<?= $isEdit ? ' (laisser vide pour ne pas changer)' : '' ?>
                </label>
                <input type="password" id="pin" name="pin"
                       class="form-control"
                       <?= $isEdit ? '' : '' ?>
                       autocomplete="new-password"
                       placeholder="<?= $isEdit ? 'Laisser vide = inchangé' : 'Vide = généré automatiquement' ?>">
                <small class="text-muted">Minimum 4 caractères. Si vide : un PIN est généré et affiché une seule fois.</small>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i>
                <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le superviseur' ?>
            </button>
        </form>
    </div>
</div>
