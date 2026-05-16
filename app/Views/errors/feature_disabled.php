<?php
/** @var string $label */
/** @var string $description */
/** @var string $featureKey */
?>
<div class="feature-disabled">
    <div class="feature-disabled__card">
        <div class="feature-disabled__icon" aria-hidden="true">
            <i class="bi bi-cone-striped"></i>
        </div>

        <h1 class="feature-disabled__title">
            Fonctionnalité temporairement indisponible
        </h1>

        <p class="feature-disabled__lead">
            La fonctionnalité <strong><?= e($label) ?></strong> a été désactivée
            par l'équipe de modération.
        </p>

        <?php if ($description !== ''): ?>
            <p class="feature-disabled__description"><?= e($description) ?></p>
        <?php endif; ?>

        <p class="feature-disabled__text">
            Merci de réessayer plus tard. Si le problème persiste, vous pouvez
            <a href="/tickets">ouvrir un ticket de support</a>.
        </p>

        <div class="feature-disabled__actions">
            <a href="<?= is_authenticated() ? '/dashboard' : '/' ?>" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>

        <p class="feature-disabled__code">
            Code technique&nbsp;: <code><?= e($featureKey) ?></code>
        </p>
    </div>
</div>

<style>
    .feature-disabled {
        max-width: 640px;
        margin: 4rem auto;
        padding: 0 1rem;
    }

    .feature-disabled__card {
        background: linear-gradient(180deg, rgba(255, 209, 102, 0.18), rgba(255, 209, 102, 0.08));
        border: 1px solid rgba(255, 209, 102, 0.5);
        border-radius: 16px;
        padding: 2.5rem 2rem;
        text-align: center;
        color: #92400e;
        box-shadow: 0 10px 30px -15px rgba(146, 64, 14, 0.25);
    }

    .feature-disabled__icon {
        font-size: 3rem;
        line-height: 1;
        margin-bottom: 1rem;
        color: #b45309;
    }

    .feature-disabled__title {
        margin: 0 0 1.25rem;
        font-size: 1.6rem;
        font-weight: 700;
        color: #78350f;
    }

    .feature-disabled__lead {
        font-size: 1.05rem;
        margin: 0 0 1rem;
        line-height: 1.5;
    }

    .feature-disabled__description {
        color: #6b4318;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        margin: 0 0 1rem;
        line-height: 1.5;
    }

    .feature-disabled__text {
        margin: 0 0 1.75rem;
        line-height: 1.5;
    }

    .feature-disabled__text a {
        color: #b45309;
        font-weight: 600;
        text-decoration: underline;
    }

    .feature-disabled__actions {
        margin-bottom: 1.5rem;
    }

    .feature-disabled__code {
        margin: 0;
        font-size: 0.8rem;
        color: #92400e;
        opacity: 0.8;
    }

    .feature-disabled__code code {
        background: rgba(146, 64, 14, 0.1);
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    @media (max-width: 480px) {
        .feature-disabled {
            margin: 2rem auto;
        }
        .feature-disabled__card {
            padding: 1.75rem 1.25rem;
        }
        .feature-disabled__title {
            font-size: 1.35rem;
        }
    }
</style>
