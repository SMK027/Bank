<?php
/**
 * @var array $friends          Liste des amis acceptés
 * @var array $pendingReceived  Demandes reçues en attente
 * @var array $pendingSent      Demandes envoyées en attente
 */
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-people-fill"></i> Mes amis</h1>
        <p class="page-description">Gérez vos amis pour partager et répartir vos dépenses.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('friend-add-section').scrollIntoView({behavior:'smooth'})">
        <i class="bi bi-person-plus"></i> Ajouter un ami
    </button>
</div>

<!-- ── Demandes reçues ────────────────────────────────────────────────── -->
<?php if (!empty($pendingReceived)): ?>
<div class="card" style="margin-bottom:1.5rem;border-left:4px solid var(--primary);">
    <div class="card-header">
        <h3><i class="bi bi-bell-fill" style="color:var(--primary);"></i>
            Demandes reçues
            <span class="badge" style="background:var(--primary);color:#fff;border-radius:20px;padding:0.1rem 0.55rem;font-size:0.82em;">
                <?= count($pendingReceived) ?>
            </span>
        </h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <tbody>
                <?php foreach ($pendingReceived as $req): ?>
                <tr>
                    <td>
                        <i class="bi bi-person-circle" style="font-size:1.2rem;color:var(--text-muted);vertical-align:middle;margin-right:0.4rem;"></i>
                        <strong><?= e($req['requester_username']) ?></strong>
                    </td>
                    <td class="text-muted" style="font-size:0.83rem;">
                        <?= date('d/m/Y', strtotime($req['created_at'])) ?>
                    </td>
                    <td style="white-space:nowrap;text-align:right;">
                        <form method="POST" action="/friends/<?= (int) $req['id'] ?>/accept" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm" style="font-size:0.82rem;">
                                <i class="bi bi-check-lg"></i> Accepter
                            </button>
                        </form>
                        <form method="POST" action="/friends/<?= (int) $req['id'] ?>/refuse" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Refuser cette demande ?')"
                                    style="font-size:0.82rem;">
                                <i class="bi bi-x-lg"></i> Refuser
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Liste des amis ────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-people"></i> Amis (<?= count($friends) ?>)</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($friends)): ?>
        <div class="empty-state" style="padding:2rem;">
            <div class="empty-icon">👫</div>
            <p>Vous n'avez pas encore d'amis.</p>
            <p class="text-muted text-small">Utilisez le formulaire ci-dessous pour envoyer une invitation.</p>
        </div>
        <?php else: ?>
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Amis depuis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($friends as $f): ?>
                <tr>
                    <td>
                        <i class="bi bi-person-check-fill" style="color:var(--success);margin-right:0.35rem;"></i>
                        <strong><?= e($f['friend_username']) ?></strong>
                    </td>
                    <td class="text-muted" style="font-size:0.85rem;">
                        <?= date('d/m/Y', strtotime($f['friends_since'])) ?>
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" action="/friends/<?= (int) $f['friend_id'] ?>/remove" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm"
                                    onclick="return confirm('Supprimer <?= e(addslashes($f['friend_username'])) ?> de vos amis ?')"
                                    style="font-size:0.82rem;color:var(--danger);border-color:var(--danger);">
                                <i class="bi bi-person-dash"></i> Retirer
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Demandes envoyées en attente ──────────────────────────────────── -->
<?php if (!empty($pendingSent)): ?>
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="bi bi-send"></i> Invitations envoyées</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <tbody>
                <?php foreach ($pendingSent as $req): ?>
                <tr>
                    <td>
                        <i class="bi bi-person-circle" style="color:var(--text-muted);margin-right:0.35rem;"></i>
                        <?= e($req['recipient_username']) ?>
                    </td>
                    <td class="text-muted" style="font-size:0.83rem;">
                        Envoyée le <?= date('d/m/Y', strtotime($req['created_at'])) ?>
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" action="/friends/<?= (int) $req['id'] ?>/cancel" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm"
                                    onclick="return confirm('Annuler cette invitation ?')"
                                    style="font-size:0.82rem;">
                                <i class="bi bi-x-lg"></i> Annuler
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Ajouter un ami ────────────────────────────────────────────────── -->
<h2 id="friend-add-section" style="margin-bottom:0.75rem;"><i class="bi bi-person-plus-fill"></i> Ajouter un ami</h2>
<div class="card" style="max-width:480px;">
    <div class="card-body">
        <form method="POST" action="/friends/send" id="friend-form">
            <?= csrf_field() ?>
            <div class="form-group" style="position:relative;">
                <label class="form-label" for="friend-input">Nom d'utilisateur <span class="text-danger">*</span></label>
                <input type="text" id="friend-input" class="form-control" placeholder="Rechercher…"
                       autocomplete="off" required>
                <input type="hidden" name="recipient_id" id="friend-recipient-id" required>
                <div id="friend-autocomplete"
                     style="display:none;position:absolute;z-index:100;background:#fff;border:1px solid var(--gray-light);border-radius:var(--border-radius-sm);width:100%;box-shadow:var(--shadow);">
                </div>
                <div id="friend-selected" style="display:none;margin-top:0.4rem;display:flex;align-items:center;gap:0.4rem;">
                    <span class="badge badge-success" id="friend-selected-badge"></span>
                    <button type="button" onclick="clearFriend()" class="btn btn-sm btn-outline"
                            style="padding:0.1rem 0.4rem;font-size:0.78rem;">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <button type="submit" id="friend-submit" class="btn btn-primary" disabled>
                <i class="bi bi-person-plus"></i> Envoyer l'invitation
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var input    = document.getElementById('friend-input');
    var hiddenId = document.getElementById('friend-recipient-id');
    var dropdown = document.getElementById('friend-autocomplete');
    var selected = document.getElementById('friend-selected');
    var badge    = document.getElementById('friend-selected-badge');
    var submit   = document.getElementById('friend-submit');
    var timer    = null;

    window.clearFriend = function () {
        hiddenId.value = '';
        input.value    = '';
        selected.style.display = 'none';
        input.style.display    = '';
        submit.disabled        = true;
    };

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('/friends/search-users?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (users) {
                    dropdown.innerHTML = '';
                    if (!users.length) { dropdown.style.display = 'none'; return; }
                    users.forEach(function (u) {
                        var item = document.createElement('div');
                        item.style.cssText = 'padding:0.5rem 0.75rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center;';
                        var nameSpan = document.createElement('span');
                        nameSpan.innerHTML = '<i class="bi bi-person"></i> ' + u.username;
                        item.appendChild(nameSpan);
                        if (u.is_friend) {
                            var fbadge = document.createElement('span');
                            fbadge.className = 'badge badge-success';
                            fbadge.style.fontSize = '0.75em';
                            fbadge.textContent = 'Déjà ami';
                            item.appendChild(fbadge);
                        }
                        item.addEventListener('mouseenter', function () { item.style.background = 'var(--gray-lighter,#f3f4f6)'; });
                        item.addEventListener('mouseleave', function () { item.style.background = ''; });
                        item.addEventListener('click', function () {
                            hiddenId.value = u.id;
                            badge.textContent = '👤 ' + u.username;
                            selected.style.display = 'flex';
                            input.style.display    = 'none';
                            dropdown.style.display = 'none';
                            submit.disabled        = false;
                        });
                        dropdown.appendChild(item);
                    });
                    dropdown.style.display = 'block';
                });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== input) {
            dropdown.style.display = 'none';
        }
    });
})();
</script>
