<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-shield-lock"></i> Connexion par code PIN</h2>
            <form method="POST" action="/login/pin">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="account_number" class="form-label">Numéro de compte</label>
                    <input type="text" id="account_number" name="account_number" class="form-control"
                           placeholder="BKxxxxxxxx" required autofocus
                           pattern="BK\d{8}" maxlength="10"
                           style="text-transform:uppercase;letter-spacing:0.08em;">
                </div>
                <div class="form-group">
                    <label for="pin" class="form-label">Code PIN (6 chiffres)</label>
                    <div class="password-wrapper">
                        <input type="password" id="pin" name="pin" class="form-control"
                               placeholder="••••••" required
                               inputmode="numeric" pattern="\d{6}" maxlength="6">
                        <button type="button" class="btn-toggle-password" data-target="pin" title="Afficher le code PIN">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-shield-check"></i> Se connecter
                    </button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                Connexion classique ? <a href="/login">Email &amp; mot de passe</a>
            </p>
        </div>
    </div>
</div>
