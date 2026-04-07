<div class="page-header">
    <div>
        <h1><i class="bi bi-percent"></i> Intérêts <?= (int) $interest['year'] ?></h1>
        <p class="page-description">
            Compte « <?= e($account['name']) ?> » — confirmation du versement
        </p>
    </div>
    <a href="/interests" class="btn btn-outline btn-sm">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="card" style="max-width:600px;margin:0 auto;">
    <div class="card-body">

        <table class="table" style="margin-bottom:1.5rem;">
            <tbody>
                <tr>
                    <td class="text-muted">Solde actuel du compte</td>
                    <td><strong><?= number_format($balance, 2, ',', ' ') ?> <?= e($account['currency']) ?></strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Taux annuel appliqué</td>
                    <td><?= number_format((float) $interest['rate'] * 100, 2, ',', ' ') ?> %</td>
                </tr>
                <tr>
                    <td class="text-muted">Intérêts calculés (prorata <?= (int) $interest['year'] ?>)</td>
                    <td>
                        <strong class="text-success">
                            <?= number_format((float) $interest['calculated_amount'], 2, ',', ' ') ?> <?= e($account['currency']) ?>
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Maximum théorique autorisé</td>
                    <td><?= number_format((float) $interest['max_amount'], 2, ',', ' ') ?> <?= e($account['currency']) ?></td>
                </tr>
            </tbody>
        </table>

        <form method="POST" action="/interests/<?= (int) $interest['id'] ?>/confirm" id="interest-form">
            <?= csrf_field() ?>
            <input type="hidden" name="choice" id="choice-input" value="yes">

            <p style="font-size:1.05rem;margin-bottom:1rem;">
                Le montant calculé
                (<strong><?= number_format((float) $interest['calculated_amount'], 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>)
                vous convient-il ?
            </p>

            <div style="display:flex;gap:0.75rem;margin-bottom:1.25rem;">
                <button type="button" id="btn-yes" class="btn btn-success" style="flex:1;" onclick="chooseYes()">
                    <i class="bi bi-check-lg"></i> Oui, verser ce montant
                </button>
                <button type="button" id="btn-no" class="btn btn-outline" style="flex:1;" onclick="chooseNo()">
                    <i class="bi bi-pencil"></i> Non, modifier
                </button>
            </div>

            <div id="custom-section" style="display:none;">
                <div class="form-group">
                    <label for="custom_amount" class="form-label">
                        Montant des intérêts (<?= e($account['currency']) ?>)
                    </label>
                    <input type="number" id="custom_amount" name="custom_amount" class="form-control"
                           min="0.01" step="0.01"
                           max="<?= htmlspecialchars((string) $interest['max_amount'], ENT_QUOTES) ?>"
                           placeholder="<?= htmlspecialchars(number_format((float) $interest['calculated_amount'], 2, '.', ''), ENT_QUOTES) ?>">
                    <span class="form-hint">
                        Maximum autorisé :
                        <strong><?= number_format((float) $interest['max_amount'], 2, ',', ' ') ?> <?= e($account['currency']) ?></strong>
                    </span>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Verser ces intérêts
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function chooseYes() {
    document.getElementById('choice-input').value = 'yes';
    document.getElementById('interest-form').submit();
}
function chooseNo() {
    document.getElementById('choice-input').value = 'no';
    document.getElementById('btn-yes').className = 'btn btn-outline';
    document.getElementById('btn-yes').style.flex = '1';
    document.getElementById('btn-no').className = 'btn btn-primary';
    document.getElementById('btn-no').style.flex = '1';
    document.getElementById('custom-section').style.display = '';
    document.getElementById('custom_amount').focus();
}
</script>
