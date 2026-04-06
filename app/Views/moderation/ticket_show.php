<?php
/**
 * @var array       $ticket    Ticket (avec username + email)
 * @var array       $messages  Messages triés par date
 * @var array|null  $account   Compte associé
 * @var array       $statuses  Ticket::STATUSES
 */
$isClosed    = \App\Models\Ticket::isClosed($ticket['status']);
$statusColor = \App\Models\Ticket::statusColor($ticket['status']);
$typeLabel   = \App\Models\Ticket::typeLabel($ticket['type']);
$statusLabel = \App\Models\Ticket::statusLabel($ticket['status']);
?>
<div class="page-header">
    <div>
        <h1>
            <span class="badge badge-mod" style="font-size:0.7rem;vertical-align:middle;margin-right:0.3rem;">MOD</span>
            Ticket #<?= (int) $ticket['id'] ?>
        </h1>
        <p class="page-description"><?= e($ticket['subject']) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="/moderation/tickets" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Tous les tickets</a>
        <a href="/moderation" class="btn btn-outline btn-sm"><i class="bi bi-shield-check"></i> Comptes</a>
        <a href="/moderation/transfers" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left-right"></i> Virements</a>
        <a href="/moderation/direct-debits" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-arrow-down"></i> Prélèvements</a>
        <a href="/moderation/mandates" class="btn btn-outline btn-sm"><i class="bi bi-file-earmark-text"></i> Mandats</a>
        <a href="/moderation/guardianships" class="btn btn-outline btn-sm"><i class="bi bi-person-lock"></i> Tutelles légales</a>
        <a href="/moderation/users" class="btn btn-outline btn-sm"><i class="bi bi-people"></i> Utilisateurs</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start;">

    <!-- Colonne principale -->
    <div>
        <!-- Fil de discussion -->
        <div class="card mb-2">
            <div class="card-header" id="messages">
                <h3><i class="bi bi-chat-text"></i> Échanges</h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($messages)): ?>
                    <div style="padding:1.5rem;text-align:center;color:var(--text-secondary);">Aucun message.</div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:0;">
                    <?php foreach ($messages as $msg): ?>
                    <?php $isStaff = (bool) $msg['is_staff']; ?>
                    <div style="
                        padding:1rem 1.25rem;
                        background:<?= $isStaff ? 'rgba(var(--primary-rgb,59,130,246),0.06)' : 'transparent' ?>;
                        border-bottom:1px solid var(--border-color);
                    ">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                            <?php if ($isStaff): ?>
                                <span style="
                                    display:inline-flex;align-items:center;justify-content:center;
                                    width:1.8rem;height:1.8rem;border-radius:50%;
                                    background:var(--primary);color:#fff;font-size:0.75rem;
                                "><i class="bi bi-shield-fill-check"></i></span>
                                <span style="font-weight:600;font-size:0.88rem;color:var(--primary);">
                                    <?= e($msg['username']) ?> <span style="font-weight:400;opacity:.6;">(Modérateur)</span>
                                </span>
                            <?php else: ?>
                                <span style="
                                    display:inline-flex;align-items:center;justify-content:center;
                                    width:1.8rem;height:1.8rem;border-radius:50%;
                                    background:var(--gray-light);color:var(--text-primary);font-size:0.78rem;font-weight:700;
                                "><?= strtoupper(substr(e($msg['username']), 0, 1)) ?></span>
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
            </div>
        </div>

        <!-- Répondre -->
        <?php if (!$isClosed): ?>
        <div class="card mb-2">
            <div class="card-header">
                <h3><i class="bi bi-reply-fill"></i> Répondre à l'utilisateur</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="/moderation/tickets/<?= (int) $ticket['id'] ?>/reply">
                    <?= csrf_field() ?>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <textarea name="body" class="form-control" rows="4"
                                  placeholder="Votre réponse…" required minlength="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Envoyer
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info" style="display:flex;align-items:center;gap:0.6rem;">
            <i class="bi bi-lock-fill"></i>
            <span>Ce ticket est <strong>clôturé</strong>.</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Colonne latérale -->
    <div style="position:sticky;top:1rem;">

        <!-- Fiche ticket -->
        <div class="card mb-2">
            <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="bi bi-info-circle"></i> Informations</h4></div>
            <div class="card-body" style="font-size:0.85rem;display:flex;flex-direction:column;gap:0.75rem;">
                <div>
                    <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);">Statut</div>
                    <span class="badge badge-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
                </div>
                <div>
                    <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);">Type</div>
                    <?= e($typeLabel) ?>
                </div>
                <div>
                    <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);">Utilisateur</div>
                    <?= e($ticket['username']) ?>
                    <div style="font-size:0.78rem;color:var(--text-secondary);"><?= e($ticket['email']) ?></div>
                </div>
                <?php if ($account): ?>
                <div>
                    <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);">Compte associé</div>
                    <a href="/accounts/<?= (int) $account['id'] ?>" style="color:var(--primary);"><?= e($account['name']) ?></a>
                    <div style="font-size:0.78rem;color:var(--text-secondary);"><?= e($account['currency']) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);">Créé le</div>
                    <?= date('d/m/Y à H:i', strtotime($ticket['created_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Changer le statut -->
        <div class="card mb-2">
            <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="bi bi-arrow-repeat"></i> Changer le statut</h4></div>
            <div class="card-body">
                <form method="POST" action="/moderation/tickets/<?= (int) $ticket['id'] ?>/status">
                    <?= csrf_field() ?>
                    <div class="form-group" style="margin-bottom:0.6rem;">
                        <select name="status" class="form-control" style="font-size:0.85rem;">
                            <?php foreach ($statuses as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $ticket['status'] === $val ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm btn-block">
                        <i class="bi bi-check2"></i> Appliquer
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /sidebar -->
</div>

<style>
@media (max-width: 700px) {
    div[style*="grid-template-columns:1fr 280px"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="position:sticky"] {
        position: static !important;
    }
}
</style>
