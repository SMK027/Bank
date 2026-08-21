<?php
/** @var string  $featureKey */
/** @var string  $featureLabel */
/** @var string  $redirectUrl */
/** @var string|null $error */
/** @var bool|null $isStepUp */

$isStepUp = (bool) ($isStepUp ?? false);
?>
<div style="max-width:420px;margin:4rem auto;padding:0 1rem;">
    <div class="card" style="border-radius:16px;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);padding:1.75rem 2rem;text-align:center;color:#fff;">
            <i class="bi bi-person-badge" style="font-size:2.5rem;display:block;margin-bottom:0.5rem;"></i>
            <h1 style="font-size:1.25rem;margin:0;font-weight:700;">Authentification superviseur</h1>
            <p style="margin:0.4rem 0 0;font-size:0.85rem;opacity:0.85;">
                <?php if ($isStepUp): ?>
                    Validation requise — <strong><?= e($featureLabel) ?></strong>
                <?php else: ?>
                    Accès restreint — fonctionnalité <strong><?= e($featureLabel) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <div class="card-body" style="padding:1.75rem 2rem;">
            <?php if ($error !== null): ?>
                <div class="alert alert-danger" style="margin-bottom:1.25rem;">
                    <i class="bi bi-exclamation-triangle"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1.25rem;">
                <?php if ($isStepUp): ?>
                    La prise de main d'un compte utilisateur est active. Chaque opération de modération
                    doit être validée par un superviseur.
                <?php else: ?>
                    Cette fonctionnalité est temporairement désactivée. Un superviseur autorisé
                    peut débloquer provisoirement l'accès pour cette session.
                <?php endif; ?>
            </p>

            <form method="POST" action="/supervisor/bypass" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="feature"  value="<?= e($featureKey) ?>">
                <input type="hidden" name="redirect" value="<?= e($redirectUrl) ?>">

                <div style="margin-bottom:1rem;">
                    <label class="form-label" for="supervisor_id">
                        <i class="bi bi-person"></i> Identifiant superviseur
                    </label>
                    <input type="text" id="supervisor_id" name="supervisor_id"
                           class="form-control" required autofocus
                           autocomplete="off"
                           placeholder="Identifiant de supervision">
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label class="form-label" for="pin">
                        <i class="bi bi-key"></i> Code PIN
                    </label>
                    <input type="password" id="pin" name="pin"
                           class="form-control" required
                           autocomplete="off"
                           placeholder="Code PIN">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="bi bi-unlock"></i> <?= $isStepUp ? 'Valider l\'opération' : 'Débloquer l\'accès' ?>
                </button>
            </form>

            <div style="text-align:center;margin-top:1.25rem;">
                <a href="<?= is_authenticated() ? '/dashboard' : '/' ?>"
                   style="font-size:0.85rem;color:var(--text-muted);">
                    <i class="bi bi-arrow-left"></i> Retour sans déverrouiller
                </a>
            </div>
        </div>
    </div>

    <p style="text-align:center;font-size:0.75rem;color:var(--text-muted);margin-top:1rem;">
        Toute tentative d'authentification est enregistrée dans le journal d'audit.
    </p>
</div>
