<?php
/** @var array  $checkbook */
/** @var array  $account */
/** @var array  $checks */
/** @var bool   $isOwner */
/** @var bool   $isGuardian */
/** @var bool   $isModerator */
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <div>
        <h1>
            <i class="bi bi-journal-check"></i> <?= e($checkbook['label']) ?>
            <?php if ($checkbook['status'] === 'opposed'): ?>
                <span class="badge" style="background:var(--danger);color:#fff;font-size:0.55em;vertical-align:middle;">
                    <i class="bi bi-slash-circle"></i> En opposition
                </span>
            <?php else: ?>
                <span class="badge" style="background:var(--success,#22c55e);color:#fff;font-size:0.55em;vertical-align:middle;">
                    <i class="bi bi-check-circle"></i> Actif
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-description">
            Compte associé :
            <?php if ($account): ?>
                <a href="/accounts/<?= (int) $account['id'] ?>"><?= e($account['name']) ?></a>
                — <?= e($account['currency']) ?>
            <?php else: ?>
                <span class="text-muted">Compte #<?= (int) $checkbook['account_id'] ?></span>
            <?php endif; ?>
            &nbsp;·&nbsp; Créé le <?= date('d/m/Y', strtotime($checkbook['created_at'])) ?>
        </p>
    </div>
    <div class="btn-group">
        <a href="/checkbooks" class="btn btn-outline btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
        <?php if ($checkbook['status'] !== 'opposed' && ($isOwner || $isGuardian || $isModerator)): ?>
        <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"
                onclick="document.getElementById('oppose-checkbook-form').style.display = document.getElementById('oppose-checkbook-form').style.display === 'none' ? 'block' : 'none';">
            <i class="bi bi-slash-circle"></i> Mettre en opposition
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($checkbook['status'] !== 'opposed' && ($isOwner || $isGuardian || $isModerator)): ?>
<div id="oppose-checkbook-form" style="display:none;margin-bottom:1.5rem;">
    <div class="alert alert-danger" style="display:flex;align-items:flex-start;gap:0.75rem;">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.3rem;flex-shrink:0;margin-top:0.1rem;"></i>
        <div>
            <strong>Mettre ce chéquier en opposition</strong><br>
            <span class="text-small">Tous les chèques non encore encaissés seront annulés et leur débit supprimé.
            Cette action est irréversible.</span>
            <div style="margin-top:0.75rem;">
                <form method="POST" action="/checkbooks/<?= (int) $checkbook['id'] ?>/oppose">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Confirmer la mise en opposition du chéquier et l\'annulation de tous les chèques en attente ?')">
                        <i class="bi bi-slash-circle"></i> Confirmer l'opposition
                    </button>
                    <button type="button" class="btn btn-outline btn-sm"
                            onclick="document.getElementById('oppose-checkbook-form').style.display='none'">
                        Annuler
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php
            $emitted = array_filter($checks, fn($c) => $c['status'] === 'emitted');
            $cashed  = array_filter($checks, fn($c) => $c['status'] === 'cashed');
            $opposed = array_filter($checks, fn($c) => $c['status'] === 'opposed');
        ?>
        <div style="display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1.5rem;">
            <div style="text-align:center;">
                <div style="font-size:1.8rem;font-weight:700;color:var(--warning,#f59e0b);"><?= count($emitted) ?></div>
                <div class="text-muted text-small">En attente</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.8rem;font-weight:700;color:var(--success,#22c55e);"><?= count($cashed) ?></div>
                <div class="text-muted text-small">Encaissés</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:1.8rem;font-weight:700;color:var(--danger);"><?= count($opposed) ?></div>
                <div class="text-muted text-small">Opposés</div>
            </div>
        </div>

        <?php if (empty($checks)): ?>
        <p class="text-muted text-center" style="padding:2rem 0;margin:0;">
            <i class="bi bi-journal-x" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
            Aucun chèque émis depuis ce chéquier.
        </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Bénéficiaire</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Date d'émission</th>
                        <?php if ($checkbook['status'] !== 'opposed' && ($isOwner || $isGuardian || $isModerator)): ?>
                        <th style="text-align:right;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $chk):
                    $isEmitted = $chk['status'] === 'emitted';
                    $isCashed  = $chk['status'] === 'cashed';
                    $isOpp     = $chk['status'] === 'opposed';
                ?>
                <tr class="<?= ($isCashed || $isOpp) ? 'text-muted' : '' ?>">
                    <td><strong>#<?= (int) $chk['check_number'] ?></strong></td>
                    <td><?= e($chk['payee'] ?: '—') ?></td>
                    <td>
                        <span style="font-weight:600;<?= $isEmitted ? 'color:var(--warning,#f59e0b);' : '' ?>">
                            <?= number_format((float) $chk['amount'], 2, ',', ' ') ?>&nbsp;<?= $account ? e($account['currency']) : '€' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($isEmitted): ?>
                            <span class="badge" style="background:var(--warning,#f59e0b);color:#fff;">
                                <i class="bi bi-clock"></i> En attente
                            </span>
                        <?php elseif ($isCashed): ?>
                            <span class="badge" style="background:var(--success,#22c55e);color:#fff;">
                                <i class="bi bi-check2-circle"></i> Encaissé
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--danger);color:#fff;">
                                <i class="bi bi-slash-circle"></i> Opposé
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($chk['created_at'])) ?></td>
                    <?php if ($checkbook['status'] !== 'opposed' && ($isOwner || $isGuardian || $isModerator)): ?>
                    <td style="text-align:right;">
                        <?php if ($isEmitted): ?>
                        <div class="btn-group btn-group-sm">
                            <form method="POST" action="/checkbooks/<?= (int) $checkbook['id'] ?>/checks/<?= (int) $chk['id'] ?>/confirm">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm"
                                        style="color:var(--success,#22c55e);border-color:var(--success,#22c55e);"
                                        onclick="return confirm('Confirmer que ce chèque a été encaissé ? Le débit sera effectué immédiatement.')">
                                    <i class="bi bi-check2-circle"></i> Encaissé
                                </button>
                            </form>
                            <form method="POST" action="/checkbooks/<?= (int) $checkbook['id'] ?>/checks/<?= (int) $chk['id'] ?>/oppose">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline btn-sm"
                                        style="color:var(--danger);border-color:var(--danger);"
                                        onclick="return confirm('Mettre ce chèque en opposition ? Le débit en attente sera annulé.')">
                                    <i class="bi bi-slash-circle"></i> Opposition
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
