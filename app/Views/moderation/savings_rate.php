<div class="page-header">
    <div>
        <h1><i class="bi bi-percent"></i> Taux d'intérêt épargne</h1>
        <p class="page-description">Configurez le taux annuel brut appliqué aux comptes épargne.</p>
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

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">

    <!-- Taux actuel + formulaire -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-gear"></i> Taux actuel</h3>
        </div>
        <div class="card-body">
            <?php if ($currentRate): ?>
                <p style="font-size:1.6rem;font-weight:700;color:var(--success,#16a34a);margin-bottom:0.25rem;">
                    <?= number_format((float) $currentRate['rate'] * 100, 2, ',', ' ') ?> % / an
                </p>
                <p class="text-muted" style="font-size:0.85rem;margin-bottom:1.25rem;">
                    Défini le <?= date('d/m/Y à H\hi', strtotime($currentRate['created_at'])) ?>
                    <?php if (!empty($currentRate['set_by_username'])): ?>
                        par <strong><?= e($currentRate['set_by_username']) ?></strong>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="text-muted" style="margin-bottom:1.25rem;">
                    <i class="bi bi-exclamation-circle"></i> Aucun taux configuré — les intérêts ne seront pas calculés.
                </p>
            <?php endif; ?>

            <hr style="margin-bottom:1.25rem;">

            <form method="POST" action="/moderation/savings-rate">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="rate">
                        Nouveau taux annuel (%)
                    </label>
                    <input type="number" id="rate" name="rate" class="form-control"
                           min="0" max="100" step="0.01" required
                           placeholder="Exemple : 3.00"
                           value="<?= $currentRate ? htmlspecialchars(number_format((float) $currentRate['rate'] * 100, 2, '.', ''), ENT_QUOTES) : '' ?>">
                    <span class="form-hint">
                        Saisissez le taux en pourcentage (ex : 3,00 pour 3 %).<br>
                        Ce taux sera appliqué lors du prochain calcul annuel (1er janvier).
                    </span>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-check-lg"></i> Enregistrer le taux
                </button>
            </form>
        </div>
    </div>

    <!-- Historique -->
    <div class="card">
        <div class="card-header">
            <h3><i class="bi bi-clock-history"></i> Historique des taux</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($history)): ?>
                <p class="text-muted" style="padding:1rem;">Aucun historique disponible.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date de définition</th>
                            <th>Taux</th>
                            <th>Modérateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y à H\hi', strtotime($h['created_at'])) ?></td>
                            <td><strong><?= number_format((float) $h['rate'] * 100, 2, ',', ' ') ?> %</strong></td>
                            <td><?= e($h['set_by_username'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>
