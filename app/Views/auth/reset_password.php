<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2>Nouveau mot de passe</h2>
            <p class="text-muted text-small mb-3">
                Choisissez un nouveau mot de passe pour votre compte.
            </p>
            <form method="POST" action="/reset-password/<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="8 caractères minimum" required autofocus minlength="8">
                        <button type="button" class="btn-toggle-password" data-target="password" title="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <span class="form-hint">8 caractères minimum</span>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                               placeholder="Répétez votre mot de passe" required minlength="8">
                        <button type="button" class="btn-toggle-password" data-target="password_confirm" title="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Enregistrer le nouveau mot de passe</button>
                </div>
            </form>
        </div>
    </div>
</div>
