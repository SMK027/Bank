<?php
/** @var array  $checkbooks */
/** @var array  $checkCounts */
/** @var array  $eligibleAccounts */
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-journal-check"></i> Mes chéquiers</h1>
    <?php if (!empty($eligibleAccounts)): ?>
    <a href="/checkbooks/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nouveau chéquier
    </a>
    <?php endif; ?>
</div>

<?php if (empty($checkbooks)): ?>
<div class="card">
    <div class="card-body">
        <p class="text-muted text-center" style="padding:2rem 0;margin:0;">
            <i class="bi bi-journal-x" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;"></i>
            Vous n'avez encore aucun chéquier enregistré.
            <?php if (!empty($eligibleAccounts)): ?>
            <br><a href="/checkbooks/create" class="btn btn-primary" style="margin-top:1rem;">
                <i class="bi bi-plus-lg"></i> Créer un chéquier
            </a>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Compte associé</th>
                        <th>Statut</th>
                        <th>Chèques émis</th>
                        <th>Chèques encaissés</th>
                        <th>Créé le</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checkbooks as $cb):
                    $cbId    = (int) $cb['id'];
                    $counts  = $checkCounts[$cbId] ?? ['total' => 0, 'emitted' => 0, 'cashed' => 0, 'opposed' => 0];
                    $opposed = $cb['status'] === 'opposed';
                    $acc     = $cb['account'] ?? null;
                ?>
                <tr class="<?= $opposed ? 'text-muted' : '' ?>">
                    <td>
                        <strong><?= e($cb['label']) ?></strong>
                    </td>
                    <td>
                        <?php if ($acc): ?>
                            <a href="/accounts/<?= (int) $acc['id'] ?>"><?= e($acc['name']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">Compte #<?= (int) $cb['account_id'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($opposed): ?>
                            <span class="badge" style="background:var(--danger);color:#fff;">
                                <i class="bi bi-slash-circle"></i> En opposition
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--success,#22c55e);color:#fff;">
                                <i class="bi bi-check-circle"></i> Actif
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($counts['emitted'] > 0): ?>
                            <span style="font-weight:600;color:var(--warning,#f59e0b);"><?= $counts['emitted'] ?></span>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $counts['cashed'] ?></td>
                    <td class="text-muted text-small"><?= date('d/m/Y', strtotime($cb['created_at'])) ?></td>
                    <td style="text-align:right;">
                        <div class="btn-group btn-group-sm">
                            <a href="/checkbooks/<?= $cbId ?>" class="btn btn-outline btn-sm">
                                <i class="bi bi-eye"></i> Détail
                            </a>
                            <?php if (!$opposed): ?>
                            <form method="POST" action="/checkbooks/<?= $cbId ?>/oppose"
                                  onsubmit="return confirm('Mettre en opposition le chéquier « <?= e(addslashes($cb['label'])) ?> » et tous ses chèques non encaissés ?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">
                                    <i class="bi bi-slash-circle"></i> Opposition
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
