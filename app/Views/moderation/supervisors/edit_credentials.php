<?php
/** @var array $supervisor */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-pencil-square"></i> Modifier identifiant et PIN</h1>
        <p class="page-description">Mettez à jour l'identifiant de supervision et définissez un code PIN choisi.</p>
    </div>
    <a href="/moderation/supervisors" class="btn btn-outline btn-sm">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-body">
        <div style="margin-bottom:1rem;padding:0.85rem 1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--surface-2);">
            <strong><?= e($supervisor['first_name']) ?> <?= e($supervisor['last_name']) ?></strong><br>
            <small class="text-muted">ID actuel : <code><?= e($supervisor['supervisor_id']) ?></code></small>
        </div>

        <form method="POST" action="/moderation/supervisors/<?= (int) $supervisor['id'] ?>/update" autocomplete="off">
            <?= csrf_field() ?>

            <div style="margin-bottom:0.85rem;">
                <label class="form-label" for="supervisor_id">
                    Identifiant de supervision <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="supervisor_id"
                       name="supervisor_id"
                       class="form-control"
                       required
                       maxlength="64"
                       pattern="[a-zA-Z0-9_\-]{3,64}"
                       title="3–64 caractères : lettres, chiffres, tiret, underscore"
                       value="<?= e($supervisor['supervisor_id']) ?>">
                <small class="text-muted">Lettres, chiffres, tiret, underscore (3–64 caractères).</small>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label class="form-label" for="pin">Nouveau code PIN</label>
                <input type="password"
                       id="pin"
                       name="pin"
                       class="form-control"
                       minlength="4"
                       autocomplete="new-password"
                       placeholder="Laisser vide pour conserver le PIN actuel">
                <small class="text-muted">Minimum 4 caractères. Le PIN est mis à jour uniquement si vous renseignez ce champ.</small>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Enregistrer les modifications
            </button>
        </form>
    </div>
</div>
