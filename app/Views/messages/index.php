<?php
/** @var array $conversations  Liste des conversations */
/** @var bool  $isModerator */
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-envelope"></i> Messagerie</h1>
        <p class="page-description">
            <?php if ($isModerator): ?>
                Échangez avec les autres modérateurs et les utilisateurs.
            <?php else: ?>
                Échangez avec l'équipe de modération.
            <?php endif; ?>
        </p>
    </div>
    <a href="/messages/create" class="btn btn-primary">
        <i class="bi bi-pencil-square"></i> Nouveau message
    </a>
</div>

<?php if (empty($conversations)): ?>
<div class="card" style="text-align:center;padding:2.5rem 1rem;">
    <div style="font-size:3rem;color:var(--gray-light);margin-bottom:1rem;"><i class="bi bi-inbox"></i></div>
    <p style="color:var(--text-secondary);margin:0 0 1.25rem;">Aucune conversation pour le moment.</p>
    <a href="/messages/create" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Écrire un message</a>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:2.5rem;"></th>
                    <th>Sujet</th>
                    <th>Type</th>
                    <th>Dernier message</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($conversations as $c):
                $hasUnread = (int) ($c['unread_count'] ?? 0) > 0;
                $isClosed  = !empty($c['is_closed']);
            ?>
                <tr style="<?= $hasUnread ? 'font-weight:600;' : '' ?>">
                    <td style="text-align:center;vertical-align:middle;">
                        <?php if ($hasUnread): ?>
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--primary);" title="Non lu"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/messages/<?= (int) $c['id'] ?>" style="text-decoration:none;color:var(--text-primary);">
                            <?php if ($isClosed): ?>
                                <i class="bi bi-lock-fill" style="color:var(--text-secondary);font-size:0.8rem;"></i>
                            <?php endif; ?>
                            <?= e($c['subject']) ?>
                            <?php if ($hasUnread): ?>
                                <span class="badge badge-primary" style="font-size:0.7rem;margin-left:0.3rem;"><?= (int) $c['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($c['type'] === 'mod_only'): ?>
                            <span class="badge badge-warning" style="font-size:0.75rem;">
                                <i class="bi bi-shield-check"></i> Modérateurs
                            </span>
                        <?php else: ?>
                            <span class="badge badge-info" style="font-size:0.75rem;">
                                <i class="bi bi-people"></i> Modération / Utilisateur
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;color:var(--text-secondary);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php if (!empty($c['last_message_at'])): ?>
                            <span title="<?= e(date('d/m/Y H:i', strtotime($c['last_message_at']))) ?>">
                                <?= date('d/m/Y H:i', strtotime($c['last_message_at'])) ?>
                            </span>
                            — <?= e(mb_substr($c['last_message'] ?? '', 0, 60)) ?><?= mb_strlen($c['last_message'] ?? '') > 60 ? '…' : '' ?>
                        <?php else: ?>
                            <em>Aucun message</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/messages/<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
