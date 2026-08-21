<?php
/** @var array $events */
/** @var array|null $active */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-calendar2-week"></i> Modération — Événements</h1>
        <p class="page-description">Planification des fenêtres d'ouverture des comptes événementiels</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/features" class="btn btn-outline btn-sm"><i class="bi bi-toggles"></i> Fonctionnalités</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-calendar2-week"></i> Événements</span>
    </div>
</div>

<?php if ($active): ?>
<div class="alert alert-success" style="margin-bottom:1rem;">
    <i class="bi bi-broadcast"></i>
    Événement actif: <strong><?= e($active['title']) ?></strong>
    (du <?= e((new DateTime($active['start_at']))->format('d/m/Y H:i')) ?>
    au <?= e((new DateTime($active['end_at']))->format('d/m/Y H:i')) ?>)
</div>
<?php else: ?>
<div class="alert alert-warning" style="margin-bottom:1rem;">
    <i class="bi bi-pause-circle"></i>
    Aucun événement actif actuellement. L'ouverture de comptes événementiels est fermée.
</div>
<?php endif; ?>

<div class="card" style="max-width:760px;margin:0 auto 1.5rem;">
    <div class="card-header"><h3><i class="bi bi-plus-circle"></i> Planifier un événement</h3></div>
    <div class="card-body">
        <form method="POST" action="/moderation/events" style="display:grid;gap:0.75rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="title" class="form-label">Titre</label>
                <input type="text" id="title" name="title" class="form-control" maxlength="180" required placeholder="Ex : Festival Été 2026">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="start_at" class="form-label">Début</label>
                    <input type="text" id="start_at" name="start_at" class="form-control" required placeholder="jj/mm/aaaa hh:mm">
                </div>
                <div class="form-group">
                    <label for="end_at" class="form-label">Fin</label>
                    <input type="text" id="end_at" name="end_at" class="form-control" required placeholder="jj/mm/aaaa hh:mm">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-calendar-check"></i> Planifier l'événement
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="bi bi-list-ul"></i> Historique des événements</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table" style="margin:0;">
                <thead>
                <tr>
                    <th>Titre</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="4" class="text-muted" style="padding:1rem;">Aucun événement planifié.</td></tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $now = time();
                        $startTs = strtotime((string) $event['start_at']);
                        $endTs = strtotime((string) $event['end_at']);
                        $status = $now < $startTs ? 'À venir' : ($now > $endTs ? 'Terminé' : 'Actif');
                        ?>
                        <tr>
                            <td><strong><?= e($event['title']) ?></strong></td>
                            <td><?= e((new DateTime($event['start_at']))->format('d/m/Y H:i')) ?></td>
                            <td><?= e((new DateTime($event['end_at']))->format('d/m/Y H:i')) ?></td>
                            <td>
                                <?php if ($status === 'Actif'): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php elseif ($status === 'À venir'): ?>
                                    <span class="badge bg-info text-dark">À venir</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Terminé</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var opts = {
        enableTime: true,
        dateFormat: 'd/m/Y H:i',
        time_24hr: true,
        locale: 'fr'
    };
    if (window.flatpickr) {
        flatpickr('#start_at', opts);
        flatpickr('#end_at', opts);
    }
})();
</script>
