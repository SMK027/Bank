<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2>Inscription</h2>
            <form method="POST" action="/register">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control"
                           placeholder="MonPseudo" required autofocus
                           minlength="3" maxlength="50" pattern="[a-zA-Z0-9_-]+">
                    <span class="form-hint">3 à 50 caractères (lettres, chiffres, tirets, underscores)</span>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="votre@email.com" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Votre mot de passe" required minlength="8">
                        <button type="button" class="btn-toggle-password" data-target="password" title="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <span class="form-hint">8 caractères minimum</span>
                </div>
                <div class="form-group">
                    <label for="birth_date" class="form-label">Date de naissance</label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control" required
                           max="<?= date('Y-m-d') ?>">
                    <span class="form-hint">Nécessaire pour déterminer les types de comptes accessibles</span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                Déjà un compte ? <a href="/login">Se connecter</a>
            </p>
        </div>
    </div>
</div>
