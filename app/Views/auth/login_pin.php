<?php
$pinDigits = range(0, 9);
shuffle($pinDigits);
$prefilled = $prefilled ?? '';
?>
<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-shield-lock"></i> Connexion par code PIN</h2>
            <form method="POST" action="/login/pin" id="pin-login-form" autocomplete="off">
                <?= csrf_field() ?>

                <!-- Numéro de compte -->
                <div class="form-group">
                    <label for="account_number" class="form-label">Numéro de compte</label>
                    <input type="text" id="account_number" name="account_number" class="form-control"
                           placeholder="BKxxxxxxxx" required
                           <?= $prefilled === '' ? 'autofocus' : '' ?>
                           pattern="BK\d{8}" maxlength="10"
                           value="<?= e($prefilled) ?>"
                           style="text-transform:uppercase;letter-spacing:0.08em;">
                    <?php if ($prefilled !== ''): ?>
                    <div class="text-muted text-small mt-1">
                        <i class="bi bi-qr-code-scan"></i> Numéro pré-rempli via QR code — vous pouvez le modifier.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Affichage du PIN (points) -->
                <div class="form-group">
                    <label class="form-label">Code PIN <span class="text-muted" style="font-size:0.78rem;font-weight:400">(6 chiffres)</span></label>
                    <div class="pin-display" id="pin-display" role="status" aria-live="polite" aria-label="Code PIN">
                        <span></span><span></span><span></span>
                        <span></span><span></span><span></span>
                    </div>
                    <!-- Champ caché — rempli uniquement par le pavé numérique -->
                    <input type="hidden" name="pin" id="pin-value">
                </div>

                <!-- Pavé numérique (ordre aléatoire côté serveur) -->
                <div class="pin-pad" id="pin-pad" role="group" aria-label="Pavé numérique">
                    <?php foreach ($pinDigits as $d): ?>
                        <button type="button" class="pin-key"
                                data-digit="<?= $d ?>"
                                aria-label="Chiffre <?= $d ?>">
                            <?= $d ?>
                        </button>
                    <?php endforeach; ?>
                    <button type="button" class="pin-key pin-key-del" id="pin-del"
                            aria-label="Effacer le dernier chiffre">
                        <i class="bi bi-backspace" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Bouton connexion (désactivé tant que 6 chiffres non saisis) -->
                <button type="submit" class="btn btn-primary btn-block" id="pin-submit" disabled>
                    <i class="bi bi-shield-check"></i> Se connecter
                </button>
            </form>

            <p class="text-center text-muted text-small mt-2">
                Connexion classique ? <a href="/login">Email &amp; mot de passe</a>
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var pin    = '';
    var MAX    = 6;
    var dots   = document.querySelectorAll('#pin-display span');
    var hidden = document.getElementById('pin-value');
    var submit = document.getElementById('pin-submit');

    function refresh() {
        dots.forEach(function (dot, i) {
            dot.classList.toggle('filled', i < pin.length);
        });
        // N'écrire dans le champ caché qu'une fois le PIN complet
        hidden.value = (pin.length === MAX) ? pin : '';
        submit.disabled = (pin.length !== MAX);
    }

    // Touches numériques
    document.querySelectorAll('.pin-key[data-digit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (pin.length < MAX) {
                pin += this.dataset.digit;
                refresh();
            }
        });
    });

    // Effacer
    document.getElementById('pin-del').addEventListener('click', function () {
        if (pin.length > 0) {
            pin = pin.slice(0, -1);
            refresh();
        }
    });

    // Bloquer toute saisie clavier dans le formulaire sur le champ caché
    document.getElementById('pin-login-form').addEventListener('keydown', function (e) {
        if (e.target.id === 'account_number') return; // autoriser la saisie du n° de compte
        if (e.key === 'Enter' && pin.length === MAX) return; // autoriser Enter pour valider
        if (e.target.type === 'hidden' || e.target.type === 'submit') e.preventDefault();
    });

    refresh();

    // Si numéro pré-rempli via QR code, déplacer le focus sur le pavé PIN
    <?php if ($prefilled !== ''): ?>
    var firstKey = document.querySelector('.pin-key[data-digit]');
    if (firstKey) firstKey.focus();
    <?php endif; ?>
}());
</script>
