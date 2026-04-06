<div class="page-header">
    <div>
        <h1><i class="bi bi-people"></i> Modération — Utilisateurs</h1>
        <p class="page-description">Gestion des rôles, suspensions et bannissements</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-ticket-perforated"></i> Tickets</a>
        <span class="btn btn-outline btn-sm disabled" aria-current="page"><i class="bi bi-people"></i> Utilisateurs</span>
        <a href="/moderation/audit-log" class="btn btn-outline btn-sm"><i class="bi bi-journal-text"></i> Journal d'audit</a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="table" style="margin:0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Modifier le rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $user):
                        $isSelf    = (int) $user['id'] === $currentUserId;
                        $isMod     = ($user['global_role'] ?? 'user') === 'moderator';
                        $status    = $user['status'] ?? 'active';
                        $untilRaw  = $user['suspended_until'] ?? null;
                        $untilFmt  = $untilRaw ? (new DateTime($untilRaw))->format('d/m/Y') : null;
                        $rowStyle  = match($status) {
                            'suspended' => 'background:rgba(255,193,7,0.08)',
                            'banned'    => 'background:rgba(220,53,69,0.07)',
                            default     => '',
                        };
                    ?>
                        <tr style="<?= $rowStyle ?>">
                            <td class="text-muted text-small">#<?= (int) $user['id'] ?></td>
                            <td>
                                <span class="font-bold"><?= e($user['username']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="badge badge-primary" style="margin-left:0.35rem">Vous</span>
                                <?php endif; ?>
                                <br><span class="text-muted text-small"><?= e($user['email']) ?></span>
                            </td>
                            <td>
                                <?php if ($isMod): ?>
                                    <span class="badge badge-mod"><i class="bi bi-shield-check"></i> Modérateur</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><i class="bi bi-person"></i> Utilisateur</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($status === 'suspended'): ?>
                                    <span class="badge" style="background:#ffc107;color:#000;font-size:0.75rem">
                                        <i class="bi bi-pause-circle"></i> Suspendu
                                    </span>
                                    <?php if ($untilFmt): ?>
                                        <br><span class="text-muted text-small">jusqu'au <?= e($untilFmt) ?></span>
                                    <?php else: ?>
                                        <br><span class="text-muted text-small">indéfiniment</span>
                                    <?php endif; ?>
                                <?php elseif ($status === 'banned'): ?>
                                    <span class="badge badge-danger" style="font-size:0.75rem">
                                        <i class="bi bi-slash-circle"></i> Banni
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success" style="font-size:0.75rem">
                                        <i class="bi bi-check-circle"></i> Actif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSelf): ?>
                                    <span class="text-muted text-small">—</span>
                                <?php else: ?>
                                    <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/role"
                                          style="display:flex;gap:0.5rem;align-items:center;">
                                        <?= csrf_field() ?>
                                        <select name="role" class="form-control" style="width:auto;padding:0.3rem 0.6rem;font-size:0.82rem;">
                                            <option value="user"      <?= ($user['global_role'] ?? 'user') === 'user'      ? 'selected' : '' ?>>Utilisateur</option>
                                            <option value="moderator" <?= ($user['global_role'] ?? 'user') === 'moderator' ? 'selected' : '' ?>>Modérateur</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm" title="Enregistrer le rôle">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSelf || $isMod): ?>
                                    <span class="text-muted text-small">—</span>
                                <?php elseif ($status === 'active'): ?>
                                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center">
                                        <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/suspend"
                                              style="display:flex;gap:0.3rem;align-items:center">
                                            <?= csrf_field() ?>
                                            <input type="date" name="suspended_until"
                                                   class="form-control"
                                                   style="width:auto;padding:0.2rem 0.4rem;font-size:0.75rem"
                                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                                   title="Date de fin (vide = indéfini)">
                                            <button type="submit" class="btn btn-warning btn-sm" title="Suspendre le compte">
                                                <i class="bi bi-pause-circle"></i> Suspendre
                                            </button>
                                        </form>
                                        <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/ban"
                                              onsubmit="return confirm('Bannir définitivement « <?= e($user['username']) ?> » ?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm" title="Bannir le compte">
                                                <i class="bi bi-slash-circle"></i> Bannir
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($status === 'suspended'): ?>
                                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                                        <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/activate">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success btn-sm" title="Réactiver le compte">
                                                <i class="bi bi-play-circle"></i> Réactiver
                                            </button>
                                        </form>
                                        <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/ban"
                                              onsubmit="return confirm('Bannir définitivement « <?= e($user['username']) ?> » ?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm" title="Bannir le compte">
                                                <i class="bi bi-slash-circle"></i> Bannir
                                            </button>
                                        </form>
                                    </div>
                                <?php else: /* banned */ ?>
                                    <form method="POST" action="/moderation/users/<?= (int) $user['id'] ?>/activate">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm" title="Réactiver le compte">
                                            <i class="bi bi-play-circle"></i> Réactiver
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
