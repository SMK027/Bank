<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-lock"></i> Changer votre code PIN</h1>
        <p class="page-description">
            <?php if ($mustChange): ?>
                Votre code PIN a été réinitialisé par la modération. Vous devez définir un nouveau code personnel.
            <?php else: ?>
                Définissez un nouveau code PIN pour votre compte.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($mustChange): ?>
<div class="alert alert-warning" role="alert"
     style="display:flex;align-items:flex-start;gap:0.75rem;margin-bottom:1.25rem;">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:1.2rem;flex-shrink:0;margin-top:0.1rem;"></i>
    <div>
        <strong>Action requise</strong> — Un modérateur a réinitialisé votre code PIN.
        Vous devez définir un nouveau code personnel avant de continuer à utiliser la plateforme.
        <br><span class="text-small">Le nouveau code doit être différent du code temporaire reçu.</span>
    </div>
</div>
<?php endif; ?>

<div class="card" style="max-width:480px;margin:0 auto;">
    <div class="card-body">
        <form method="POST" action="/profile/pin/change" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="new_pin" class="form-label">Nouveau code PIN (6 chiffres)</label>
                <div class="password-wrapper">
                    <input type="password" id="new_pin" name="new_pin"
                           class="form-control" inputmode="numeric" pattern="\d{6}"
                           maxlength="6" placeholder="••••••" autofocus required>
                    <button type="button" class="btn-toggle-password"
                            data-target="new_pin" title="Afficher">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm_pin" class="form-label">Confirmer le nouveau code PIN</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_pin" name="confirm_pin"
                           class="form-control" inputmode="numeric" pattern="\d{6}"
                           maxlength="6" placeholder="••••••" required>
                    <button type="button" class="btn-toggle-password"
                            data-target="confirm_pin" title="Afficher">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;margin-top:1rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-shield-check"></i> Enregistrer le nouveau code PIN
                </button>
                <?php if (!$mustChange): ?>
                    <a href="/profile" class="btn btn-secondary">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
