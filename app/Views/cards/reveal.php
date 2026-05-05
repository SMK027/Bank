<?php
/** @var array $card */
/** @var ?string $pan */
/** @var ?string $error */
$error = $error ?? null;
$pan   = $pan ?? null;
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-eye"></i> Afficher le numéro de carte</h1>
    <a href="/cards" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-body">
        <p class="text-muted" style="margin-top:0;">
            Carte
            <code style="letter-spacing:0.1em;">
                <?= e(\App\Models\PaymentCard::mask($card['card_number'])) ?>
            </code>
            <?php if (!empty($card['label'])): ?>
                — <?= e($card['label']) ?>
            <?php endif; ?>
        </p>

        <?php if ($pan !== null): ?>
            <div class="alert alert-success" role="alert" style="display:flex;align-items:flex-start;gap:0.75rem;">
                <i class="bi bi-check-circle-fill" style="font-size:1.3rem;flex-shrink:0;"></i>
                <div style="flex:1;">
                    <strong>Numéro complet :</strong>
                    <div style="margin-top:0.5rem;">
                        <code style="font-size:1.25rem;letter-spacing:0.15em;background:#fff;padding:0.45rem 0.8rem;border-radius:6px;border:1px solid var(--gray-light);">
                            <?= e(chunk_split($pan, 4, ' ')) ?>
                        </code>
                    </div>
                    <p class="text-muted text-small" style="margin-top:0.5rem;margin-bottom:0;">
                        Quittez cette page après avoir noté le numéro. Toute consultation est tracée dans le journal d'audit.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <p>
                Pour des raisons de sécurité, veuillez confirmer votre mot de passe avant
                d'afficher le numéro complet de cette carte.
            </p>

            <form method="POST" action="/cards/<?= (int) $card['id'] ?>/reveal" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control"
                           required autofocus autocomplete="current-password">
                </div>
                <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
                    <a href="/cards" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Afficher le numéro
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
