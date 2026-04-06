<div class="page-header">
    <div>
        <h1><i class="bi bi-arrow-left-right"></i> Modération — Virements</h1>
        <p class="page-description">Historique de tous les virements</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-arrow-left-right"></i> Virements</span>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
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
            <div style="position:relative">
                <label style="font-size:0.76rem;font-weight:600;margin-bottom:2px;display:block">Auteur</label>
                <input type="text" id="tf-author-search"
                       class="form-control"
                       style="font-size:0.82rem;padding:0.3rem 0.5rem;height:auto"
                       placeholder="Tous…"
                       autocomplete="off">
                <div id="tf-author-results"
                     style="display:none;position:absolute;z-index:300;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);top:calc(100% + 2px);left:0;"></div>
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
                <th style="white-space:nowrap">Actions</th>
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
    var TRANSFERS   = <?= $transfersJson ?? '[]' ?>;
    var TF_CSRF     = <?= json_encode($csrfToken ?? '') ?>;
    var TF_AUTHORS  = <?= json_encode(array_values($tfAuthors ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

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

    var SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;
    function canCancel(t) {
        var st = t.status || '';
        if (st === 'cancelled' || st === 'failed') return false;
        if (st === 'scheduled') return true;
        if (st === 'success') {
            var ref = t.executed_at || t.created_at;
            if (!ref) return false;
            return (Date.now() - new Date(ref.replace(' ', 'T')).getTime()) <= SEVEN_DAYS_MS;
        }
        return false;
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
            var actionCell;
            if (canCancel(t)) {
                actionCell = '<form method="POST" action="/moderation/transfers/' + esc(String(t.id)) + '/cancel"'
                    + ' style="display:inline">'
                    + '<input type="hidden" name="csrf_token" value="' + esc(TF_CSRF) + '">'
                    + '<button type="submit" class="btn btn-danger btn-sm"'
                    + ' style="font-size:0.75rem;padding:0.2rem 0.55rem"'
                    + ' onclick="return confirm(\'Annuler le virement #' + t.id + ' de ' + fmt(t.amount) + ' ?\u00a0\\nCette action est irr\u00e9versible.\')">'
                    + '<i class="bi bi-x-circle"></i> Annuler</button>'
                    + '</form>';
            } else {
                actionCell = '<span style="color:var(--text-muted);font-size:0.78rem">\u2014</span>';
            }
            html += '<tr>'
                + '<td style="color:var(--text-muted)">#' + esc(String(t.id)) + '</td>'
                + '<td>' + esc(t.user_name) + '</td>'
                + '<td>' + esc(t.from_account) + '</td>'
                + '<td>' + esc(t.to_account) + '</td>'
                + '<td style="white-space:nowrap;font-weight:600">' + fmt(t.amount) + '</td>'
                + '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + motifCell + '</td>'
                + '<td><span class="badge ' + bCls + '">' + bLbl + '</span></td>'
                + '<td style="white-space:nowrap;font-size:0.78rem">' + fmtDate(t.scheduled_at) + '</td>'
                + '<td style="white-space:nowrap;font-size:0.78rem">' + fmtDate(t.executed_at) + '</td>'
                + '<td style="white-space:nowrap">' + actionCell + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
    }

    // ── Autocomplete auteur ─────────────────────────────────────────────
    (function () {
        var input   = document.getElementById('tf-author-search');
        var results = document.getElementById('tf-author-results');
        if (!input || !results) return;

        function showSuggestions(q) {
            var lower = q.toLowerCase();
            var matches = q === ''
                ? TF_AUTHORS
                : TF_AUTHORS.filter(function (n) { return n.toLowerCase().indexOf(lower) !== -1; });
            if (!matches.length) { results.style.display = 'none'; return; }
            var html = '';
            matches.forEach(function (n) {
                html += '<div class="tf-ac-item" style="padding:0.45rem 0.75rem;cursor:pointer;font-size:0.83rem;border-bottom:1px solid var(--border-color);"'
                     + ' data-name="' + n.replace(/"/g, '&quot;') + '">'
                     + n.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
            });
            results.innerHTML = html;
            results.querySelectorAll('.tf-ac-item').forEach(function (el) {
                el.addEventListener('mouseenter', function () { this.style.background = 'var(--bg-secondary)'; });
                el.addEventListener('mouseleave', function () { this.style.background = ''; });
                el.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = this.dataset.name;
                    results.style.display = 'none';
                    filterTransfers();
                });
            });
            results.style.display = 'block';
        }

        input.addEventListener('input',  function () { showSuggestions(this.value.trim()); filterTransfers(); });
        input.addEventListener('focus',  function () { if (this.value.trim() === '') showSuggestions(''); });
        input.addEventListener('blur',   function () { setTimeout(function () { results.style.display = 'none'; }, 150); });
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !results.contains(e.target)) results.style.display = 'none';
        });
    })();

    function filterTransfers() {
        var author   = ((document.getElementById('tf-author-search') || {value:''}).value || '').trim().toLowerCase();
        var status   = (document.getElementById('tf-status')     || {value:''}).value;
        var amtMinEl = document.getElementById('tf-amount-min');
        var amtMaxEl = document.getElementById('tf-amount-max');
        var amtMin   = amtMinEl && amtMinEl.value !== '' ? parseFloat(amtMinEl.value) : -Infinity;
        var amtMax   = amtMaxEl && amtMaxEl.value !== '' ? parseFloat(amtMaxEl.value) :  Infinity;
        var dateFrom = (document.getElementById('tf-date-from')  || {value:''}).value;
        var dateTo   = (document.getElementById('tf-date-to')    || {value:''}).value;
        var motif    = ((document.getElementById('tf-motif')     || {value:''}).value || '').toLowerCase().trim();

        var filtered = TRANSFERS.filter(function (t) {
            if (author && t.user_name.toLowerCase().indexOf(author) === -1)               return false;
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

    ['tf-status','tf-amount-min','tf-amount-max','tf-date-from','tf-date-to'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', filterTransfers);
    });
    var motifEl = document.getElementById('tf-motif');
    if (motifEl) {
        motifEl.addEventListener('input',  filterTransfers);
        motifEl.addEventListener('change', filterTransfers);
    }

    window.resetTfFilters = function () {
        ['tf-status','tf-amount-min','tf-amount-max','tf-date-from','tf-date-to','tf-motif'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var authorEl = document.getElementById('tf-author-search');
        if (authorEl) authorEl.value = '';
        filterTransfers();
    };

    renderTransfers(TRANSFERS);
})();
</script>
