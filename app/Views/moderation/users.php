<div class="page-header">
    <div>
        <h1><i class="bi bi-people"></i> Modération — Utilisateurs</h1>
        <p class="page-description">Gestion des rôles globaux</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="table" style="margin:0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Rôle actuel</th>
                        <th>Modifier le rôle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $user): ?>
                        <tr>
                            <td class="text-muted text-small">#<?= (int) $user['id'] ?></td>
                            <td class="font-bold">
                                <?= e($user['username']) ?>
                                <?php if ((int) $user['id'] === $currentUserId): ?>
                                    <span class="badge badge-primary" style="margin-left:0.35rem">Vous</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-small"><?= e($user['email']) ?></td>
                            <td>
                                <?php if ($user['global_role'] === 'moderator'): ?>
                                    <span class="badge badge-mod"><i class="bi bi-shield-check"></i> Modérateur</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><i class="bi bi-person"></i> Utilisateur</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $user['id'] === $currentUserId): ?>
                                    <span class="text-muted text-small">—</span>
                                <?php else: ?>
                                    <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/role"
                                          style="display:flex;gap:0.5rem;align-items:center;">
                                        <?= csrf_field() ?>
                                        <select name="role" class="form-control" style="width:auto;padding:0.3rem 0.6rem;font-size:0.82rem;">
                                            <option value="user"      <?= ($user['global_role'] ?? 'user') === 'user'      ? 'selected' : '' ?>>Utilisateur</option>
                                            <option value="moderator" <?= ($user['global_role'] ?? 'user') === 'moderator' ? 'selected' : '' ?>>Modérateur</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
