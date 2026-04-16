<?php
/** @var bool  $isModerator */
/** @var array $recipients  (modérateurs uniquement — liste des utilisateurs) */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-pencil-square"></i> Nouveau message</h1>
        <p class="page-description">
            <?php if ($isModerator): ?>
                Envoyez un message à un ou plusieurs utilisateurs, ou démarrez une conversation entre modérateurs.
            <?php else: ?>
                Envoyez un message à l'équipe de modération.
            <?php endif; ?>
        </p>
    </div>
    <a href="/messages" class="btn btn-outline"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/messages" id="compose-form">
            <?= csrf_field() ?>

            <?php if ($isModerator): ?>
            <!-- Type de conversation -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Type de conversation</label>
                <select name="type" id="conv-type" class="form-control" required>
                    <option value="mod_user">💬 Vers un/plusieurs utilisateurs</option>
                    <option value="mod_only">🛡️ Entre modérateurs uniquement</option>
                </select>
            </div>

            <!-- Choix des destinataires -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Destinataire(s)</label>
                <div id="selected-recipients" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.5rem;"></div>
                <input type="text" id="recipient-search" class="form-control"
                       placeholder="Rechercher un utilisateur par nom…"
                       autocomplete="off">
                <div id="recipient-results" style="border:1px solid var(--border-color);border-top:none;border-radius:0 0 var(--radius) var(--radius);max-height:200px;overflow-y:auto;display:none;background:var(--bg-primary);"></div>
                <p class="text-muted" style="font-size:0.78rem;margin-top:0.3rem;">
                    Tapez au moins 2 caractères pour chercher.
                </p>
            </div>
            <?php else: ?>
            <input type="hidden" name="type" value="mod_user">
            <div class="alert alert-info" style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                <i class="bi bi-info-circle"></i>
                <span>Votre message sera envoyé à l'équipe de modération.</span>
            </div>
            <?php endif; ?>

            <!-- Sujet -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label for="msg-subject" class="form-label">Sujet</label>
                <input type="text" id="msg-subject" name="subject" class="form-control"
                       required minlength="2" maxlength="255" placeholder="Objet du message">
            </div>

            <!-- Corps du message -->
            <div class="form-group" style="margin-bottom:1rem;">
                <label for="msg-body" class="form-label">Message</label>
                <textarea id="msg-body" name="body" class="form-control" rows="6"
                          required minlength="1" placeholder="Écrivez votre message…"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Envoyer
            </button>
        </form>
    </div>
</div>

<?php if ($isModerator): ?>
<script>
(function () {
    var searchInput  = document.getElementById('recipient-search');
    var resultsDiv   = document.getElementById('recipient-results');
    var selectedDiv  = document.getElementById('selected-recipients');
    var typeSelect   = document.getElementById('conv-type');
    var selectedIds  = {};
    var searchTimeout;

    function renderSelected() {
        selectedDiv.innerHTML = '';
        for (var id in selectedIds) {
            var tag = document.createElement('span');
            tag.className = 'badge badge-primary';
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:0.3rem;padding:0.3rem 0.6rem;font-size:0.82rem;cursor:pointer;';
            tag.dataset.id = id;
            tag.innerHTML = selectedIds[id].name +
                (selectedIds[id].role === 'moderator' ? ' <i class="bi bi-shield-check" style="font-size:0.7rem;"></i>' : '') +
                ' <i class="bi bi-x-lg" style="font-size:0.65rem;"></i>';
            tag.title = 'Cliquer pour retirer';
            tag.addEventListener('click', function () {
                delete selectedIds[this.dataset.id];
                renderSelected();
                updateHiddenInputs();
            });
            selectedDiv.appendChild(tag);
        }
    }

    function updateHiddenInputs() {
        // Remove existing hidden inputs
        document.querySelectorAll('input[name="recipients[]"]').forEach(function (el) { el.remove(); });
        for (var id in selectedIds) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'recipients[]';
            input.value = id;
            document.getElementById('compose-form').appendChild(input);
        }
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        var q = this.value.trim();
        if (q.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }
        searchTimeout = setTimeout(function () {
            fetch('/messages/search-users?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (users) {
                resultsDiv.innerHTML = '';
                var convType = typeSelect.value;
                var filtered = users.filter(function (u) {
                    if (selectedIds[u.id]) return false;
                    if (convType === 'mod_only' && u.global_role !== 'moderator') return false;
                    return true;
                });
                if (filtered.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding:0.5rem 0.75rem;color:var(--text-secondary);font-size:0.85rem;">Aucun résultat</div>';
                } else {
                    filtered.forEach(function (u) {
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:0.5rem 0.75rem;cursor:pointer;display:flex;align-items:center;gap:0.4rem;font-size:0.88rem;';
                        row.innerHTML = '<i class="bi bi-person"></i> ' + u.username +
                            (u.global_role === 'moderator' ? ' <span class="badge badge-warning" style="font-size:0.65rem;margin-left:0.3rem;">Mod</span>' : '');
                        row.addEventListener('mouseenter', function () { this.style.background = 'var(--bg-secondary)'; });
                        row.addEventListener('mouseleave', function () { this.style.background = ''; });
                        row.addEventListener('click', function () {
                            selectedIds[u.id] = { name: u.username, role: u.global_role };
                            renderSelected();
                            updateHiddenInputs();
                            searchInput.value = '';
                            resultsDiv.style.display = 'none';
                        });
                        resultsDiv.appendChild(row);
                    });
                }
                resultsDiv.style.display = '';
            });
        }, 300);
    });

    // Cacher les résultats au clic en dehors
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });

    // Filtrer les destinataires décochés quand on change de type
    typeSelect.addEventListener('change', function () {
        if (this.value === 'mod_only') {
            for (var id in selectedIds) {
                if (selectedIds[id].role !== 'moderator') {
                    delete selectedIds[id];
                }
            }
            renderSelected();
            updateHiddenInputs();
        }
    });
})();
</script>
<?php endif; ?>
