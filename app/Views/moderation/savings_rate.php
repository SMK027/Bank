<div class="page-header">
    <div>
        <h1><i class="bi bi-percent"></i> Taux d'intérêt maximum — Épargne</h1>
        <p class="page-description">Définissez le taux annuel brut <strong>maximum</strong> autorisé par type de compte. Les utilisateurs fixent leur propre taux dans la configuration de leur compte, dans la limite de ce plafond.</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-percent"></i> Taux d'intérêt</span>
    </div>
</div>

<?php
use App\Models\Account;
$typeLabels = array_column(Account::TYPES, 'label', null);
// $typeLabels est indexé par clé de tableau, on refait la map correctement
$typeMap = [];
foreach (Account::TYPES as $key => $def) { $typeMap[$key] = $def['label']; }
?>

<!-- Taux actuels par type -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-gear"></i> Taux actuels par type de compte</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Type de compte</th>
                    <th>Taux maximum</th>
                    <th>Défini le</th>
                    <th>Par</th>
                    <th style="width:240px;">Nouveau taux (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eligibleTypes as $accountType): ?>
                <?php $current = $allRates[$accountType] ?? null; ?>
                <tr>
                    <td>
                        <strong><?= e($typeMap[$accountType] ?? $accountType) ?></strong>
                        <code style="font-size:0.75rem;margin-left:0.4rem;color:var(--text-muted,#6b7280);"><?= e($accountType) ?></code>
                    </td>
                    <td>
                        <?php if ($current): ?>
                            <span style="font-size:1.1rem;font-weight:700;color:var(--success,#16a34a);">
                                <?= number_format((float) $current['rate'] * 100, 2, ',', ' ') ?> %
                            </span>
                        <?php else: ?>
                            <span class="text-muted"><i class="bi bi-dash"></i> Non configuré (aucune limite)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= $current ? date('d/m/Y à H\hi', strtotime($current['created_at'])) : '—' ?>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= $current ? e($current['set_by_username'] ?? '—') : '—' ?>
                    </td>
                    <td>
                        <form method="POST" action="/moderation/savings-rate" style="display:flex;gap:0.4rem;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_type" value="<?= e($accountType) ?>">
                            <input type="number" name="rate" class="form-control" style="padding:0.3rem 0.5rem;font-size:0.85rem;width:110px;"
                                   min="0" max="100" step="0.01" required
                                   placeholder="Ex : 3.00"
                                   value="<?= $current ? htmlspecialchars(number_format((float) $current['rate'] * 100, 2, '.', ''), ENT_QUOTES) : '' ?>">
                            <span class="form-hint" style="font-size:0.75rem;color:var(--text-muted,#6b7280);">Plafond max des utilisateurs</span>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Déclencher un calcul d'intérêts -->
<div class="card" style="margin-bottom:1.5rem;border-left:4px solid #10b981;">
    <div class="card-header" style="display:flex;align-items:center;gap:0.6rem;">
        <i class="bi bi-play-circle-fill" style="color:#10b981;font-size:1.1rem;"></i>
        <h3 style="margin:0;">Déclencher un calcul d'intérêts</h3>
    </div>
    <div class="card-body">
        <p style="margin:0 0 0.8rem;color:var(--text-muted,#6b7280);font-size:0.88rem;">
            Calcule et crée les intérêts en attente pour chaque compte éligible ayant un taux configuré.
            Le <strong>solde actuel</strong> est utilisé directement (équivalent à considérer que le solde est présent depuis le 1<sup>er</sup> janvier).
            Les comptes ayant déjà des intérêts enregistrés pour l'année choisie sont ignorés.
        </p>
        <form method="POST" action="/moderation/interests/run"
              onsubmit="return confirm('Déclencher le calcul des intérêts pour ' + this.year.value + ' ?');"
              style="display:flex;gap:0.6rem;align-items:center;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <label for="run_year" style="font-weight:600;white-space:nowrap;">Année :</label>
            <input type="number" id="run_year" name="year"
                   class="form-control" style="width:110px;"
                   min="2000" max="<?= (int) date('Y') + 1 ?>"
                   value="<?= (int) date('Y') ?>" required>
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-play-circle"></i> Lancer le calcul
            </button>
        </form>
    </div>
</div>

<!-- Historique -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
        <h3 style="margin:0;"><i class="bi bi-clock-history"></i> Historique des taux</h3>
        <div style="display:flex;gap:0.4rem;align-items:center;">
            <span class="text-muted" style="font-size:0.85rem;">Filtrer :</span>
            <?php foreach (array_merge([''], $eligibleTypes) as $t): ?>
            <a href="/moderation/savings-rate<?= $t !== '' ? '?type=' . urlencode($t) : '' ?>"
               class="btn btn-sm <?= ($filterType === ($t !== '' ? $t : null)) || ($filterType === null && $t === '') ? 'btn-primary' : 'btn-outline' ?>">
                <?= $t !== '' ? e($typeMap[$t] ?? $t) : 'Tous' ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($history)): ?>
            <p class="text-muted" style="padding:1rem;">Aucun historique disponible.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type de compte</th>
                        <th>Taux</th>
                        <th>Modérateur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= date('d/m/Y à H\hi', strtotime($h['created_at'])) ?></td>
                        <td>
                            <?= e($typeMap[$h['account_type']] ?? $h['account_type']) ?>
                            <code style="font-size:0.75rem;margin-left:0.3rem;color:var(--text-muted,#6b7280);"><?= e($h['account_type']) ?></code>
                        </td>
                        <td><strong><?= number_format((float) $h['rate'] * 100, 2, ',', ' ') ?> %</strong></td>
                        <td><?= e($h['set_by_username'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

