<div class="auth-container" style="max-width:520px">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-pencil"></i> Modifier le compte</h2>
            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/edit">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="name" class="form-label">Nom du compte</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= e($account['name']) ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="account_type" class="form-label">Type de compte</label>
                    <select id="account_type" name="account_type" class="form-control" required>
                        <?php foreach ($accountTypes as $key => $info): ?>
                            <option value="<?= e($key) ?>"
                                    data-no-overdraft="<?= $info['overdraft'] ? '0' : '1' ?>"
                                    data-has-cap="<?= !empty($info['cap']) ? '1' : '0' ?>"
                                    <?= ($account['type'] ?? 'standard') === $key ? 'selected' : '' ?>>
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
                            <?php
                            $currencies = ['EUR' => 'EUR (€)', 'USD' => 'USD ($)', 'GBP' => 'GBP (£)', 'CHF' => 'CHF (Fr)', 'CAD' => 'CAD ($)', 'JPY' => 'JPY (¥)', 'XOF' => 'XOF (CFA)', 'MAD' => 'MAD (DH)'];
                            foreach ($currencies as $code => $label): ?>
                                <option value="<?= $code ?>" <?= $account['currency'] === $code ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="overdraft-group">
                        <label for="overdraft" class="form-label">Découvert autorisé</label>
                        <input type="number" id="overdraft" name="overdraft" class="form-control"
                               value="<?= e((string) ($account['overdraft'] ?? 0)) ?>" min="0" step="0.01">
                    </div>
                </div>
                <div id="overdraft-blocked-notice" class="alert alert-warning" style="display:none;">
                    <i class="bi bi-slash-circle"></i>
                    Ce type de compte <strong>n'autorise pas le découvert</strong>.
                </div>
                <div class="form-group" id="cap-group" style="display:none;">
                    <label for="cap" class="form-label">Plafond d'épargne</label>
                    <input type="number" id="cap" name="cap" class="form-control"
                           value="<?= e((string) ($account['cap'] ?? '')) ?>"
                           min="0" step="0.01" placeholder="Ex : 50000.00">
                    <span class="form-hint">Solde maximum autorisé (0 ou vide = pas de plafond)</span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-check-lg"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                <a href="/accounts/<?= (int) $account['id'] ?>"><i class="bi bi-arrow-left"></i> Retour au compte</a>
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
