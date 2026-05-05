<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-arrow-down"></i> Modération — Prélèvements</h1>
        <p class="page-description">Gestion des prélèvements automatiques</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/direct-debits/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Nouveau prélèvement
        </a>
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/recurring-transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-repeat"></i> Virements récurrents</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</span>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="/moderation/savings-rate" class="btn btn-outline btn-sm"><i class="bi bi-percent"></i> Taux d'intérêt</a>
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

<!-- Stat cards -->
<div class="stats-grid" id="dd-stats" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.75rem;margin-bottom:1rem;">
    <div class="stat-card" style="padding:0.9rem 0.7rem;cursor:pointer" onclick="filterByStatus('')" title="Afficher tous">
        <div class="stat-value" style="font-size:1.4rem" id="stat-total">0</div>
        <div class="stat-label" style="font-size:0.78rem">Total</div>
    </div>
    <div class="stat-card" style="padding:0.9rem 0.7rem;cursor:pointer" onclick="filterByStatus('scheduled')" title="Filtrer : Planifiés">
        <div class="stat-value" style="font-size:1.4rem;color:var(--warning,#f59e0b)" id="stat-scheduled">0</div>
        <div class="stat-label" style="font-size:0.78rem"><i class="bi bi-clock"></i> Planifiés</div>
    </div>
    <div class="stat-card" style="padding:0.9rem 0.7rem;cursor:pointer" onclick="filterByStatus('success')" title="Filtrer : Exécutés">
        <div class="stat-value" style="font-size:1.4rem;color:var(--success,#10b981)" id="stat-success">0</div>
        <div class="stat-label" style="font-size:0.78rem"><i class="bi bi-check-circle"></i> Exécutés</div>
    </div>
    <div class="stat-card" style="padding:0.9rem 0.7rem;cursor:pointer" onclick="filterByStatus('rejected')" title="Filtrer : Rejetés">
        <div class="stat-value" style="font-size:1.4rem;color:var(--danger,#ef4444)" id="stat-rejected">0</div>
        <div class="stat-label" style="font-size:0.78rem"><i class="bi bi-x-octagon"></i> Rejetés</div>
    </div>
    <div class="stat-card" style="padding:0.9rem 0.7rem;cursor:pointer" onclick="filterByStatus('failed')" title="Filtrer : Échoués">
        <div class="stat-value" style="font-size:1.4rem;color:var(--danger,#ef4444);opacity:0.7" id="stat-failed">0</div>
        <div class="stat-label" style="font-size:0.78rem"><i class="bi bi-exclamation-triangle"></i> Échoués</div>
    </div>
    <div class="stat-card" style="padding:0.9rem 0.7rem;cursor:pointer" onclick="filterByStatus('cancelled')" title="Filtrer : Annulés">
        <div class="stat-value" style="font-size:1.4rem;color:var(--gray,#6b7280)" id="stat-cancelled">0</div>
        <div class="stat-label" style="font-size:0.78rem"><i class="bi bi-slash-circle"></i> Annulés</div>
    </div>
</div>

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
    <table class="table table-hover moderation-dd-table" style="margin:0;font-size:0.83rem;">
        <thead style="background:var(--bg-secondary,#f8fafc);">
            <tr>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">#</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Mandat</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Comptes</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Montant</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Motif</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Dates</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;">Statut</th>
                <th style="white-space:nowrap;padding:0.6rem 0.8rem;text-align:center">Actions</th>
            </tr>
        </thead>
        <tbody id="dd-tbody"></tbody>
    </table>
</div>

<?php endif; ?>

<p class="text-center text-muted text-small mt-2">
    <a href="/moderation"><i class="bi bi-arrow-left"></i> Retour à la modération</a>
</p>

<script>
var DD_DATA   = <?= $debitsJson ?>;
var DD_CSRF   = <?= json_encode($csrfToken) ?>;
var ddVisible = DD_DATA.slice();

var STATUS_BADGE = {
    scheduled: '<span class="badge badge-warning"><i class="bi bi-clock"></i> Planifié</span>',
    success:   '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Exécuté</span>',
    failed:    '<span class="badge badge-danger"><i class="bi bi-exclamation-triangle"></i> Échoué</span>',
    cancelled: '<span class="badge badge-secondary"><i class="bi bi-slash-circle"></i> Annulé</span>',
    rejected:  '<span class="badge badge-danger" style="opacity:0.85"><i class="bi bi-x-octagon"></i> Rejeté</span>',
};

var ROW_BG = {
    scheduled: 'background:rgba(245,158,11,0.04)',
    success:   '',
    failed:    'background:rgba(239,68,68,0.05)',
    cancelled: 'background:rgba(107,114,128,0.04)',
    rejected:  'background:rgba(239,68,68,0.05)',
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

function confirmRejectDD(form, id, mandateNumber) {
    var reason = (form.querySelector('input[name="reason"]') || {}).value || '';
    var pwd    = (form.querySelector('input[name="password"]') || {}).value || '';
    if (!reason.trim()) {
        alert('Le motif de rejet est obligatoire.');
        return false;
    }
    if (!pwd) {
        alert('Veuillez saisir votre mot de passe pour confirmer le rejet.');
        return false;
    }
    return confirm('Rejeter le prélèvement #' + id + ' (mandat ' + mandateNumber + ') ?\nLe montant sera recrédité sur le compte débité.');
}

function renderTable() {
    var tbody = document.getElementById('dd-tbody');
    if (!tbody) return;

    document.getElementById('dd-count-badge').textContent =
        ddVisible.length + ' prélèvement' + (ddVisible.length !== 1 ? 's' : '') + ' affiché' + (ddVisible.length !== 1 ? 's' : '')
        + ' sur ' + DD_DATA.length;

    if (ddVisible.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:1.5rem"><i class="bi bi-search"></i> Aucun résultat</td></tr>';
        return;
    }

    var html = '';
    for (var i = 0; i < ddVisible.length; i++) {
        var d = ddVisible[i];
        var actionCell = '';
        if (d.status === 'scheduled') {
            var curDate = d.scheduled_at ? fmtDate(d.scheduled_at) : '';
            actionCell =
                '<div style="display:flex;flex-direction:column;gap:0.3rem;align-items:center">'
                + '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/reschedule"'
                + ' style="display:flex;align-items:center;gap:0.3rem;">'
                + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                + '<input type="text" name="scheduled_at"'
                + ' value="' + esc(curDate) + '"'
                + ' placeholder="jj/mm/aaaa hh:mm"'
                + ' title="Nouvelle date d\'exécution"'
                + ' style="font-size:0.76rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:130px">'
                + '<button type="submit" class="btn btn-outline btn-sm" style="padding:0.25rem 0.5rem;font-size:0.76rem;" title="Reprogrammer l\'échéance">'
                + '<i class="bi bi-calendar2-event"></i></button>'
                + '</form>'
                + '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/cancel"'
                + ' style="display:inline" onsubmit="return confirm(\'Annuler le prélèvement #' + d.id + ' (mandat ' + esc(d.mandate_number) + ') ?\');">'
                + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                + '<button type="submit" class="btn btn-danger btn-sm" style="padding:0.25rem 0.6rem;font-size:0.76rem;" title="Annuler ce prélèvement">'
                + '<i class="bi bi-x-circle"></i> Annuler</button>'
                + '</form>'
                + '</div>';
        } else if (d.status === 'success') {
            var now        = Date.now();
            var executedMs = d.executed_at ? new Date(d.executed_at.replace(' ', 'T')).getTime() : 0;
            var ageMs      = now - executedMs;
            var H48        = 48 * 3600 * 1000;
            if (executedMs && ageMs >= 0 && ageMs < H48) {
                // Fenêtre 48 h : motif + mot de passe obligatoires
                actionCell =
                    '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/reject"'
                    + ' style="display:flex;flex-direction:column;gap:0.25rem;align-items:stretch;min-width:200px;"'
                    + ' onsubmit="return confirmRejectDD(this, ' + d.id + ', \'' + esc(d.mandate_number) + '\');">'
                    + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                    + '<input type="text" name="reason" required maxlength="500" placeholder="Motif du rejet (obligatoire)"'
                    + ' style="font-size:0.74rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);">'
                    + '<input type="password" name="password" required autocomplete="current-password" placeholder="Votre mot de passe"'
                    + ' style="font-size:0.74rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);">'
                    + '<button type="submit" class="btn btn-warning btn-sm" style="padding:0.25rem 0.6rem;font-size:0.76rem;" title="Rejeter et rembourser">'
                    + '<i class="bi bi-arrow-counterclockwise"></i> Rejeter</button>'
                    + '</form>';
            } else if (executedMs) {
                // Au-delà de 48 h : rejet libre, sans motif ni mot de passe
                actionCell =
                    '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/reject"'
                    + ' style="display:inline" onsubmit="return confirm(\'Rejeter le prélèvement #' + d.id + ' (mandat ' + esc(d.mandate_number) + ') ?\\nLe montant sera recrédité sur le compte débité.\');">'
                    + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                    + '<button type="submit" class="btn btn-warning btn-sm" style="padding:0.25rem 0.6rem;font-size:0.76rem;" title="Rejeter et rembourser (au-delà de 48 h)">'
                    + '<i class="bi bi-arrow-counterclockwise"></i> Rejeter</button>'
                    + '</form>';
            } else {
                actionCell = '<span class="badge badge-secondary" style="font-size:0.7rem;opacity:0.7"><i class="bi bi-hourglass-split"></i> —</span>';
            }
        } else if (d.status === 'rejected' || d.status === 'failed') {
            actionCell =
                '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/retry"'
                + ' style="display:inline">'
                + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                + '<div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">'
                + '<input type="text" name="scheduled_at"'
                + ' placeholder="jj/mm/aaaa hh:mm"'
                + ' title="Date de planification (vide = immédiate)"'
                + ' style="font-size:0.76rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);">'
                + '<button type="submit" class="btn btn-info btn-sm" style="padding:0.25rem 0.6rem;font-size:0.76rem;" title="Créer un nouveau prélèvement planifié">'
                + '<i class="bi bi-arrow-repeat"></i> Réexécuter</button>'
                + '</div>'
                + '</form>';
        } else if (d.status === 'cancelled') {
            var scheduledMs = d.scheduled_at ? new Date(d.scheduled_at.replace(' ', 'T')).getTime() : 0;
            if (scheduledMs && scheduledMs > Date.now()) {
                actionCell =
                    '<form method="POST" action="/moderation/direct-debits/' + esc(String(d.id)) + '/reactivate"'
                    + ' style="display:inline"'
                    + ' onsubmit="return confirm(\'Réactiver le prélèvement #' + d.id + ' (mandat ' + esc(d.mandate_number) + ') ?\\nIl repassera en statut Planifié et sera exécuté le ' + esc(fmtDate(d.scheduled_at)) + '.\')">'
                    + '<input type="hidden" name="csrf_token" value="' + esc(DD_CSRF) + '">'
                    + '<button type="submit" class="btn btn-success btn-sm" style="padding:0.25rem 0.6rem;font-size:0.76rem;" title="Réactiver ce prélèvement">'
                    + '<i class="bi bi-arrow-clockwise"></i> Réactiver</button>'
                    + '</form>';
            } else {
                actionCell = '<span style="color:var(--text-muted);font-size:0.76rem;">—</span>';
            }
        } else {
            actionCell = '<span style="color:var(--text-muted);font-size:0.76rem;">—</span>';
        }

        /* Colonne Comptes : émetteur → destinataire */
        var accountsCell =
            '<div style="line-height:1.4">'
            + '<div style="font-size:0.78rem;color:var(--text-muted)"><i class="bi bi-arrow-right" style="font-size:0.65rem"></i> de <strong>' + esc(d.from_account) + '</strong></div>'
            + '<div style="font-size:0.78rem"><i class="bi bi-arrow-right" style="font-size:0.65rem"></i> vers <strong>' + esc(d.to_account) + '</strong></div>'
            + '</div>';

        /* Colonne Dates : planifié + exécuté sur deux lignes */
        var datesCell =
            '<div style="line-height:1.4;font-size:0.76rem">'
            + '<div title="Planifié le"><i class="bi bi-calendar-event" style="font-size:0.7rem;opacity:0.6"></i> ' + fmtDate(d.scheduled_at) + '</div>';
        if (d.executed_at) {
            datesCell += '<div title="Exécuté le" style="color:var(--success,#10b981)"><i class="bi bi-calendar-check" style="font-size:0.7rem;opacity:0.6"></i> ' + fmtDate(d.executed_at) + '</div>';
        }
        datesCell += '</div>';

        /* Colonne Mandat : numéro + créé par */
        var mandateCell =
            '<div>'
            + '<div style="font-family:monospace;font-size:0.76rem;word-break:break-all;max-width:170px" title="' + esc(d.mandate_number) + '">' + esc(d.mandate_number) + '</div>'
            + '<div style="font-size:0.7rem;color:var(--text-muted);margin-top:1px"><i class="bi bi-person" style="font-size:0.65rem"></i> ' + esc(d.created_by_name) + '</div>'
            + '</div>';

        html +=
            '<tr style="' + (ROW_BG[d.status] || '') + '">'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap;color:var(--text-muted);font-size:0.78rem">' + esc(String(d.id)) + '</td>'
            + '<td style="padding:0.5rem 0.8rem">' + mandateCell + '</td>'
            + '<td style="padding:0.5rem 0.8rem">' + accountsCell + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap;font-weight:600;font-size:0.9rem">' + fmtAmount(d.amount) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(d.motif || '') + '">' + esc(d.motif || '—') + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap">' + datesCell + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap">' + (STATUS_BADGE[d.status] || esc(d.status)) + '</td>'
            + '<td style="padding:0.5rem 0.8rem;white-space:nowrap;text-align:center">' + actionCell + '</td>'
            + '</tr>';
    }
    tbody.innerHTML = html;
    if (typeof initFlatpickrs === 'function') initFlatpickrs();
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

function updateStats() {
    var counts = { scheduled: 0, success: 0, failed: 0, cancelled: 0, rejected: 0 };
    DD_DATA.forEach(function(d) { if (counts.hasOwnProperty(d.status)) counts[d.status]++; });
    document.getElementById('stat-total').textContent     = DD_DATA.length;
    document.getElementById('stat-scheduled').textContent = counts.scheduled;
    document.getElementById('stat-success').textContent   = counts.success;
    document.getElementById('stat-rejected').textContent  = counts.rejected;
    document.getElementById('stat-failed').textContent    = counts.failed;
    document.getElementById('stat-cancelled').textContent = counts.cancelled;
}

function filterByStatus(status) {
    var sel = document.getElementById('dd-status');
    if (sel) sel.value = status;
    applyDdFilters();
}

['dd-status','dd-mandate','dd-amount-min','dd-amount-max','dd-date-from','dd-date-to','dd-search']
    .forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', applyDdFilters);
    });

updateStats();
renderTable();
</script>
