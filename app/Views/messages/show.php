<?php
/**
 * @var array $conversation   Conversation courante
 * @var array $messages       Liste des messages
 * @var array $participants   Participants avec infos utilisateur
 * @var bool  $isModerator
 */
$isClosed = !empty($conversation['is_closed']);
$currentUserId = (int) (current_user_id() ?? 0);
?>
<div class="page-header">
    <div>
        <h1>
            <i class="bi bi-envelope-open"></i> <?= e($conversation['subject']) ?>
            <?php if ($isClosed): ?>
                <span class="badge" style="background:var(--text-secondary);color:#fff;font-size:0.55em;vertical-align:middle;">
                    <i class="bi bi-lock-fill"></i> Clôturée
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-description">
            <?php if ($conversation['type'] === 'mod_only'): ?>
                <span class="badge badge-warning" style="font-size:0.78rem;"><i class="bi bi-shield-check"></i> Conversation entre modérateurs</span>
            <?php else: ?>
                <span class="badge badge-info" style="font-size:0.78rem;"><i class="bi bi-people"></i> Modération / Utilisateur</span>
            <?php endif; ?>
            &nbsp; Créée le <?= date('d/m/Y à H:i', strtotime($conversation['created_at'])) ?>
        </p>
    </div>
    <a href="/messages" class="btn btn-outline"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<!-- Participants -->
<div class="card mb-2">
    <div class="card-body" style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;padding:0.75rem 1rem;">
        <span style="font-size:0.82rem;color:var(--text-secondary);margin-right:0.3rem;">
            <i class="bi bi-people-fill"></i> Participants :
        </span>
        <?php foreach ($participants as $p): ?>
            <span class="badge <?= ($p['global_role'] ?? '') === 'moderator' ? 'badge-warning' : 'badge-secondary' ?>"
                  style="font-size:0.78rem;display:inline-flex;align-items:center;gap:0.25rem;">
                <?php if (($p['global_role'] ?? '') === 'moderator'): ?>
                    <i class="bi bi-shield-check"></i>
                <?php else: ?>
                    <i class="bi bi-person"></i>
                <?php endif; ?>
                <?= e($p['username']) ?>
            </span>
        <?php endforeach; ?>

        <?php if ($isModerator && !$isClosed): ?>
            <form method="POST" action="/messages/<?= (int) $conversation['id'] ?>/add-participant"
                  style="display:inline-flex;align-items:center;gap:0.3rem;margin-left:auto;">
                <?= csrf_field() ?>
                <input type="number" name="user_id" class="form-control form-control-sm"
                       placeholder="ID utilisateur" min="1" required style="width:120px;font-size:0.8rem;">
                <button type="submit" class="btn btn-outline btn-sm" title="Ajouter un participant"
                        style="padding:0.2rem 0.5rem;font-size:0.78rem;">
                    <i class="bi bi-person-plus"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Fil de messages -->
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="bi bi-chat-text"></i> Messages</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($messages)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--text-secondary);">Aucun message.</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0;">
            <?php foreach ($messages as $msg):
                $isSelf = (int) $msg['user_id'] === $currentUserId;
                $isMod  = ($msg['global_role'] ?? '') === 'moderator';
            ?>
            <div style="
                padding:1rem 1.25rem;
                background:<?= $isSelf ? 'var(--bg-secondary)' : 'transparent' ?>;
                border-bottom:1px solid var(--border-color);
            ">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                    <?php if ($isMod): ?>
                        <span style="
                            display:inline-flex;align-items:center;justify-content:center;
                            width:1.8rem;height:1.8rem;border-radius:50%;
                            background:var(--warning, #f59e0b);color:#fff;font-size:0.75rem;font-weight:700;
                        "><i class="bi bi-shield-fill-check"></i></span>
                        <span style="font-weight:600;font-size:0.88rem;color:var(--warning, #f59e0b);">
                            <?= e($msg['username']) ?>
                            <span style="font-weight:400;font-size:0.75rem;color:var(--text-secondary);"> — Modérateur</span>
                        </span>
                    <?php else: ?>
                        <span style="
                            display:inline-flex;align-items:center;justify-content:center;
                            width:1.8rem;height:1.8rem;border-radius:50%;
                            background:var(--gray-light);color:var(--text-primary);font-size:0.78rem;font-weight:700;
                        "><?= strtoupper(mb_substr(e($msg['username']), 0, 1)) ?></span>
                        <span style="font-weight:600;font-size:0.88rem;"><?= e($msg['username']) ?></span>
                    <?php endif; ?>
                    <span style="font-size:0.78rem;color:var(--text-secondary);margin-left:auto;">
                        <?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?>
                    </span>
                </div>
                <div style="font-size:0.9rem;line-height:1.6;white-space:pre-wrap;"><?= e($msg['body']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div id="messages-end"></div>
    </div>
</div>

<!-- Formulaire de réponse -->
<?php if (!$isClosed): ?>
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="bi bi-reply"></i> Répondre</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/messages/<?= (int) $conversation['id'] ?>/reply" class="reply-form">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom:0.75rem;">
                <textarea name="body" class="form-control" rows="4"
                          placeholder="Votre message…" required minlength="1"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Envoyer
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Actions modérateur -->
<?php if ($isModerator): ?>
<div style="display:flex;justify-content:flex-end;gap:0.5rem;">
    <?php if ($isClosed): ?>
        <form method="POST" action="/messages/<?= (int) $conversation['id'] ?>/reopen">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-unlock"></i> Rouvrir
            </button>
        </form>
    <?php else: ?>
        <form method="POST" action="/messages/<?= (int) $conversation['id'] ?>/close"
              onsubmit="return confirm('Clôturer cette conversation ?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--text-secondary);">
                <i class="bi bi-lock"></i> Clôturer
            </button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>
