<?php
/** @var array $payments */
/** @var array $cardsById */
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-shop"></i> Paiements TPE</h1>
    <span class="text-muted text-small"><?= count($payments) ?> opération(s) récente(s)</span>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <p class="text-muted text-center" style="padding:1.5rem 0;margin:0;">
                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                Aucun paiement TPE enregistré.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Carte</th>
                            <th>Compte client</th>
                            <th>Compte commerçant</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $p):
                        $cancelled = !empty($p['cancelled_at']);
                        $failed    = ($p['status'] ?? '') === 'failed';
                        $deferred  = !empty($p['deferred_debit_id']);
                        $cardLast4 = $cardsById[(int) ($p['card_id'] ?? 0)]['last4'] ?? '----';
                    ?>
                        <tr>
                            <td><?= (int) $p['id'] ?></td>
                            <td>
                                <span class="text-small"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></span>
                            </td>
                            <td><code>**** <?= e($cardLast4) ?></code></td>
                            <td>
                                <?php if (!empty($p['account_id'])): ?>
                                    <a href="/accounts/<?= (int) $p['account_id'] ?>">#<?= (int) $p['account_id'] ?></a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['credit_transaction_id'])): ?>
                                    <span class="text-muted text-small">TX-<?= (int) $p['credit_transaction_id'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= number_format((float) $p['amount'], 2, ',', ' ') ?></strong>
                                <span class="text-muted text-small"><?= e($p['currency']) ?></span>
                            </td>
                            <td>
                                <?php if ($cancelled): ?>
                                    <span class="badge bg-secondary">Annulé</span>
                                <?php elseif ($failed): ?>
                                    <span class="badge badge-danger">Échec</span>
                                <?php elseif ($deferred): ?>
                                    <span class="badge bg-warning text-dark">Différé</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Succès</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <?php if (!$cancelled && !$failed): ?>
                                    <details style="display:inline-block;text-align:left;">
                                        <summary class="btn btn-sm btn-danger" style="cursor:pointer;">
                                            <i class="bi bi-x-circle"></i> Annuler
                                        </summary>
                                        <form method="POST" action="/moderation/pos-payments/<?= (int) $p['id'] ?>/cancel"
                                              style="display:flex;gap:0.4rem;align-items:center;margin-top:0.5rem;"
                                              onsubmit="return confirm('Annuler définitivement ce paiement TPE ?');">
                                            <?= csrf_field() ?>
                                            <input type="text" name="reason" class="form-control form-control-sm"
                                                   placeholder="Motif (facultatif)" maxlength="255">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    </details>
                                <?php elseif ($cancelled): ?>
                                    <span class="text-muted text-small" title="<?= e((string) ($p['cancel_reason'] ?? '')) ?>">
                                        <?= date('d/m/Y H:i', strtotime($p['cancelled_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted text-small"><?= e((string) ($p['reason'] ?? '')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
