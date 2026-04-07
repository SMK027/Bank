<div class="page-header">
    <h1><i class="bi bi-person-circle"></i> Mon profil</h1>
</div>

<div class="profile-header">
    <div class="profile-avatar">
        <?= strtoupper(substr(e($user['username']), 0, 1)) ?>
    </div>
    <div class="profile-info">
        <h2><?= e($user['username']) ?></h2>
        <p class="profile-role">
            <?php if ($user['global_role'] === 'moderator'): ?>
                <span class="badge badge-mod"><i class="bi bi-shield-check"></i> Modérateur</span>
            <?php else: ?>
                <span class="badge badge-secondary"><i class="bi bi-person"></i> Utilisateur</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="grid grid-2" style="gap:1.5rem;align-items:start">

    <!-- Informations du compte -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Informations</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width:40%">Nom d'utilisateur</th>
                        <td><?= e($user['username']) ?></td>
                    </tr>
                    <tr>
                        <th>Adresse email</th>
                        <td><?= e($user['email']) ?></td>
                    </tr>
                    <tr>
                        <th>Rôle</th>
                        <td>
                            <?php if ($user['global_role'] === 'moderator'): ?>
                                <span class="badge badge-mod">Modérateur</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Utilisateur</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Membre depuis</th>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Changer le mot de passe -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-lock"></i> Changer le mot de passe</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/profile/password">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="current_password" class="form-label">Mot de passe actuel</label>
                    <div class="password-wrapper">
                        <input type="password" id="current_password" name="current_password"
                               class="form-control" required>
                        <button type="button" class="btn-toggle-password"
                                data-target="current_password" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password"
                               class="form-control" required minlength="8">
                        <button type="button" class="btn-toggle-password"
                                data-target="new_password" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control" required minlength="8">
                        <button type="button" class="btn-toggle-password"
                                data-target="confirm_password" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Mettre à jour
                </button>
            </form>
        </div>
    </div>

</div>

<!-- Section profil professionnel -->
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-briefcase"></i> Statut professionnel</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($user['is_professional'])): ?>
            <div class="alert alert-success" style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;">
                <i class="bi bi-check-circle" style="font-size:1.2rem;"></i>
                <span>
                    <strong>Professionnel vérifié</strong><br>
                    Raison sociale : <strong><?= e($user['company_name']) ?></strong> — SIRET : <code><?= e($user['siret']) ?></code>
                </span>
            </div>
            <a href="/profile/professional" class="btn btn-secondary btn-block">
                <i class="bi bi-pencil"></i> Gérer le statut professionnel
            </a>
        <?php else: ?>
            <p class="text-muted">Vous n'avez pas de statut professionnel. Activez-le pour accéder aux comptes bancaires professionnels.</p>
            <a href="/profile/professional" class="btn btn-primary btn-block">
                <i class="bi bi-briefcase"></i> Activer le statut professionnel
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Section numéro de compte et code PIN -->
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-shield-lock"></i> Connexion par numéro de compte &amp; PIN</h3>
    </div>
    <div class="card-body">

        <!-- Numéro de compte -->
        <div style="margin-bottom:1.5rem;">
            <p class="text-muted" style="margin-bottom:0.5rem;">
                Votre numéro de compte vous permet de vous connecter sans saisir votre email.
            </p>
            <?php if (!empty($user['account_number'])): ?>
                <div style="display:flex;align-items:flex-start;gap:1.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
                            <code style="font-size:1.15rem;letter-spacing:0.12em;background:var(--bg-secondary,#f5f5f5);padding:0.4rem 0.75rem;border-radius:6px;">
                                <?= e($user['account_number']) ?>
                            </code>
                        </div>
                        <p class="text-muted text-small" style="margin:0;">
                            Utilisez ce numéro ou scannez le QR code pour vous connecter par PIN.
                        </p>
                    </div>
                    <div style="text-align:center;">
                        <img src="<?= e($qrDataUri) ?>"
                             alt="QR Code connexion PIN"
                             width="140" height="140"
                             id="qr-thumbnail"
                             data-qr-src="<?= e($qrDataUri) ?>"
                             style="border-radius:8px;border:1px solid var(--gray-light);display:block;cursor:zoom-in;"
                             title="Cliquer pour agrandir">
                        <p class="text-muted text-small mt-1" style="margin-bottom:0;">
                            <i class="bi bi-zoom-in"></i> Cliquer pour agrandir
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">Aucun numéro de compte attribué.</p>
            <?php endif; ?>
            <form method="POST" action="/profile/account-number/reset"
                  onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser votre numéro de compte ? L\'ancien numéro ne sera plus utilisable pour vous connecter.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-arrow-repeat"></i>
                    <?= !empty($user['account_number']) ? 'Réinitialiser le numéro de compte' : 'Générer un numéro de compte' ?>
                </button>
            </form>
        </div>

        <!-- Code PIN -->
        <hr style="margin:0 0 1.25rem;">
        <?php if (!empty($user['pin_hash'])): ?>
            <p class="text-muted" style="margin-bottom:0.75rem;">
                Un code PIN est déjà défini. Vous pouvez le modifier ci-dessous.
            </p>
            <form method="POST" action="/profile/pin">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="current_pin" class="form-label">Code PIN actuel</label>
                    <div class="password-wrapper">
                        <input type="password" id="current_pin" name="current_pin"
                               class="form-control" inputmode="numeric" pattern="\d{6}"
                               maxlength="6" placeholder="••••••" required>
                        <button type="button" class="btn-toggle-password"
                                data-target="current_pin" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_pin" class="form-label">Nouveau code PIN (6 chiffres)</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_pin" name="new_pin"
                               class="form-control" inputmode="numeric" pattern="\d{6}"
                               maxlength="6" placeholder="••••••" required>
                        <button type="button" class="btn-toggle-password"
                                data-target="new_pin" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_pin" class="form-label">Confirmer le nouveau code PIN</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_pin" name="confirm_pin"
                               class="form-control" inputmode="numeric" pattern="\d{6}"
                               maxlength="6" placeholder="••••••" required>
                        <button type="button" class="btn-toggle-password"
                                data-target="confirm_pin" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Modifier le code PIN
                </button>
            </form>
        <?php else: ?>
            <p class="text-muted" style="margin-bottom:0.75rem;">
                Vous n'avez pas encore de code PIN. Définissez-en un pour pouvoir vous connecter sans email.
            </p>
            <form method="POST" action="/profile/pin">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="new_pin" class="form-label">Code PIN (6 chiffres)</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_pin" name="new_pin"
                               class="form-control" inputmode="numeric" pattern="\d{6}"
                               maxlength="6" placeholder="••••••" required>
                        <button type="button" class="btn-toggle-password"
                                data-target="new_pin" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_pin" class="form-label">Confirmer le code PIN</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_pin" name="confirm_pin"
                               class="form-control" inputmode="numeric" pattern="\d{6}"
                               maxlength="6" placeholder="••••••" required>
                        <button type="button" class="btn-toggle-password"
                                data-target="confirm_pin" title="Afficher">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-shield-check"></i> Définir le code PIN
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>

<!-- Modal agrandissement QR code -->
<div id="qr-modal"
     role="dialog"
     aria-modal="true"
     aria-label="QR Code agrandi"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,0.75);align-items:center;justify-content:center;">
    <div style="position:relative;background:#fff;border-radius:12px;padding:1.5rem;
                box-shadow:0 8px 40px rgba(0,0,0,0.4);text-align:center;max-width:90vw;">
        <button id="qr-modal-close"
                aria-label="Fermer"
                style="position:absolute;top:0.5rem;right:0.75rem;background:none;
                       border:none;font-size:1.5rem;cursor:pointer;color:#666;line-height:1;"
                title="Fermer">&times;</button>
        <img id="qr-modal-img" src="" alt="QR Code connexion PIN agrandi"
             style="width:280px;height:280px;display:block;border-radius:6px;">
        <p style="margin:0.75rem 0 0;font-size:0.85rem;color:#666;">
            <i class="bi bi-qr-code-scan"></i> Scannez ce QR code pour vous connecter par PIN
        </p>
    </div>
</div>

<script>
(function () {
    var thumb  = document.getElementById('qr-thumbnail');
    var modal  = document.getElementById('qr-modal');
    var img    = document.getElementById('qr-modal-img');
    var close  = document.getElementById('qr-modal-close');

    function openModal() {
        img.src = thumb.dataset.qrSrc;
        modal.style.display = 'flex';
        close.focus();
    }

    function closeModal() {
        modal.style.display = 'none';
        thumb.focus();
    }

    thumb.addEventListener('click', openModal);
    thumb.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openModal(); }
    });

    close.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { closeModal(); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') { closeModal(); }
    });
}());
</script>
