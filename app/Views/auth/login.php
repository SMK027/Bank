<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2>Connexion</h2>
            <form method="POST" action="/login">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email" class="form-label">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="votre@email.com" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Votre mot de passe" required>
                        <button type="button" class="btn-toggle-password" data-target="password" title="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                Pas encore de compte ? <a href="/register">Créer un compte</a>
            </p>
            <p class="text-center text-muted text-small">
                <a href="/login/pin"><i class="bi bi-shield-lock"></i> Se connecter avec un numéro de compte et un code PIN</a>
            </p>
        </div>
    </div>
</div>
