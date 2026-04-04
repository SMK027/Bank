<div class="auth-container" style="max-width:520px">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-plus-circle"></i> Créer un compte bancaire</h2>
            <form method="POST" action="/accounts/create">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="name" class="form-label">Nom du compte</label>
                    <input type="text" id="name" name="name" class="form-control"
                           placeholder="Ex : Compte courant, Épargne vacances..." required autofocus>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="currency" class="form-label">Devise</label>
                        <select id="currency" name="currency" class="form-control" required>
                            <option value="EUR">EUR (€)</option>
                            <option value="USD">USD ($)</option>
                            <option value="GBP">GBP (£)</option>
                            <option value="CHF">CHF (Fr)</option>
                            <option value="CAD">CAD ($)</option>
                            <option value="JPY">JPY (¥)</option>
                            <option value="XOF">XOF (CFA)</option>
                            <option value="MAD">MAD (DH)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="overdraft" class="form-label">Découvert autorisé</label>
                        <input type="number" id="overdraft" name="overdraft" class="form-control"
                               placeholder="0.00" min="0" step="0.01" value="0">
                        <span class="form-hint">Montant maximal de découvert autorisé</span>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-check-lg"></i> Créer le compte
                    </button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                <a href="/dashboard"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a>
            </p>
        </div>
    </div>
</div>
