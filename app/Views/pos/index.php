<?php
/** @var array  $user */
/** @var array  $merchantAccounts */
/** @var array  $form */
/** @var array  $errors */
/** @var ?array $receipt */
$errors  = $errors ?? [];
$form    = $form ?? [];
$receipt = $receipt ?? null;
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-shop"></i> Terminal de paiement (TPE)</h1>
    <span class="badge bg-success"><i class="bi bi-wifi"></i> Raccordé à la plateforme</span>
</div>

<?php if ($receipt): ?>
    <div class="alert alert-success" role="alert" style="display:flex;align-items:flex-start;gap:0.75rem;">
        <i class="bi bi-receipt" style="font-size:1.5rem;flex-shrink:0;"></i>
        <div style="flex:1;">
            <strong>Paiement accepté</strong>
            <div style="margin-top:0.5rem;display:grid;grid-template-columns:max-content 1fr;gap:0.25rem 1rem;font-size:0.95rem;">
                <span class="text-muted">Référence :</span>
                <strong><?= e($receipt['reference']) ?></strong>
                <span class="text-muted">Date :</span>
                <span><?= e($receipt['datetime']) ?></span>
                <span class="text-muted">Commerçant :</span>
                <span><?= e($receipt['merchant']) ?></span>
                <span class="text-muted">Opération :</span>
                <span><?= e($receipt['label']) ?></span>
                <span class="text-muted">Montant :</span>
                <strong><?= number_format((float) $receipt['amount'], 2, ',', ' ') ?> <?= e($receipt['currency']) ?></strong>
                <span class="text-muted">Carte :</span>
                <code><?= e($receipt['card_masked']) ?></code>
                <span class="text-muted">Compte crédité :</span>
                <span><?= e($receipt['merchant_account']) ?></span>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul style="margin:0.25rem 0 0 1.25rem;padding:0;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($merchantAccounts)): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Vous ne disposez d'aucun compte professionnel actif. Le TPE nécessite un compte
        de type <strong>professionnel</strong> pour recevoir les encaissements.
        <?php if (!is_professional()): ?>
            <br><a href="/profile/professional">Activez votre statut professionnel</a> en renseignant votre SIRET.
        <?php else: ?>
            <br><a href="/accounts/create">Créer un compte professionnel</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card" style="max-width:680px;">
        <div class="card-body">
            <p class="text-muted" style="margin-top:0;">
                Saisissez les informations de la transaction. La carte du client sera
                débitée et le compte d'encaissement sélectionné sera crédité du montant.
            </p>

            <form method="POST" action="/pos/charge" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="card_number" class="form-label">
                        <i class="bi bi-credit-card-2-front"></i> Numéro de carte
                    </label>
                    <input type="text" id="card_number" name="card_number" class="form-control"
                           placeholder="4242 4242 4242 4242" required maxlength="23"
                           value="<?= e((string) ($form['card_number'] ?? '')) ?>"
                           inputmode="numeric" autofocus>
                    <small class="text-muted">16 chiffres. Espaces et tirets autorisés.</small>
                </div>

                <div class="form-group">
                    <label for="amount" class="form-label">
                        <i class="bi bi-currency-euro"></i> Montant
                    </label>
                    <input type="text" id="amount" name="amount" class="form-control"
                           placeholder="0,00" required inputmode="decimal"
                           value="<?= e((string) ($form['amount'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="label" class="form-label">
                        <i class="bi bi-tag"></i> Intitulé de l'opération
                    </label>
                    <input type="text" id="label" name="label" class="form-control"
                           placeholder="Ex : Achat sur place" required maxlength="120"
                           value="<?= e((string) ($form['label'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="merchant" class="form-label">
                        <i class="bi bi-shop"></i> Commerçant
                    </label>
                    <input type="text" id="merchant" name="merchant" class="form-control"
                           placeholder="Ex : Boulangerie Dupont" required maxlength="120"
                           value="<?= e((string) ($form['merchant'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="account_id" class="form-label">
                        <i class="bi bi-bank"></i> Compte d'encaissement
                    </label>
                    <select id="account_id" name="account_id" class="form-control" required>
                        <option value="">— Choisir un compte professionnel —</option>
                        <?php foreach ($merchantAccounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"
                                <?= (int) ($form['account_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                                <?= e($a['name']) ?> (<?= e($a['currency']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1.5rem;">
                    <a href="/dashboard" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> Encaisser
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
