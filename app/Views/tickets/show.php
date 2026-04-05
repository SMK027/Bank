<?php
/**
 * @var array       $ticket    Ticket courant (avec username du propriétaire)
 * @var array       $messages  Liste des messages
 * @var array|null  $account   Compte associé (ou null)
 * @var array       $statuses  Ticket::STATUSES
 */
$isClosed = \App\Models\Ticket::isClosed($ticket['status']);
$statusColor = \App\Models\Ticket::statusColor($ticket['status']);
$typeLabel   = \App\Models\Ticket::typeLabel($ticket['type']);
$statusLabel = \App\Models\Ticket::statusLabel($ticket['status']);
?>
<div class="page-header">
    <div>
        <h1><i class="bi bi-ticket-perforated"></i> Demande #<?= (int) $ticket['id'] ?></h1>
        <p class="page-description"><?= e($ticket['subject']) ?></p>
    </div>
    <a href="/tickets" class="btn btn-outline"><i class="bi bi-arrow-left"></i> Mes demandes</a>
</div>

<!-- Fiche ticket -->
<div class="card mb-2">
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem 1.5rem;">
        <div>
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);margin-bottom:0.2rem;">Statut</div>
            <span class="badge badge-<?= e($statusColor) ?>" style="font-size:0.85rem;"><?= e($statusLabel) ?></span>
        </div>
        <div>
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);margin-bottom:0.2rem;">Type</div>
            <span style="font-size:0.9rem;"><?= e($typeLabel) ?></span>
        </div>
        <div>
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);margin-bottom:0.2rem;">Créé le</div>
            <span style="font-size:0.9rem;"><?= date('d/m/Y à H:i', strtotime($ticket['created_at'])) ?></span>
        </div>
        <?php if ($account): ?>
        <div>
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary);margin-bottom:0.2rem;">Compte associé</div>
            <a href="/accounts/<?= (int) $account['id'] ?>" style="font-size:0.9rem;color:var(--primary);">
                <?= e($account['name']) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

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
            <?php foreach ($messages as $i => $msg): ?>
            <?php $isStaff = (bool) $msg['is_staff']; ?>
            <div style="
                padding:1rem 1.25rem;
                background:<?= $isStaff ? 'var(--bg-secondary)' : 'transparent' ?>;
                border-bottom:1px solid var(--border-color);
            ">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                    <?php if ($isStaff): ?>
                        <span style="
                            display:inline-flex;align-items:center;justify-content:center;
                            width:1.8rem;height:1.8rem;border-radius:50%;
                            background:var(--primary);color:#fff;font-size:0.75rem;font-weight:700;
                        "><i class="bi bi-shield-fill-check"></i></span>
                        <span style="font-weight:600;font-size:0.88rem;color:var(--primary);">Équipe de modération</span>
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

<!-- Formulaire de réponse -->
<?php if (!$isClosed): ?>
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="bi bi-reply"></i> Répondre</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/tickets/<?= (int) $ticket['id'] ?>/reply">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom:0.75rem;">
                <textarea name="body" class="form-control" rows="4"
                          placeholder="Votre message…" required minlength="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Envoyer
            </button>
        </form>
    </div>
</div>

<!-- Fermer le ticket -->
<div style="text-align:right;">
    <form method="POST" action="/tickets/<?= (int) $ticket['id'] ?>/close"
          onsubmit="return confirm('Clôturer ce ticket ? L\'équipe de modération ne pourra plus répondre.');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline" style="font-size:0.82rem;color:var(--text-secondary);">
            <i class="bi bi-x-circle"></i> Clôturer ce ticket
        </button>
    </form>
</div>

<?php else: ?>
<div class="alert alert-info" style="display:flex;align-items:center;gap:0.6rem;">
    <i class="bi bi-lock-fill"></i>
    <span>Ce ticket est <strong>clôturé</strong> — aucune réponse supplémentaire n'est possible.</span>
</div>
<?php endif; ?>
