<div class="page-header">
    <div>
        <h1><i class="bi bi-person-plus"></i> Nouveau compte mineur</h1>
        <p class="page-description">Créer un compte bancaire pour un utilisateur mineur et désigner ses responsables légaux</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Tutelles légales
        </a>
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-bank"></i> Comptes
        </a>
    </div>
</div>

<?php if (empty($minorUsers)): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;gap:0.75rem;">
    <i class="bi bi-exclamation-triangle" style="font-size:1.3rem;"></i>
    <div>
        <strong>Aucun utilisateur mineur disponible.</strong><br>
        Pour créer un compte mineur, un utilisateur avec une date de naissance indiquant moins de 18 ans doit exister.
        Vérifiez les profils utilisateurs dans la <a href="/moderation/users">gestion des utilisateurs</a>.
    </div>
</div>
<?php else: ?>

<div class="alert" style="background:rgba(var(--warning-rgb,245,158,11),0.1);border-left:4px solid var(--warning,#f59e0b);display:flex;align-items:flex-start;gap:0.75rem;padding:1rem 1.2rem;">
    <i class="bi bi-shield-lock" style="font-size:1.3rem;color:var(--warning,#f59e0b);flex-shrink:0;"></i>
    <div style="font-size:0.88rem;">
        <strong>Compte à supervision légale obligatoire.</strong><br>
        Un compte mineur est soumis à la tutelle d'un ou deux adultes désignés comme responsables légaux.
        Ces personnes auront automatiquement <strong>procuration sur tous les comptes du mineur</strong> tant qu'il n'est pas majeur.
        À ses 18 ans, toutes les procurations expirent et doivent être remises en place manuellement.
    </div>
</div>

<div class="card" style="max-width:620px;margin:1.5rem auto 0;">
    <div class="card-body">
        <form method="POST" action="/moderation/minor-accounts" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-person-badge"></i> Utilisateur mineur
            </h3>

            <div class="form-group">
                <label for="minor_user_id" class="form-label">
                    Sélectionner le mineur <span style="color:var(--danger)">*</span>
                </label>
                <select id="minor_user_id" name="minor_user_id" class="form-control" required>
                    <option value="">— Choisir un utilisateur mineur —</option>
                    <?php foreach ($minorUsers as $u): ?>
                        <option value="<?= (int) $u['id'] ?>">
                            <?= e($u['username']) ?>
                            <?php if (!empty($u['birth_date'])): ?>
                                — né(e) le <?= date('d/m/Y', strtotime($u['birth_date'])) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:0.76rem;color:var(--text-muted);margin-top:2px;">
                    Seuls les utilisateurs avec date de naissance inférieure à 18 ans sont affichés.
                </div>
            </div>

            <hr style="margin:1.2rem 0;border-color:var(--border-color);">
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-wallet2"></i> Détails du compte
            </h3>

            <div class="form-group">
                <label for="name" class="form-label">
                    Nom du compte <span style="color:var(--danger)">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Ex : Compte mineur de Léa" maxlength="255" required autofocus>
            </div>

            <div class="form-group">
                <label for="currency" class="form-label">Devise</label>
                <select id="currency" name="currency" class="form-control">
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

            <hr style="margin:1.2rem 0;border-color:var(--border-color);">
            <h3 style="margin:0 0 1rem;font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
                <i class="bi bi-people"></i> Responsables légaux
            </h3>

            <?php if (empty($adultUsers)): ?>
            <div class="alert alert-warning" style="font-size:0.88rem;">
                <i class="bi bi-exclamation-triangle"></i>
                Aucun utilisateur adulte disponible pour être désigné responsable légal.
            </div>
            <?php else: ?>

            <div class="form-group">
                <label for="guardian_1_id" class="form-label">
                    Responsable légal principal <span style="color:var(--danger)">*</span>
                </label>
                <select id="guardian_1_id" name="guardian_1_id" class="form-control" required>
                    <option value="">— Choisir un adulte —</option>
                    <?php foreach ($adultUsers as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="guardian_2_id" class="form-label">
                    Second responsable légal
                    <span style="color:var(--text-muted);font-size:0.8rem;">(facultatif — max 2)</span>
                </label>
                <select id="guardian_2_id" name="guardian_2_id" class="form-control">
                    <option value="">— Aucun —</option>
                    <?php foreach ($adultUsers as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php endif; ?>

            <div class="form-group" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary btn-block"
                    <?= empty($adultUsers) ? 'disabled' : '' ?>>
                    <i class="bi bi-check-lg"></i> Créer le compte mineur
                </button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>
