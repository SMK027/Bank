<div class="page-header">
    <h1><i class="bi bi-briefcase"></i> Profil professionnel</h1>
</div>

<div class="auth-container" style="max-width:560px">
    <div class="card">
        <div class="card-body">

        <?php if (!empty($user['is_professional'])): ?>
            <!-- Déjà professionnel -->
            <div class="alert alert-success" style="display:flex;align-items:center;gap:0.6rem;">
                <i class="bi bi-check-circle" style="font-size:1.3rem;"></i>
                <span>
                    <strong>Statut professionnel actif</strong><br>
                    Raison sociale : <strong><?= e($user['company_name']) ?></strong><br>
                    SIRET : <code><?= e($user['siret']) ?></code>
                </span>
            </div>
            <form method="POST" action="/profile/professional/remove" style="margin-top:1rem;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-block"
                        onclick="return confirm('Retirer le statut professionnel ? Vous ne pourrez plus créer de comptes professionnels.');">
                    <i class="bi bi-x-circle"></i> Retirer le statut professionnel
                </button>
            </form>
        <?php else: ?>
            <!-- Formulaire d'activation -->
            <p class="text-muted" style="margin-bottom:1.2rem;">
                Activez votre statut professionnel pour accéder aux comptes de type <strong>Professionnel</strong>.
                Votre numéro SIRET sera vérifié auprès du répertoire SIRENE de l'INSEE.
            </p>
            <form method="POST" action="/profile/professional" id="pro-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="siret" class="form-label">Numéro SIRET</label>
                    <div style="display:flex;gap:0.5rem;align-items:start;">
                        <input type="text" id="siret" name="siret" class="form-control"
                               placeholder="362 521 879 00034" required
                               pattern="\s*\d[\d\s]{12,16}\d\s*"
                               maxlength="20" style="flex:1;">
                        <button type="button" id="btn-verify-siret" class="btn btn-secondary"
                                style="white-space:nowrap;">
                            <i class="bi bi-search"></i> Vérifier
                        </button>
                    </div>
                    <span class="form-hint">14 chiffres — sera vérifié via l'API du gouvernement</span>
                    <div id="siret-result" style="margin-top:0.5rem;display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="company_name" class="form-label">Raison sociale</label>
                    <input type="text" id="company_name" name="company_name" class="form-control"
                           placeholder="Nom de l'entreprise" required
                           minlength="2" maxlength="255">
                    <span class="form-hint">Sera complétée automatiquement après vérification du SIRET</span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-briefcase"></i> Activer le statut professionnel
                    </button>
                </div>
            </form>
        <?php endif; ?>

            <p class="text-center text-muted text-small mt-2">
                <a href="/profile"><i class="bi bi-arrow-left"></i> Retour au profil</a>
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    var btnVerify   = document.getElementById('btn-verify-siret');
    var siretInput  = document.getElementById('siret');
    var resultDiv   = document.getElementById('siret-result');
    var companyInput = document.getElementById('company_name');

    if (!btnVerify) return;

    btnVerify.addEventListener('click', function () {
        var siret = siretInput.value.replace(/\s+/g, '');
        if (siret.length !== 14 || !/^\d{14}$/.test(siret)) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color:var(--danger)"><i class="bi bi-x-circle"></i> Le SIRET doit contenir exactement 14 chiffres.</span>';
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<span style="color:var(--text-muted)"><i class="bi bi-hourglass-split"></i> Vérification en cours…</span>';
        btnVerify.disabled = true;

        fetch('/profile/verify-siret?siret=' + encodeURIComponent(siret))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.valid) {
                    resultDiv.innerHTML = '<span style="color:var(--success)"><i class="bi bi-check-circle"></i> SIRET valide — ' + (data.company_name || '') + '</span>';
                    if (data.company_name && !companyInput.value) {
                        companyInput.value = data.company_name;
                    }
                } else {
                    resultDiv.innerHTML = '<span style="color:var(--danger)"><i class="bi bi-x-circle"></i> ' + (data.error || 'SIRET introuvable') + '</span>';
                }
            })
            .catch(function () {
                resultDiv.innerHTML = '<span style="color:var(--danger)"><i class="bi bi-exclamation-triangle"></i> Erreur lors de la vérification.</span>';
            })
            .finally(function () { btnVerify.disabled = false; });
    });
})();
</script>
