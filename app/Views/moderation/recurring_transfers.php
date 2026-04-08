<div class="page-header">
    <div>
        <h1><i class="bi bi-arrow-repeat"></i> Modération — Virements récurrents</h1>
        <p class="page-description">Gestion des virements récurrents et reprogrammation</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-arrow-repeat"></i> Virements récurrents</span>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
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
        <div class="empty-icon">🔄</div>
        <p>Aucun virement récurrent enregistré.</p>
    </div>
<?php else: ?>

<h4 style="margin-bottom:0.9rem;font-size:1rem;font-weight:600">
    <span id="rt-count-badge" style="font-size:0.82rem;font-weight:400;color:var(--text-muted)"></span>
</h4>

<!-- Barre de filtres -->
<div class="card mb-2">
    <div class="card-body" style="padding:0.9rem 1.1rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem 0.75rem;">
            <div style="position:relative">
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Utilisateur</label>
                <input type="text" id="rt-author-search" class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto"
                       placeholder="Tous…" autocomplete="off">
                <div id="rt-author-results"
                     style="display:none;position:absolute;z-index:300;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);top:calc(100% + 2px);left:0;"></div>
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Statut</label>
                <select id="rt-status" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto">
                    <option value="">Tous</option>
                    <option value="active">Actif</option>
                    <option value="cancelled">Annulé</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant min.</label>
                <input type="number" id="rt-amount-min" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" min="0" step="0.01" placeholder="0.00">
            </div>
            <div>
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Montant max.</label>
                <input type="number" id="rt-amount-max" class="form-control" style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto" min="0" step="0.01" placeholder="—">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:0.6rem">
            <button type="button" onclick="resetRtFilters()" class="btn btn-outline btn-sm">
                <i class="bi bi-x-circle"></i> Réinitialiser
            </button>
        </div>
    </div>
</div>

<!-- Tableau -->
<div class="table-responsive" style="border-radius:6px;border:1px solid var(--border-color,#e2e8f0);overflow:hidden">
    <table class="table" id="rt-table" style="margin:0;font-size:0.84rem">
        <thead>
            <tr>
                <th style="white-space:nowrap">#</th>
                <th style="white-space:nowrap">Utilisateur</th>
                <th style="white-space:nowrap">Émetteur</th>
                <th style="white-space:nowrap">Destinataire</th>
                <th style="white-space:nowrap">Montant</th>
                <th>Motif</th>
                <th style="white-space:nowrap">Intervalle</th>
                <th style="white-space:nowrap">Statut</th>
                <th style="white-space:nowrap">Prochaine exéc.</th>
                <th style="white-space:nowrap">Dernière exéc.</th>
                <th style="white-space:nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="rt-body"></tbody>
    </table>
</div>
<p id="rt-empty" style="display:none;text-align:center;color:var(--text-muted);padding:1.2rem 0;font-size:0.88rem">
    <i class="bi bi-search"></i> Aucun virement récurrent ne correspond à vos critères.
</p>

<?php endif; ?>

<p class="text-center text-muted text-small mt-2">
    <a href="/moderation"><i class="bi bi-arrow-left"></i> Retour à la modération</a>
</p>

<script>
(function () {
    var RT_LIST   = <?= $rtJson ?? '[]' ?>;
    var RT_CSRF   = <?= json_encode($csrfToken ?? '') ?>;
    var RT_AUTHORS = <?= json_encode(array_values($rtAuthors ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    var STATUS_LABELS = { 'active': 'Actif', 'cancelled': 'Annulé' };
    var STATUS_BADGES = { 'active': 'badge-success', 'cancelled': 'badge-secondary' };

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
    function fmtDateInput(s) {
        if (!s) return '';
        var d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return '';
        var pad = function(n){ return String(n).padStart(2,'0'); };
        return pad(d.getDate()) + '/' + pad(d.getMonth()+1) + '/' + d.getFullYear()
             + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function esc(s) {
        return String(s || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var rtVisible = RT_LIST.slice();

    function applyFilters() {
        var author    = (document.getElementById('rt-author-search').value || '').toLowerCase().trim();
        var status    = document.getElementById('rt-status').value;
        var amtMin    = parseFloat(document.getElementById('rt-amount-min').value) || 0;
        var amtMax    = parseFloat(document.getElementById('rt-amount-max').value) || Infinity;

        rtVisible = RT_LIST.filter(function (rt) {
            if (author && !(rt.user_name || '').toLowerCase().includes(author)) return false;
            if (status && rt.status !== status) return false;
            var a = parseFloat(rt.amount) || 0;
            if (a < amtMin || a > amtMax) return false;
            return true;
        });
        renderTable(rtVisible);
    }

    function resetRtFilters() {
        document.getElementById('rt-author-search').value = '';
        document.getElementById('rt-status').value        = '';
        document.getElementById('rt-amount-min').value    = '';
        document.getElementById('rt-amount-max').value    = '';
        rtVisible = RT_LIST.slice();
        renderTable(rtVisible);
    }
    window.resetRtFilters = resetRtFilters;

    function renderTable(list) {
        var tbody = document.getElementById('rt-body');
        var empty = document.getElementById('rt-empty');
        var badge = document.getElementById('rt-count-badge');
        if (!tbody) return;
        if (badge) badge.textContent = list.length + ' virement' + (list.length > 1 ? 's' : '')
            + ' récurrent' + (list.length > 1 ? 's' : '')
            + ' affiché' + (list.length > 1 ? 's' : '') + ' sur ' + RT_LIST.length;
        if (list.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.style.display = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        var html = '';
        list.forEach(function (rt) {
            var st   = rt.status || 'active';
            var bCls = STATUS_BADGES[st] || 'badge-secondary';
            var bLbl = STATUS_LABELS[st] || st;
            var motifCell = rt.motif
                ? '<span title="' + esc(rt.motif) + '">' + esc(rt.motif) + '</span>'
                : '<span style="color:var(--text-muted)">—</span>';
            var intervalCell = rt.interval_days
                ? rt.interval_days + '\u00a0j'
                : '—';
            var actionCell;
            if (st === 'active') {
                var curDate = fmtDateInput(rt.next_execution_at);
                actionCell =
                    '<form method="POST" action="/moderation/recurring-transfers/' + esc(String(rt.id)) + '/reschedule"'
                    + ' style="display:flex;align-items:center;gap:0.3rem;">'
                    + '<input type="hidden" name="csrf_token" value="' + esc(RT_CSRF) + '">'
                    + '<input type="text" name="next_execution_at"'
                    + ' value="' + esc(curDate) + '"'
                    + ' placeholder="jj/mm/aaaa hh:mm"'
                    + ' title="Nouvelle date d\'exécution"'
                    + ' style="font-size:0.76rem;padding:0.2rem 0.4rem;border:1px solid var(--border-color);border-radius:4px;background:var(--input-bg,#fff);color:var(--text-color);width:130px">'
                    + '<button type="submit" class="btn btn-outline btn-sm" style="padding:0.25rem 0.5rem;font-size:0.76rem;" title="Reprogrammer la prochaine exécution">'
                    + '<i class="bi bi-calendar2-event"></i></button>'
                    + '</form>';
            } else {
                actionCell = '<span style="color:var(--text-muted);font-size:0.78rem">\u2014</span>';
            }
            html += '<tr>'
                + '<td style="color:var(--text-muted)">#' + esc(String(rt.id)) + '</td>'
                + '<td>' + esc(rt.user_name) + '</td>'
                + '<td style="white-space:nowrap">' + esc(rt.from_account) + '</td>'
                + '<td style="white-space:nowrap">' + esc(rt.to_account) + '</td>'
                + '<td style="white-space:nowrap;font-weight:600">' + fmt(rt.amount) + '\u00a0€</td>'
                + '<td>' + motifCell + '</td>'
                + '<td style="white-space:nowrap">' + intervalCell + '</td>'
                + '<td><span class="badge ' + bCls + '">' + bLbl + '</span></td>'
                + '<td style="white-space:nowrap">' + fmtDate(rt.next_execution_at) + '</td>'
                + '<td style="white-space:nowrap">' + fmtDate(rt.last_executed_at) + '</td>'
                + '<td style="white-space:nowrap">' + actionCell + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
    }

    // Autocomplete auteur
    (function () {
        var input   = document.getElementById('rt-author-search');
        var results = document.getElementById('rt-author-results');
        if (!input || !results) return;
        input.addEventListener('input', function () {
            var v = input.value.toLowerCase().trim();
            if (!v) { results.style.display = 'none'; applyFilters(); return; }
            var matches = RT_AUTHORS.filter(function (a) { return a.toLowerCase().includes(v); });
            if (!matches.length) { results.style.display = 'none'; applyFilters(); return; }
            results.innerHTML = matches.map(function (a) {
                return '<div style="padding:0.35rem 0.6rem;cursor:pointer;font-size:0.83rem" onmousedown="event.preventDefault();document.getElementById(\'rt-author-search\').value=\'' + esc(a) + '\';document.getElementById(\'rt-author-results\').style.display=\'none\';applyRtFilters();">' + esc(a) + '</div>';
            }).join('');
            results.style.display = 'block';
            applyFilters();
        });
        document.addEventListener('click', function (e) {
            if (!results.contains(e.target) && e.target !== input) results.style.display = 'none';
        });
    })();
    window.applyRtFilters = applyFilters;

    ['rt-status','rt-amount-min','rt-amount-max'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', applyFilters);
        if (el) el.addEventListener('input', applyFilters);
    });

    renderTable(RT_LIST);
})();
</script>
