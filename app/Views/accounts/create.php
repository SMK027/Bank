<div class="auth-container" style="max-width:520px">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-plus-circle"></i> Créer un compte bancaire</h2>
            <?php if ($isMinor): ?>
            <div class="alert alert-warning" style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                <i class="bi bi-person-arms-up" style="font-size:1.2rem;"></i>
                <span>
                    <strong>Profil mineur</strong> — Seuls les comptes
                    <strong>Épargne</strong> sont disponibles.
                    Les comptes mineurs sont ouverts uniquement par la modération.
                </span>
            </div>
            <?php elseif (!empty($isPro)): ?>
            <div class="alert alert-info" style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                <i class="bi bi-briefcase" style="font-size:1.2rem;"></i>
                <span>
                    <strong>Profil professionnel</strong> — Seuls les comptes
                    <strong>Professionnel</strong> et <strong>Épargne</strong> sont disponibles.
                </span>
            </div>
            <?php else: ?>
            <div class="alert alert-info" style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                <i class="bi bi-briefcase" style="font-size:1.2rem;"></i>
                <span>
                    Vous souhaitez un <strong>compte professionnel</strong> ?
                    <a href="/profile/professional">Activez votre statut professionnel</a> en renseignant votre SIRET.
                </span>
            </div>
            <?php endif; ?>
            <form method="POST" action="/accounts/create">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="name" class="form-label">Nom du compte</label>
                    <input type="text" id="name" name="name" class="form-control"
                           placeholder="Ex : Compte courant, Épargne vacances..." required autofocus>
                </div>
                <div class="form-group">
                    <label for="account_type" class="form-label">Type de compte</label>
                    <select id="account_type" name="account_type" class="form-control" required>
                        <?php foreach ($accountTypes as $key => $info): ?>
                            <option value="<?= e($key) ?>"
                                    data-no-overdraft="<?= $info['overdraft'] ? '0' : '1' ?>"
                                    data-has-cap="<?= !empty($info['cap']) ? '1' : '0' ?>">
                                <?= e($info['label']) ?>
                                <?= $info['overdraft'] ? '' : ' — découvert interdit' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                    <div class="form-group" id="overdraft-group">
                        <label for="overdraft" class="form-label">Découvert autorisé</label>
                        <input type="number" id="overdraft" name="overdraft" class="form-control"
                               inputmode="decimal" placeholder="0.00" min="0" step="0.01" value="0">
                        <span class="form-hint">Montant maximal de découvert autorisé</span>
                    </div>
                </div>
                <div id="overdraft-blocked-notice" class="alert alert-warning" style="display:none;">
                    <i class="bi bi-slash-circle"></i>
                    Ce type de compte <strong>n'autorise pas le découvert</strong>.
                </div>
                <div class="form-group" id="cap-group" style="display:none;">
                    <label for="cap" class="form-label">Plafond d'épargne</label>
                    <input type="number" id="cap" name="cap" class="form-control"
                           inputmode="decimal" placeholder="Ex : 50000.00" min="0" step="0.01" value="">
                    <span class="form-hint">Solde maximum autorisé (0 ou vide = pas de plafond)</span>
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
<script>
(function () {
    var typeEl  = document.getElementById('account_type');
    var group   = document.getElementById('overdraft-group');
    var notice  = document.getElementById('overdraft-blocked-notice');
    var input   = document.getElementById('overdraft');
    var capGrp  = document.getElementById('cap-group');
    function toggle() {
        var opt    = typeEl.options[typeEl.selectedIndex];
        var noOd   = opt.dataset.noOverdraft === '1';
        var hasCap = opt.dataset.hasCap === '1';
        group.style.display  = noOd ? 'none' : '';
        notice.style.display = noOd && !hasCap ? 'block' : 'none';
        capGrp.style.display = hasCap ? '' : 'none';
        if (noOd) { input.value = '0'; }
        if (!hasCap) { document.getElementById('cap').value = ''; }
    }
    typeEl.addEventListener('change', toggle);
    toggle();
})();
</script>
