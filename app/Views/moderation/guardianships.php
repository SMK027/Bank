<div class="page-header">
    <div>
        <h1><i class="bi bi-person-lock"></i> Tutelles légales</h1>
        <p class="page-description">Responsables légaux des comptes mineurs</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/minor-accounts/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nouveau compte mineur</a>
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-person-lock"></i> Tutelles légales</span>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
        <a href="/moderation/savings-rate" class="btn btn-outline btn-sm"><i class="bi bi-percent"></i> Taux d'intérêt</a>
    </div>
</div>

<div class="alert" style="background:rgba(var(--info-rgb,59,130,246),0.08);border-left:4px solid #3b82f6;display:flex;align-items:flex-start;gap:0.75rem;padding:0.9rem 1.1rem;margin-bottom:1.5rem;">
    <i class="bi bi-info-circle" style="font-size:1.1rem;color:#3b82f6;flex-shrink:0;"></i>
    <div style="font-size:0.84rem;">
        Les responsables légaux ont <strong>procuration automatique</strong> sur tous les comptes du mineur.
        Cette procuration <strong>expire automatiquement</strong> dès que le mineur atteint 18 ans.
        Les historiques de tutelle sont conservés même après l'expiration.
    </div>
</div>

<?php if (empty($guardianships)): ?>
    <div class="empty-state">
        <div class="empty-icon">🔏</div>
        <p>Aucune tutelle légale enregistrée.</p>
        <a href="/moderation/minor-accounts/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Créer un compte mineur
        </a>
    </div>
<?php else: ?>

<?php
// Grouper les tutelles par mineur pour un affichage plus lisible
$byMinor = [];
foreach ($guardianships as $g) {
    $byMinor[$g['minor_user_id']][] = $g;
}
?>

<div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;flex-wrap:wrap;">
    <div style="display:flex;border:1px solid var(--border-color);border-radius:6px;overflow:hidden;flex-shrink:0;">
        <button id="filter-by-minor" onclick="setFilterMode('minor')"
                class="btn btn-sm"
                style="border-radius:0;border:none;padding:0.35rem 0.9rem;font-size:0.82rem;background:var(--primary,#3b82f6);color:#fff;">
            <i class="bi bi-person-badge"></i> Par enfant
        </button>
        <button id="filter-by-guardian" onclick="setFilterMode('guardian')"
                class="btn btn-sm"
                style="border-radius:0;border:none;border-left:1px solid var(--border-color);padding:0.35rem 0.9rem;font-size:0.82rem;background:var(--card-bg,#fff);color:var(--text-muted);">
            <i class="bi bi-person-check"></i> Par tuteur
        </button>
    </div>
    <div style="position:relative;flex:1;min-width:180px;max-width:340px;">
        <i class="bi bi-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.82rem;pointer-events:none;"></i>
        <input type="text" id="guardianship-filter"
               placeholder="Filtrer…"
               autocomplete="off"
               oninput="applyFilter()"
               style="width:100%;padding:0.35rem 0.6rem 0.35rem 1.9rem;font-size:0.83rem;border:1px solid var(--border-color);border-radius:6px;background:var(--input-bg,#fff);color:var(--text);box-sizing:border-box;">
    </div>
    <span id="filter-count" style="font-size:0.78rem;color:var(--text-muted);"></span>
</div>

<div id="guardianship-list" style="display:flex;flex-direction:column;gap:1rem;">
<?php foreach ($byMinor as $minorId => $links): ?>
<?php $first = $links[0]; ?>
<?php $guardianNames = implode(' ', array_column($links, 'guardian_username')); ?>
<div class="card guardianship-card"
     data-minor="<?= e(strtolower($first['minor_username'])) ?>"
     data-guardians="<?= e(strtolower($guardianNames)) ?>">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <i class="bi bi-person-badge" style="font-size:1.1rem;"></i>
            <span class="font-bold"><?= e($first['minor_username']) ?></span>
            <?php if (!empty($first['minor_birth_date'])): ?>
                <span class="text-small text-muted">
                    — né(e) le <?= date('d/m/Y', strtotime($first['minor_birth_date'])) ?>
                </span>
            <?php endif; ?>
            <?php if ($first['is_still_minor']): ?>
                <span class="badge badge-warning" style="background:#f59e0b;color:#fff;">
                    <i class="bi bi-person-arms-up"></i> Mineur
                </span>
            <?php else: ?>
                <span class="badge badge-secondary" title="Ce membre est devenu majeur — les procurations sont expirées.">
                    <i class="bi bi-person-check"></i> Majeur (procurations expirées)
                </span>
            <?php endif; ?>
        </div>
        <?php if ($first['is_still_minor'] && count($links) < 2): ?>
        <?php $fid = 'inline_g_' . (int) $minorId; ?>
        <form method="POST"
              action="/moderation/guardianships/<?= (int) $minorId ?>/add"
              style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;"
              onsubmit="return document.getElementById('<?= $fid ?>_id').value !== '' || (alert('Veuillez sélectionner un responsable légal.'), false)">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="guardian_user_id" id="<?= $fid ?>_id" value="">
            <div class="ac-wrap" style="position:relative;min-width:200px;">
                <input type="text" id="<?= $fid ?>_search" class="form-control"
                       placeholder="Rechercher un adulte…" autocomplete="off"
                       style="padding:0.3rem 0.6rem;font-size:0.82rem;">
                <div id="<?= $fid ?>_results" class="ac-results" style="display:none;position:absolute;z-index:200;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                <div id="<?= $fid ?>_selected" style="display:none;align-items:center;gap:0.4rem;margin-top:0.25rem;background:rgba(59,130,246,0.08);border-radius:5px;padding:0.2rem 0.6rem;font-size:0.8rem;">
                    <i class="bi bi-person-check" style="color:#3b82f6;"></i>
                    <span id="<?= $fid ?>_label"></span>
                    <button type="button" onclick="clearUserAc('<?= $fid ?>')" style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;color:var(--text-muted);font-size:1rem;line-height:1;">×</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Ajouter
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="table" style="margin:0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Responsable légal</th>
                    <th>Ajouté le</th>
                    <th>Statut</th>
                    <th style="text-align:right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $g): ?>
                <tr>
                    <td class="text-muted text-small"><?= (int) $g['id'] ?></td>
                    <td class="font-bold">
                        <i class="bi bi-person-check text-success"></i>
                        <?= e($g['guardian_username']) ?>
                    </td>
                    <td class="text-small text-muted">
                        <?= date('d/m/Y', strtotime($g['created_at'])) ?>
                    </td>
                    <td>
                        <?php if ($g['is_still_minor']): ?>
                            <span class="badge badge-success">
                                <i class="bi bi-check-circle"></i> Procuration active
                            </span>
                        <?php else: ?>
                            <span class="badge badge-secondary">
                                <i class="bi bi-clock-history"></i> Expirée (mineur devenu majeur)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right">
                        <form method="POST"
                              action="/moderation/guardianships/<?= (int) $g['id'] ?>/remove"
                              style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Supprimer la tutelle de <?= e(addslashes($g['guardian_username'])) ?> sur <?= e(addslashes($g['minor_username'])) ?> ?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<?php if (!empty($minorUsers)): ?>
<div class="card mt-3" style="max-width:540px;">
    <div class="card-header">
        <h3 style="margin:0;font-size:0.95rem;">
            <i class="bi bi-person-plus"></i> Ajouter une tutelle
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="add-guardianship-form"
              onsubmit="
                var mid = document.getElementById('add_minor_id').value;
                var gid = document.getElementById('add_guardian_id').value;
                if (!mid) { alert('Veuillez sélectionner un mineur.'); return false; }
                if (!gid) { alert('Veuillez sélectionner un responsable légal.'); return false; }
                this.action = '/moderation/guardianships/' + mid + '/add';
              ">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="form-row" style="gap:0.75rem;align-items:flex-start;">
                <div class="form-group" style="margin:0;flex:1;">
                    <label class="form-label" style="font-size:0.8rem;">Mineur</label>
                    <input type="hidden" id="add_minor_id" value="">
                    <div class="ac-wrap" style="position:relative;">
                        <input type="text" id="add_minor_search" class="form-control"
                               placeholder="Rechercher un mineur…" autocomplete="off"
                               style="font-size:0.84rem;padding:0.35rem 0.6rem;">
                        <div id="add_minor_results" class="ac-results" style="display:none;position:absolute;z-index:200;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                        <div id="add_minor_selected" style="display:none;align-items:center;gap:0.4rem;margin-top:0.25rem;background:rgba(59,130,246,0.08);border-radius:5px;padding:0.2rem 0.6rem;font-size:0.8rem;">
                            <i class="bi bi-person-badge" style="color:#3b82f6;"></i>
                            <span id="add_minor_label"></span>
                            <button type="button" onclick="clearUserAc('add_minor')" style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;color:var(--text-muted);font-size:1rem;line-height:1;">×</button>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin:0;flex:1;">
                    <label class="form-label" style="font-size:0.8rem;">Responsable légal (adulte)</label>
                    <input type="hidden" name="guardian_user_id" id="add_guardian_id" value="">
                    <div class="ac-wrap" style="position:relative;">
                        <input type="text" id="add_guardian_search" class="form-control"
                               placeholder="Rechercher un adulte…" autocomplete="off"
                               style="font-size:0.84rem;padding:0.35rem 0.6rem;">
                        <div id="add_guardian_results" class="ac-results" style="display:none;position:absolute;z-index:200;width:100%;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:4px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                        <div id="add_guardian_selected" style="display:none;align-items:center;gap:0.4rem;margin-top:0.25rem;background:rgba(59,130,246,0.08);border-radius:5px;padding:0.2rem 0.6rem;font-size:0.8rem;">
                            <i class="bi bi-person-check" style="color:#3b82f6;"></i>
                            <span id="add_guardian_label"></span>
                            <button type="button" onclick="clearUserAc('add_guardian')" style="background:none;border:none;cursor:pointer;padding:0 0 0 0.3rem;color:var(--text-muted);font-size:1rem;line-height:1;">×</button>
                        </div>
                    </div>
                </div>
                <div style="padding-top:1.55rem;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Ajouter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
var USER_SEARCH_URL = '/moderation/users/search';
var _filterMode = 'minor';

function setFilterMode(mode) {
    _filterMode = mode;
    var btnMinor    = document.getElementById('filter-by-minor');
    var btnGuardian = document.getElementById('filter-by-guardian');
    if (mode === 'minor') {
        btnMinor.style.background    = 'var(--primary,#3b82f6)';
        btnMinor.style.color         = '#fff';
        btnGuardian.style.background = 'var(--card-bg,#fff)';
        btnGuardian.style.color      = 'var(--text-muted)';
        document.getElementById('guardianship-filter').placeholder = 'Filtrer par enfant…';
    } else {
        btnGuardian.style.background = 'var(--primary,#3b82f6)';
        btnGuardian.style.color      = '#fff';
        btnMinor.style.background    = 'var(--card-bg,#fff)';
        btnMinor.style.color         = 'var(--text-muted)';
        document.getElementById('guardianship-filter').placeholder = 'Filtrer par tuteur…';
    }
    applyFilter();
}

function applyFilter() {
    var q     = document.getElementById('guardianship-filter').value.trim().toLowerCase();
    var cards = document.querySelectorAll('.guardianship-card');
    var shown = 0;
    cards.forEach(function(card) {
        var haystack = _filterMode === 'minor'
            ? card.dataset.minor
            : card.dataset.guardians;
        var match = !q || haystack.indexOf(q) !== -1;
        card.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    var countEl = document.getElementById('filter-count');
    if (q) {
        countEl.textContent = shown + ' résultat' + (shown > 1 ? 's' : '');
    } else {
        countEl.textContent = '';
    }
}

var _acTimers = {};

function setupUserAc(prefix, type) {
    var input   = document.getElementById(prefix + '_search');
    var results = document.getElementById(prefix + '_results');
    if (!input || !results) return;

    input.addEventListener('input', function() {
        clearTimeout(_acTimers[prefix]);
        var q = this.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }

        _acTimers[prefix] = setTimeout(function() {
            fetch(USER_SEARCH_URL + '?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) {
                    results.innerHTML = '<div style="padding:0.6rem 0.9rem;color:var(--text-muted);font-size:0.82rem;">Aucun résultat</div>';
                } else {
                    var html = '';
                    data.forEach(function(item) {
                        html += '<div class="uac-item" data-id="' + item.id + '" data-username="' + _escAttr(item.username) + '" data-email="' + _escAttr(item.email) + '"'
                              + ' style="padding:0.5rem 0.9rem;cursor:pointer;font-size:0.83rem;border-bottom:1px solid var(--border-color);">'
                              + '<strong>' + _esc(item.username) + '</strong>'
                              + ' <span style="color:var(--text-muted);font-size:0.78rem;">' + _esc(item.email) + '</span>'
                              + '</div>';
                    });
                    results.innerHTML = html;
                    results.querySelectorAll('.uac-item').forEach(function(el) {
                        el.addEventListener('mouseenter', function() { this.style.background = 'var(--bg-secondary)'; });
                        el.addEventListener('mouseleave', function() { this.style.background = ''; });
                        el.addEventListener('click', function() {
                            selectUserAc(prefix, this.dataset.id, this.dataset.username, this.dataset.email);
                        });
                    });
                }
                results.style.display = 'block';
            })
            .catch(function() { results.style.display = 'none'; });
        }, 280);
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });
}

function selectUserAc(prefix, id, username, email) {
    document.getElementById(prefix + '_id').value                = id;
    document.getElementById(prefix + '_label').textContent       = username + ' — ' + email;
    document.getElementById(prefix + '_selected').style.display  = 'flex';
    document.getElementById(prefix + '_search').value            = '';
    document.getElementById(prefix + '_results').style.display   = 'none';
}

function clearUserAc(prefix) {
    document.getElementById(prefix + '_id').value                = '';
    document.getElementById(prefix + '_label').textContent       = '';
    document.getElementById(prefix + '_selected').style.display  = 'none';
}

function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function _escAttr(s) { return String(s).replace(/"/g,'&quot;'); }

// Formulaire global en bas
setupUserAc('add_minor',    'minor');
setupUserAc('add_guardian', 'adult');

// Formulaires inline (un par mineur)
document.querySelectorAll('[id^="inline_g_"][id$="_search"]').forEach(function(el) {
    var prefix = el.id.replace('_search', '');
    setupUserAc(prefix, 'adult');
});
</script>
