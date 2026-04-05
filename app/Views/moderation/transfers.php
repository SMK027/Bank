<div class="page-header">
    <div>
        <h1><i class="bi bi-arrow-left-right"></i> Modération — Virements</h1>
        <p class="page-description">Historique de tous les virements</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-bank"></i> Comptes
        </a>
        <a href="/moderation/users" class="btn btn-outline btn-sm">
            <i class="bi bi-people"></i> Utilisateurs
        </a>
    </div>
</div>

<?php if ($totalCount === 0): ?>
    <div class="empty-state">
        <div class="empty-icon">↔️</div>
        <p>Aucun virement enregistré.</p>
    </div>
<?php else: ?>

<h4 style="margin-bottom:0.9rem;font-size:1rem;font-weight:600">
    <span id="transfer-count-badge" style="font-size:0.82rem;font-weight:400;color:var(--text-muted)"></span>
</h4>

<!-- Barre de filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 0.75rem;">
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Auteur</label>
                <select id="tf-author" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                    <option value="">Tous</option>
                    <?php foreach ($tfAuthors as $uname): ?>
                        <option value="<?= e($uname) ?>"><?= e($uname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Statut</label>
                <select id="tf-status" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                    <option value="">Tous</option>
                    <option value="success">Réussi</option>
                    <option value="scheduled">Planifié</option>
                    <option value="failed">Échoué</option>
                    <option value="cancelled">Annulé</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant min.</label>
                <input type="number" id="tf-amount-min" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" min="0" step="0.01" placeholder="0.00">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant max.</label>
                <input type="number" id="tf-amount-max" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" min="0" step="0.01" placeholder="—">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Date du</label>
                <input type="date" id="tf-date-from" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Date au</label>
                <input type="date" id="tf-date-to" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
            </div>
            <div style="grid-column:1/-1">
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Motif</label>
                <input type="text" id="tf-motif" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" placeholder="Recherche libre…">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:0.6rem">
            <button type="button" onclick="resetTfFilters()" class="btn btn-outline btn-sm">
                <i class="bi bi-x-circle"></i> Réinitialiser
            </button>
        </div>
    </div>
</div>

<!-- Tableau -->
<div class="table-responsive" style="border-radius:6px;border:1px solid var(--border-color,#e2e8f0);overflow:hidden">
    <table class="table" id="transfers-history-table" style="margin:0;font-size:0.84rem">
        <thead>
            <tr>
                <th style="white-space:nowrap">#</th>
                <th style="white-space:nowrap">Auteur</th>
                <th style="white-space:nowrap">Émetteur</th>
                <th style="white-space:nowrap">Destinataire</th>
                <th style="white-space:nowrap">Montant</th>
                <th>Motif</th>
                <th style="white-space:nowrap">Statut</th>
                <th style="white-space:nowrap">Planifié le</th>
                <th style="white-space:nowrap">Exécuté le</th>
            </tr>
        </thead>
        <tbody id="transfers-history-body"></tbody>
    </table>
</div>
<p id="tf-empty" style="display:none;text-align:center;color:var(--text-muted);padding:1.2rem 0;font-size:0.88rem">
    <i class="bi bi-search"></i> Aucun virement ne correspond à vos critères.
</p>

<?php endif; ?>

<p class="text-center text-muted text-small mt-2">
    <a href="/moderation"><i class="bi bi-arrow-left"></i> Retour à la modération</a>
</p>

<script>
(function () {
    var TRANSFERS = <?= $transfersJson ?? '[]' ?>;

    var STATUS_LABELS = {
        'scheduled': 'Planifié',
        'success':   'Réussi',
        'failed':    'Échoué',
        'cancelled': 'Annulé'
    };
    var STATUS_BADGES = {
        'scheduled': 'badge-info',
        'success':   'badge-success',
        'failed':    'badge-danger',
        'cancelled': 'badge-secondary'
    };

    function fmt(n) {
        return Number(n).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    function fmtDate(s) {
        if (!s) return '—';
        var d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit', year:'numeric'})
             + '\u00a0' + d.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
    }
    function dateOnly(s) {
        return s ? String(s).substring(0, 10) : '';
    }
    function esc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderTransfers(list) {
        var tbody = document.getElementById('transfers-history-body');
        var empty = document.getElementById('tf-empty');
        var badge = document.getElementById('transfer-count-badge');
        if (!tbody) return;
        if (badge) badge.textContent = list.length + ' virement' + (list.length > 1 ? 's' : '') + ' affiché' + (list.length > 1 ? 's' : '') + ' sur ' + TRANSFERS.length;
        if (list.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.style.display = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        var html = '';
        list.forEach(function (t) {
            var st   = t.status || 'success';
            var bCls = STATUS_BADGES[st] || 'badge-secondary';
            var bLbl = STATUS_LABELS[st] || st;
            var motifCell = t.motif
                ? '<span title="' + esc(t.motif) + '">' + esc(t.motif) + '</span>'
                : '<span style="color:var(--text-muted)">—</span>';
            html += '<tr>'
                + '<td style="color:var(--text-muted)">#' + esc(t.id) + '</td>'
                + '<td>' + esc(t.user_name) + '</td>'
                + '<td>' + esc(t.from_account) + '</td>'
                + '<td>' + esc(t.to_account) + '</td>'
                + '<td style="white-space:nowrap;font-weight:600">' + fmt(t.amount) + '</td>'
                + '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + motifCell + '</td>'
                + '<td><span class="badge ' + bCls + '">' + bLbl + '</span></td>'
                + '<td style="white-space:nowrap;font-size:0.78rem">' + fmtDate(t.scheduled_at) + '</td>'
                + '<td style="white-space:nowrap;font-size:0.78rem">' + fmtDate(t.executed_at) + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
    }

    function filterTransfers() {
        var author   = (document.getElementById('tf-author')     || {value:''}).value;
        var status   = (document.getElementById('tf-status')     || {value:''}).value;
        var amtMinEl = document.getElementById('tf-amount-min');
        var amtMaxEl = document.getElementById('tf-amount-max');
        var amtMin   = amtMinEl && amtMinEl.value !== '' ? parseFloat(amtMinEl.value) : -Infinity;
        var amtMax   = amtMaxEl && amtMaxEl.value !== '' ? parseFloat(amtMaxEl.value) :  Infinity;
        var dateFrom = (document.getElementById('tf-date-from')  || {value:''}).value;
        var dateTo   = (document.getElementById('tf-date-to')    || {value:''}).value;
        var motif    = ((document.getElementById('tf-motif')     || {value:''}).value || '').toLowerCase().trim();

        var filtered = TRANSFERS.filter(function (t) {
            if (author && t.user_name !== author)                                          return false;
            if (status && t.status    !== status)                                          return false;
            if (t.amount < amtMin)                                                         return false;
            if (amtMax !== Infinity && t.amount > amtMax)                                  return false;
            var d = dateOnly(t.created_at);
            if (dateFrom && d < dateFrom)                                                  return false;
            if (dateTo   && d > dateTo)                                                    return false;
            if (motif && (t.motif || '').toLowerCase().indexOf(motif) === -1)              return false;
            return true;
        });
        renderTransfers(filtered);
    }

    ['tf-author','tf-status','tf-amount-min','tf-amount-max','tf-date-from','tf-date-to'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', filterTransfers);
    });
    var motifEl = document.getElementById('tf-motif');
    if (motifEl) {
        motifEl.addEventListener('input',  filterTransfers);
        motifEl.addEventListener('change', filterTransfers);
    }

    window.resetTfFilters = function () {
        ['tf-author','tf-status','tf-amount-min','tf-amount-max','tf-date-from','tf-date-to','tf-motif'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        filterTransfers();
    };

    renderTransfers(TRANSFERS);
})();
</script>
