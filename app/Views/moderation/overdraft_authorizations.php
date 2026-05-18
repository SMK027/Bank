<?php
/**
 * Modération — Autorisations de dépassement de découvert.
 *
 * Variables : $authorizations, $allAccounts
 */
use App\Models\Account;

$statusLabels = [
    'active'  => ['label' => 'Active',    'class' => 'badge-success'],
    'pending' => ['label' => 'En attente','class' => 'badge-info'],
    'expired' => ['label' => 'Expirée',   'class' => 'badge-secondary'],
    'revoked' => ['label' => 'Révoquée',  'class' => 'badge-danger'],
];
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-plus"></i> Autorisations de dépassement</h1>
        <p class="page-description">Gérez les autorisations permettant à des comptes de dépasser leur découvert habituel.</p>
    </div>
</div>

<!-- ── Formulaire de création ────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header">
        <h3><i class="bi bi-plus-circle"></i> Nouvelle autorisation</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/moderation/overdraft-authorizations/create">
            <?= csrf_field() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Compte cible <span class="text-danger">*</span></label>
                    <select name="account_id" class="form-control" required>
                        <option value="">— Choisir un compte —</option>
                        <?php foreach ($allAccounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>">
                                <?= e($acc['name']) ?> (<?= e($acc['owner_name']) ?>) — <?= e($acc['currency']) ?>
                                <?php if ((float) ($acc['overdraft'] ?? 0) > 0): ?>
                                    — découvert : <?= fmt_amount_smart((float) $acc['overdraft']) ?> <?= e($acc['currency']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Limite supplémentaire <span class="text-danger">*</span></label>
                    <input type="number" name="extra_limit" class="form-control"
                           min="0.01" step="0.01" placeholder="Ex : 500.00" required>
                    <span class="form-hint">Montant ajouté par-dessus le découvert existant</span>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Date de début <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Date de fin</label>
                    <input type="date" name="end_date" class="form-control">
                    <span class="form-hint">Laisser vide = valable jusqu'à révocation</span>
                </div>

                <div class="form-group" style="margin:0;grid-column:1/-1;">
                    <label class="form-label">Motif</label>
                    <input type="text" name="reason" class="form-control" maxlength="500"
                           placeholder="Ex : situation exceptionnelle validée le …">
                </div>
            </div>

            <div style="margin-top:1.1rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Créer l'autorisation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Liste des autorisations ───────────────────────────────────────────── -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <h3 style="margin:0;"><i class="bi bi-list-ul"></i> Toutes les autorisations</h3>
        <span class="text-muted" style="font-size:0.85rem;"><?= count($authorizations) ?> entrée(s)</span>
    </div>

    <?php if (empty($authorizations)): ?>
        <div class="card-body" style="text-align:center;color:var(--text-muted);padding:2rem;">
            <i class="bi bi-shield-check" style="font-size:2rem;"></i>
            <p style="margin-top:0.5rem;">Aucune autorisation enregistrée.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Compte</th>
                        <th>Limite supplémentaire</th>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Modérateur</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authorizations as $a): ?>
                        <?php
                            $st     = $a['status'];
                            $badge  = $statusLabels[$st] ?? ['label' => $st, 'class' => 'badge-secondary'];
                            $isActive = $st === 'active' || $st === 'pending';
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.8rem;">#<?= (int) $a['id'] ?></td>
                            <td>
                                <a href="/accounts/<?= (int) $a['account_id'] ?>">
                                    <?= e($a['account_name'] ?? 'Compte #' . $a['account_id']) ?>
                                </a>
                                <?php if (!empty($a['account_overdraft']) && (float) $a['account_overdraft'] > 0): ?>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">
                                        Découvert de base : <?= fmt_amount_smart((float) $a['account_overdraft']) ?> <?= e($a['account_currency'] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--primary);">
                                    + <?= fmt_amount_smart((float) $a['extra_limit']) ?> <?= e($a['account_currency'] ?? '') ?>
                                </strong>
                            </td>
                            <td style="white-space:nowrap;">
                                <?= e(date('d/m/Y', strtotime($a['start_date']))) ?>
                                →
                                <?= $a['end_date'] ? e(date('d/m/Y', strtotime($a['end_date']))) : '<span style="color:var(--text-muted);">Sans fin</span>' ?>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                title="<?= e($a['reason']) ?>">
                                <?= e($a['reason'] ?: '—') ?>
                            </td>
                            <td><?= e($a['moderator_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                                <?php if ($st === 'revoked'): ?>
                                    <div style="font-size:0.73rem;color:var(--text-muted);">
                                        <?= e(date('d/m/Y H:i', strtotime($a['revoked_at']))) ?>
                                        <?= !empty($a['revoker_name']) ? 'par ' . e($a['revoker_name']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($st === 'active' || $st === 'pending'): ?>
                                    <form method="POST"
                                          action="/moderation/overdraft-authorizations/<?= (int) $a['id'] ?>/revoke"
                                          onsubmit="return confirm('Révoquer cette autorisation ?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-x-circle"></i> Révoquer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.82rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
