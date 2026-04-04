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
                    <div class="form-group">
                        <label for="overdraft" class="form-label">Découvert autorisé</label>
                        <input type="number" id="overdraft" name="overdraft" class="form-control"
                               value="<?= e((string) ($account['overdraft'] ?? 0)) ?>" min="0" step="0.01">
                    </div>
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
