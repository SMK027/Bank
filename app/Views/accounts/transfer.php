<?php
/**
 * @var array $account
 * @var array $owner
 * @var int   $cardsCount
 * @var int   $checkbooksCount
 */
?>
<div class="auth-container" style="max-width:560px;">
    <div class="card">
        <div class="card-body">
            <h2><i class="bi bi-person-gear"></i> Transférer le compte bancaire</h2>

            <div class="alert alert-info" style="margin-top:1rem;margin-bottom:1.25rem;font-size:0.9rem;">
                <i class="bi bi-info-circle-fill"></i>
                Vous vous apprêtez à transférer la propriété du compte <strong><?= e($account['name']) ?></strong>.
            </div>

            <div class="account-summary-box" style="padding:1rem;background:var(--surface-2, #f8fafc);border:1px solid var(--border-color, #e2e8f0);border-radius:12px;margin-bottom:1.25rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                    <strong>Compte :</strong>
                    <span><?= e($account['name']) ?> (<?= e($account['currency']) ?>)</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                    <strong>Propriétaire actuel :</strong>
                    <span><?= e($owner['username'] ?? 'Inconnu') ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                    <strong>Cartes bancaires raccordées :</strong>
                    <span class="badge bg-primary"><?= (int) $cardsCount ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong>Chéquiers raccordés :</strong>
                    <span class="badge bg-primary"><?= (int) $checkbooksCount ?></span>
                </div>
            </div>

            <div class="alert alert-warning" style="margin-bottom:1.5rem;font-size:0.88rem;line-height:1.45;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Conséquences du transfert :</strong>
                <ul style="margin:0.5rem 0 0 1.25rem;padding:0;">
                    <li>La propriété du compte sera cédée au destinataire.</li>
                    <li>L'accès et la gestion des <strong>cartes bancaires (<?= (int) $cardsCount ?>)</strong> et <strong>chéquiers (<?= (int) $checkbooksCount ?>)</strong> liés à ce compte seront automatiquement transférés au nouveau propriétaire.</li>
                    <li>Le nouveau propriétaire pourra effectuer toutes les opérations autorisées sur le compte et ses moyens de paiement.</li>
                </ul>
            </div>

            <form method="POST" action="/accounts/<?= (int) $account['id'] ?>/transfer">
                <?= csrf_field() ?>

                <div class="form-group mb-3">
                    <label for="recipient" class="form-label">
                        <i class="bi bi-person"></i> Email ou Nom d'utilisateur du nouveau propriétaire <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="recipient" name="recipient" class="form-control"
                           placeholder="Ex : jean.dupont@example.com ou jean_dupont" required autofocus>
                    <span class="form-hint">Saisissez l'adresse email ou le nom d'utilisateur exact du destinataire.</span>
                </div>

                <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
                    <a href="/accounts/<?= (int) $account['id'] ?>" class="btn btn-outline" style="flex:1;">
                        <i class="bi bi-arrow-left"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary" style="flex:2;" onclick="return confirm('Confirmer le transfert de ce compte et de tous ses moyens de paiement raccordés ?');">
                        <i class="bi bi-check-circle"></i> Confirmer le transfert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
