<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-arrow-down"></i> Modération — Prélèvements</h1>
        <p class="page-description">Gestion des prélèvements automatiques</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/direct-debits/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Nouveau prélèvement
        </a>
        <a href="/moderation" class="btn btn-outline btn-sm">
            <i class="bi bi-bank"></i> Comptes
        </a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm">
            <i class="bi bi-arrow-left-right"></i> Virements
        </a>
        <a href="/moderation/users" class="btn btn-outline btn-sm">
            <i class="bi bi-people"></i> Utilisateurs
        </a>
    </div>
</div>

<?php if ($totalCount === 0): ?>
    <div class="empty-state">
        <div class="empty-icon">📄</div>
        <p>Aucun prélèvement enregistré.</p>
        <a href="/moderation/direct-debits/create" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">
            <i class="bi bi-plus-circle"></i> Créer un prélèvement
        </a>
    </div>
<?php else: ?>

<h4 style="margin-bottom:0.9rem;font-size:1rem;font-weight:600">
    <span id="dd-count-badge" style="font-size:0.82rem;font-weight:400;color:var(--text-muted)"></span>
</h4>

<!-- Barre de filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 0.75rem;">
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Statut</label>
                <select id="dd-status" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                    <option value="">Tous</option>
                    <option value="scheduled">Planifié</option>
                    <option value="success">Exécuté</option>
                    <option value="failed">Échoué</option>
                    <option value="cancelled">Annulé</option>
                    <option value="rejected">Rejeté</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Mandat</label>
                <input type="text" id="dd-mandate" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto"
                       placeholder="Recherche…">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant min.</label>
                <input type="number" id="dd-amount-min" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto"
                       min="0" step="0.01" placeholder="0.00">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant max.</label>
                <input type="number" id="dd-amount-max" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto"
                       min="0" step="0.01" placeholder="—">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Planifié du</label>
                <input type="date" id="dd-date-from" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Planifié au</label>
                <input type="date" id="dd-date-to" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
            </div>
            <div style="grid-column:1/-1">
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Motif / Compte</label>
                <input type="text" id="dd-search" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto"
                       placeholder="Recherche libre…">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:0.6rem">
            <button type="button" onclick="resetDdFilters()" class="btn btn-outline btn-sm">
                <i class="bi bi-x-circle"></i> Réinitialiser
            </button>
        </div>
    </div>
</div>

<!-- Tableau -->
<div class="table-responsive" style="border-radius:6px;border:1px solid var(--border-color,#e2e8f0);overflow:hidden">
    <table class="table table-hover" style="margin:0;font-size:0.83rem;">
        <thead style="background:var(--bg-secondary,#f8fafc);">
            <tr>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">#</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Mandat</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Montant</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Émetteur</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Destinataire</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Motif</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Planifié le</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Exécuté le</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Statut</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Créé par</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Actions</th>
            </tr>
        </thead>
        <tbody id="dd-tbody"></tbody>
    </table>
</div>

<?php endif; ?>

<script>
var DD_DATA   = <?= $debitsJson ?>;
var DD_CSRF   = <?= json_encode($csrfToken) ?>;
var ddVisible = DD_DATA.slice();

var STATUS_BADGE = {
    scheduled: '<span class="badge badge-warning">Planifié</span>',
    success:   '<span class="badge badge-success">Exécuté</span>',
    failed:    '<span class="badge badge-danger">Échoué</span>',
    cancelled: '<span class="badge badge-secondary">Annulé</span>',
    rejected:  '<span class="badge badge-danger" style="opacity:0.8">Rejeté</span>',
};

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtDate(d) {
    if (!d) return '—';
    var dt = new Date(d.replace(' ', 'T'));
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('fr-FR') + ' ' + dt.toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'});
}

function fmtAmount(a) {
    return Number(a).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
}

function renderTable() {
    var tbody = document.getElementById('dd-tbody');
    if (!tbody) return;

    document.getElementById('dd-count-badge').textContent =
        ddVisible.length + ' prélèvement' + (ddVisible.length !== 1 ? 's' : '') + ' affiché' + (ddVisible.length !== 1 ? 's' : '');

    if (ddVisible.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:1.5rem">Aucun résultat</td></tr>';
        return;
    }

    var html = '';
    for (var i = 0; i < ddVisible.length; i++) {
        var d = ddVisible[i];
        var actionCell = '';
        if (d.status === 'scheduled') {
            actionCell =
                '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/cancel"'
                + ' style="display:inline" onsubmit="return confirm(\'Annuler le prélèvement #' + d.id + ' (mandat ' + esc(d.mandate_number) + ') ?\');">'
                + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                + '<button type="submit" class="btn btn-danger btn-sm" style="padding:0.2rem 0.5rem;font-size:0.76rem;">'
                + '<i class="bi bi-x-circle"></i> Annuler</button>'
                + '</form>';
        } else if (d.status === 'success') {
            actionCell =
                '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/reject"'
                + ' style="display:inline" onsubmit="return confirm(\'Rejeter le prélèvement #' + d.id + ' (mandat ' + esc(d.mandate_number) + ') ?\\nLe montant sera recrédité sur le compte débité.\');">'  
                + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                + '<button type="submit" class="btn btn-warning btn-sm" style="padding:0.2rem 0.5rem;font-size:0.76rem;">'
                + '<i class="bi bi-arrow-counterclockwise"></i> Rejeter</button>'
                + '</form>';
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap">' + esc(d.from_account) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap">' + esc(d.to_account) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;max-width:160px;overflow:hidden;text-overflow:ellipsis">' + esc(d.motif || '—') + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap;font-size:0.78rem">' + fmtDate(d.scheduled_at) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap;font-size:0.78rem">' + fmtDate(d.executed_at) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap">' + (STATUS_BADGE[d.status] || esc(d.status)) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap;font-size:0.78rem">' + esc(d.created_by_name) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap">' + actionCell + '</td>'
            + '</tr>';
    }
    tbody.innerHTML = html;
}

function applyDdFilters() {
    var status    = document.getElementById('dd-status').value;
    var mandate   = (document.getElementById('dd-mandate').value || '').toLowerCase();
    var amtMin    = parseFloat(document.getElementById('dd-amount-min').value) || null;
    var amtMax    = parseFloat(document.getElementById('dd-amount-max').value) || null;
    var dateFrom  = document.getElementById('dd-date-from').value;
    var dateTo    = document.getElementById('dd-date-to').value;
    var search    = (document.getElementById('dd-search').value || '').toLowerCase();

    ddVisible = DD_DATA.filter(function(d) {
        if (status   && d.status !== status) return false;
        if (mandate  && d.mandate_number.toLowerCase().indexOf(mandate) === -1) return false;
        if (amtMin   !== null && d.amount < amtMin) return false;
        if (amtMax   !== null && d.amount > amtMax) return false;
        if (dateFrom && d.scheduled_at && d.scheduled_at.substring(0,10) < dateFrom) return false;
        if (dateTo   && d.scheduled_at && d.scheduled_at.substring(0,10) > dateTo)   return false;
        if (search) {
            var haystack = (d.motif + ' ' + d.from_account + ' ' + d.to_account).toLowerCase();
            if (haystack.indexOf(search) === -1) return false;
        }
        return true;
    });
    renderTable();
}

function resetDdFilters() {
    ['dd-status','dd-mandate','dd-amount-min','dd-amount-max','dd-date-from','dd-date-to','dd-search']
        .forEach(function(id) { var el = document.getElementById(id); if (el) el.value = ''; });
    ddVisible = DD_DATA.slice();
    renderTable();
}

['dd-status','dd-mandate','dd-amount-min','dd-amount-max','dd-date-from','dd-date-to','dd-search']
    .forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', applyDdFilters);
    });

renderTable();
</script>
