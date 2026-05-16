<?php
/** @var array $account Compte bancaire */
?>

<div class="container statement-form" style="max-width:560px; margin:0 auto; padding:1.5rem 1rem;">
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <a href="/accounts/<?= (int) $account['id'] ?>" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
        <h2 style="margin:0; display:flex; align-items:center; gap:0.5rem;">
            <i class="bi bi-file-earmark-text" style="color:#1e40af;"></i>
            Relevé de compte
        </h2>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; gap:0.5rem;">
            <i class="bi bi-wallet2"></i>
            <span><?= e($account['name']) ?></span>
            <span class="badge badge-secondary" style="margin-left:auto; font-size:0.75rem;">
                <?= e($account['currency']) ?>
            </span>
        </div>
        <div class="card-body">
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.25rem;">
                Sélectionnez la période souhaitée. Toutes les opérations exécutées seront incluses dans le relevé PDF.
            </p>

            <form method="GET" action="/accounts/<?= (int) $account['id'] ?>/statement/pdf" target="_blank">

                <div class="statement-date-range" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div>
                        <label for="date_from" style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.3rem;">
                            <i class="bi bi-calendar-event"></i> Date de début
                        </label>
                        <input type="date" id="date_from" name="date_from"
                               class="form-control"
                               value="<?= e($_GET['date_from'] ?? date('Y-m-01')) ?>"
                               max="<?= date('Y-m-d') ?>"
                               required>
                    </div>
                    <div>
                        <label for="date_to" style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.3rem;">
                            <i class="bi bi-calendar-check"></i> Date de fin
                        </label>
                        <input type="date" id="date_to" name="date_to"
                               class="form-control"
                               value="<?= e($_GET['date_to'] ?? date('Y-m-d')) ?>"
                               max="<?= date('Y-m-d') ?>"
                               required>
                    </div>
                </div>

                <!-- Raccourcis de périodes -->
                <div class="statement-period-presets" style="display:flex; gap:0.4rem; flex-wrap:wrap; margin-bottom:1.25rem;">
                    <button type="button" class="btn btn-outline btn-sm period-preset"
                            data-from="<?= date('Y-m-01') ?>" data-to="<?= date('Y-m-d') ?>">
                        Mois en cours
                    </button>
                    <button type="button" class="btn btn-outline btn-sm period-preset"
                            data-from="<?= date('Y-m-01', strtotime('first day of last month')) ?>"
                            data-to="<?= date('Y-m-t', strtotime('first day of last month')) ?>">
                        Mois dernier
                    </button>
                    <button type="button" class="btn btn-outline btn-sm period-preset"
                            data-from="<?= date('Y-01-01') ?>" data-to="<?= date('Y-m-d') ?>">
                        Année <?= date('Y') ?>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm period-preset"
                            data-from="<?= date('Y-m-d', strtotime('-90 days')) ?>"
                            data-to="<?= date('Y-m-d') ?>">
                        90 derniers jours
                    </button>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="bi bi-file-earmark-pdf"></i>&nbsp; Générer le relevé PDF
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.period-preset').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('date_from').value = this.dataset.from;
        document.getElementById('date_to').value   = this.dataset.to;
    });
});

// Validation côté client : date_from <= date_to
document.querySelector('form').addEventListener('submit', function(e) {
    var from = document.getElementById('date_from').value;
    var to   = document.getElementById('date_to').value;
    if (from > to) {
        e.preventDefault();
        alert('La date de début doit être antérieure ou égale à la date de fin.');
    }
});
</script>
