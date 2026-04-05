<?php
/**
 * @var array  $types    Ticket::TYPES
 * @var array  $accounts Comptes accessibles par l'utilisateur
 */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-plus-square"></i> Nouvelle demande</h1>
        <p class="page-description">Soumettez une demande qui nécessite l'intervention de la modération.</p>
    </div>
    <a href="/tickets" class="btn btn-outline"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="bi bi-pencil-square"></i> Formulaire de demande</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/tickets" id="ticket-form">
            <?= csrf_field() ?>

            <!-- Type -->
            <div class="form-group">
                <label for="type" class="form-label">Type de demande <span style="color:var(--danger)">*</span></label>
                <select name="type" id="type" class="form-control" required onchange="updateTypeHint(this.value)">
                    <option value="">— Choisissez le type —</option>
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="type-hint" class="form-hint" style="display:none;margin-top:0.35rem;font-size:0.82rem;color:var(--text-secondary);"></div>
            </div>

            <!-- Compte associé (facultatif) -->
            <?php if (!empty($accounts)): ?>
            <div class="form-group">
                <label for="account_id" class="form-label">Compte concerné <span style="color:var(--text-secondary);font-size:0.8rem;">(facultatif)</span></label>
                <select name="account_id" id="account_id" class="form-control">
                    <option value="">— Aucun compte spécifique —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?> (<?= e($acc['currency']) ?>)
                            <?php if (!empty($acc['user_name']) && $acc['user_name'] !== ($currentUserName ?? '')): ?>
                                — <?= e($acc['user_name']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Sujet -->
            <div class="form-group">
                <label for="subject" class="form-label">Sujet <span style="color:var(--danger)">*</span></label>
                <input type="text" name="subject" id="subject" class="form-control"
                       placeholder="Résumez votre demande en quelques mots" required
                       minlength="5" maxlength="255">
            </div>

            <!-- Corps -->
            <div class="form-group">
                <label for="body" class="form-label">Description détaillée <span style="color:var(--danger)">*</span></label>
                <textarea name="body" id="body" class="form-control"
                          rows="6" required minlength="10"
                          placeholder="Décrivez précisément votre demande : contexte, montants, comptes concernés…"></textarea>
                <div style="font-size:0.78rem;color:var(--text-secondary);margin-top:0.3rem;">Minimum 10 caractères.</div>
            </div>

            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem;">
                <a href="/tickets" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send"></i> Envoyer la demande
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var TYPE_HINTS = {
    'direct_debit_request':   'Demandez la mise en place d\'un prélèvement automatique récurrent sur l\'un de vos comptes.',
    'minor_proxy':            'Demandez une procuration pour accéder au compte d\'un mineur dont vous êtes responsable légal.',
    'minor_account_create':   'Demandez la création d\'un compte bancaire dédié à un enfant mineur.',
    'minor_overdraft_access': 'Demandez l\'ajout d\'un mineur sur un compte avec découvert autorisé (compte courant, professionnel ou joint). Cette opération nécessite une dérogation de modération.',
    'account_freeze':         'Demandez le gel ou le dégel d\'un de vos comptes (perte de carte, suspicion de fraude, etc.).',
    'transfer_request':       'Demandez un virement exceptionnel qui ne peut pas être effectué via le formulaire standard.',
    'account_access':         'Demandez l\'accès à un compte sur lequel vous n\'êtes pas propriétaire.',
    'other':                  'Toute autre demande ne correspondant pas aux catégories ci-dessus.',
};
function updateTypeHint(val) {
    var hint = document.getElementById('type-hint');
    if (val && TYPE_HINTS[val]) {
        hint.textContent = TYPE_HINTS[val];
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}
</script>
