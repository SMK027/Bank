<?php /** @var array $eligibleAccounts */ ?>
<div class="auth-container" style="max-width:520px">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-credit-card-2-front"></i> Enregistrer une carte bancaire</h2>
            <p class="text-muted">
                Choisissez les <strong>4 derniers chiffres</strong> de votre future carte. Le reste du numéro
                sera généré automatiquement. Les comptes d'épargne ne peuvent pas être associés à une carte.
            </p>

            <form method="POST" action="/cards">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="account_id" class="form-label">Compte à associer</label>
                    <select id="account_id" name="account_id" class="form-control" required>
                        <?php foreach ($eligibleAccounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>">
                                <?= e($a['name']) ?> — <?= e(\App\Models\Account::TYPES[$a['type']]['label'] ?? $a['type']) ?>
                                (<?= e($a['currency']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="last4" class="form-label">4 derniers chiffres de la carte</label>
                    <input type="text" id="last4" name="last4" class="form-control"
                           pattern="\d{4}" maxlength="4" inputmode="numeric"
                           placeholder="Ex : 4242" required autofocus>
                    <span class="form-hint">Ces 4 chiffres apparaîtront en clair sur votre carte. Le reste reste secret.</span>
                </div>

                <div class="form-group">
                    <label for="label" class="form-label">Libellé (facultatif)</label>
                    <input type="text" id="label" name="label" class="form-control"
                           maxlength="100" placeholder="Ex : Carte courses, Carte voyage…">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;">
                    <div class="form-group">
                        <label for="expires_at" class="form-label">
                            Date d'expiration
                            <span class="text-muted" style="font-weight:normal;font-size:0.85em;">(facultatif, MM/AA)</span>
                        </label>
                        <input type="text" id="expires_at" name="expires_at" class="form-control"
                               maxlength="7" placeholder="Ex : 12/28" inputmode="numeric"
                               pattern="\d{2}/\d{2,4}">
                        <span class="form-hint">Laissez vide pour une carte sans date d'expiration.</span>
                    </div>
                    <div class="form-group">
                        <label for="monthly_limit" class="form-label">
                            Plafond mensuel
                            <span class="text-muted" style="font-weight:normal;font-size:0.85em;">(facultatif)</span>
                        </label>
                        <input type="text" id="monthly_limit" name="monthly_limit" class="form-control"
                               maxlength="12" placeholder="Ex : 500,00" inputmode="decimal">
                        <span class="form-hint">Montant maximum de dépenses par mois calendaire.</span>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="bi bi-check-lg"></i> Enregistrer la carte
                    </button>
                </div>
            </form>

            <p class="text-center text-muted text-small mt-2">
                <a href="/cards"><i class="bi bi-arrow-left"></i> Retour à mes cartes</a>
            </p>
        </div>
    </div>
</div>
