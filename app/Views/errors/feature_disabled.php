<?php
/** @var string $label */
/** @var string $description */
/** @var string $featureKey */
?>
<div class="container" style="max-width:680px;margin:60px auto;text-align:center;">
    <div class="alert alert-warning" style="padding:32px;border-radius:12px;">
        <h1 style="margin-top:0;">
            <i class="bi bi-cone-striped"></i>
            Fonctionnalité temporairement indisponible
        </h1>

        <p style="font-size:1.1rem;">
            La fonctionnalité <strong><?= e($label) ?></strong> a été désactivée
            par l'équipe de modération.
        </p>

        <?php if ($description !== ''): ?>
            <p style="color:#555;"><?= e($description) ?></p>
        <?php endif; ?>

        <p>
            Merci de réessayer plus tard. Si le problème persiste, vous pouvez
            <a href="/tickets">ouvrir un ticket de support</a>.
        </p>

        <p style="margin-top:24px;">
            <a href="<?= is_authenticated() ? '/dashboard' : '/' ?>" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </p>

        <p style="margin-top:16px;font-size:0.8rem;color:#888;">
            Code technique&nbsp;: <code><?= e($featureKey) ?></code>
        </p>
    </div>
</div>
