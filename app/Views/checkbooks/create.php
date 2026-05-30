<?php
/** @var array  $eligibleAccounts */
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-journal-plus"></i> Nouveau chéquier</h1>
    <a href="/checkbooks" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<?php if (empty($eligibleAccounts)): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Vous n'avez aucun compte éligible pour un chéquier.
    Seuls les comptes courants et professionnels actifs peuvent être associés à un chéquier.
</div>
<?php else: ?>
<div class="card" style="max-width:520px;">
    <div class="card-body">
        <form method="POST" action="/checkbooks/create">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="account_id" class="form-label">Compte associé <span style="color:var(--danger);">*</span></label>
                <select id="account_id" name="account_id" class="form-control" required>
                    <option value="">-- Sélectionner un compte --</option>
                    <?php foreach ($eligibleAccounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?> — <?= e($acc['currency']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Ce compte ne pourra pas être modifié après création.</span>
            </div>
            <div class="form-group">
                <label for="label" class="form-label">Libellé <span class="text-muted" style="font-weight:400;font-size:0.85em;">(optionnel)</span></label>
                <input type="text" id="label" name="label" class="form-control"
                       placeholder="Ex : Chéquier principal" maxlength="100">
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                <i class="bi bi-plus-lg"></i> Créer le chéquier
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
