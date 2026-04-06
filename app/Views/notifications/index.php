<?php
/** @var array  $notifications   Liste des notifications */
/** @var int    $unreadCount     Nombre de non lues */
?>

<div class="notifications-page">

    <div class="page-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; margin-bottom:1.5rem;">
        <h1 class="page-title" style="margin:0;">
            <i class="bi bi-bell-fill" style="color:var(--primary);"></i>
            Notifications
            <?php if ($unreadCount > 0): ?>
                <span class="badge badge-primary" style="font-size:.75rem; vertical-align:middle;"><?= $unreadCount ?> non lue<?= $unreadCount > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </h1>

        <?php if (!empty($notifications)): ?>
        <div class="notifications-actions" style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <?php if ($unreadCount > 0): ?>
            <form method="POST" action="/notifications/read-all">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline">
                    <i class="bi bi-check2-all"></i> Tout marquer comme lu
                </button>
            </form>
            <?php endif; ?>
            <form method="POST" action="/notifications/delete-read"
                  onsubmit="return confirm('Supprimer toutes les notifications lues ?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline btn-outline-danger">
                    <i class="bi bi-trash"></i> Supprimer les lues
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state" style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <i class="bi bi-bell-slash" style="font-size:3rem; opacity:.4;"></i>
            <p style="margin-top:1rem;">Vous n'avez aucune notification.</p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $notif): ?>
            <?php
                $isRead   = (int) $notif['is_read'] === 1;
                $iconClass = \App\Models\Notification::iconClass($notif['type']);
            ?>
            <div class="notif-item<?= $isRead ? '' : ' notif-item--unread' ?>" id="notif-<?= (int) $notif['id'] ?>">
                <div class="notif-icon">
                    <i class="bi <?= e($iconClass) ?>"></i>
                </div>

                <div class="notif-content">
                    <div class="notif-header">
                        <span class="notif-title"><?= e($notif['title']) ?></span>
                        <span class="notif-time" title="<?= e(format_date($notif['created_at'])) ?>">
                            <?= e(time_ago($notif['created_at'])) ?>
                        </span>
                    </div>
                    <?php if (!empty($notif['body'])): ?>
                        <p class="notif-body"><?= e($notif['body']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="notif-controls">
                    <?php if (!$isRead): ?>
                    <form method="POST" action="/notifications/<?= (int) $notif['id'] ?>/read" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="notif-btn notif-btn-read" title="Marquer comme lu">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if (!empty($notif['link'])): ?>
                    <a href="<?= e($notif['link']) ?>" class="notif-btn notif-btn-link" title="Voir">
                        <i class="bi bi-arrow-right-circle"></i>
                    </a>
                    <?php endif; ?>

                    <form method="POST" action="/notifications/<?= (int) $notif['id'] ?>/delete"
                          style="display:inline"
                          onsubmit="return confirm('Supprimer cette notification ?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="notif-btn notif-btn-delete" title="Supprimer">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
