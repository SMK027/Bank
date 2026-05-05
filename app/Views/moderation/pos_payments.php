<?php
/** @var array $payments */
/** @var array $cardsById */
/** @var array $filters */
/** @var bool  $hasFilters */
/** @var array $posStatus */
/** @var array $suspendedAccounts */
$filters            = $filters            ?? [];
$hasFilters         = $hasFilters         ?? false;
$posStatus          = $posStatus          ?? ['is_disabled' => false, 'reason' => '', 'disabled_at' => null, 'disabled_until' => null];
$suspendedAccounts  = $suspendedAccounts  ?? [];
$f = static fn(string $k): string => htmlspecialchars((string) ($filters[$k] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-shop"></i> Paiements TPE</h1>
    <span class="text-muted text-small">
        <?= count($payments) ?> opération(s)<?= $hasFilters ? ' (filtrées)' : ' récente(s)' ?>
    </span>
</div>

<!-- Panneau d'activation/désactivation du TPE -->
<div class="card mb-3 pos-status-card">
    <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem;">
        <?php if ($posStatus['is_disabled']): ?>
            <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
                <span class="badge badge-danger" style="font-size:0.85rem;">
                    <i class="bi bi-power"></i> TPE désactivé
                </span>
                <?php if (!empty($posStatus['disabled_at'])): ?>
                    <span class="text-muted text-small">
                        depuis le <?= e(date('d/m/Y H:i', strtotime($posStatus['disabled_at']))) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($posStatus['disabled_until'])): ?>
                    <span class="text-muted text-small">
                        — réactivation auto le <?= e(date('d/m/Y H:i', strtotime($posStatus['disabled_until']))) ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted text-small">— durée indéterminée</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($posStatus['reason'])): ?>
                <div class="text-small">
                    <strong>Motif :</strong> <?= e($posStatus['reason']) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="/moderation/pos-payments/enable" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('Réactiver le TPE pour toute la plateforme ?');">
                    <i class="bi bi-power"></i> Réactiver le TPE
                </button>
            </form>
        <?php else: ?>
            <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
                <span class="badge badge-success" style="font-size:0.85rem;">
                    <i class="bi bi-check-circle"></i> TPE actif
                </span>
                <span class="text-muted text-small">Tous les commerçants peuvent encaisser des paiements par carte.</span>
            </div>
            <details>
                <summary class="btn btn-outline-danger btn-sm" style="cursor:pointer;display:inline-block;">
                    <i class="bi bi-power"></i> Désactiver le TPE
                </summary>
                <form method="POST" action="/moderation/pos-payments/disable"
                      style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.6rem;align-items:end;margin-top:0.75rem;"
                      onsubmit="return confirm('Désactiver le TPE pour toute la plateforme ?');">
                    <?= csrf_field() ?>
                    <div style="grid-column:1 / -1;">
                        <label class="form-label text-small" for="pos-disable-reason">Motif <span class="text-danger">*</span></label>
                        <textarea id="pos-disable-reason" name="reason" rows="2" maxlength="500"
                                  class="form-control form-control-sm" required
                                  placeholder="Décrivez la raison de la désactivation (incident, maintenance, fraude…)"></textarea>
                    </div>
                    <div>
                        <label class="form-label text-small" for="pos-disable-until">Réactivation automatique (facultatif)</label>
                        <input type="datetime-local" id="pos-disable-until" name="disabled_until"
                               class="form-control form-control-sm">
                        <small class="text-muted">Laissez vide pour une désactivation à durée indéterminée.</small>
                    </div>
                    <div style="display:flex;align-items:end;">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-power"></i> Confirmer la désactivation
                        </button>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </div>
</div>

<!-- Panneau : comptes professionnels suspendus du TPE + formulaire de suspension -->
<div class="card mb-3">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
        <h4 style="margin:0;font-size:0.95rem;">
            <i class="bi bi-shop-window"></i> Suspension d'un commerçant
        </h4>
        <?php if (!empty($suspendedAccounts)): ?>
            <span class="badge badge-danger"><?= count($suspendedAccounts) ?> compte(s) suspendu(s)</span>
        <?php else: ?>
            <span class="badge badge-success">Aucune suspension active</span>
        <?php endif; ?>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:1rem;">

        <?php if (!empty($suspendedAccounts)): ?>
        <!-- Liste des comptes actuellement suspendus -->
        <div class="table-responsive">
            <table class="table" style="font-size:0.88rem;">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Titulaire</th>
                        <th>Suspendu depuis</th>
                        <th>Jusqu'au</th>
                        <th>Motif</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($suspendedAccounts as $sa): ?>
                    <tr>
                        <td>
                            <a href="/accounts/<?= (int) $sa['id'] ?>">#<?= (int) $sa['id'] ?></a>
                            <span class="text-muted text-small"> — <?= e($sa['name']) ?></span>
                        </td>
                        <td>
                            <?= e($sa['username']) ?>
                            <br><small class="text-muted"><?= e($sa['email']) ?></small>
                        </td>
                        <td class="text-small"><?= e(date('d/m/Y H:i', strtotime($sa['pos_suspended_at']))) ?></td>
                        <td class="text-small">
                            <?php if (!empty($sa['pos_suspended_until'])): ?>
                                <?= e(date('d/m/Y H:i', strtotime($sa['pos_suspended_until']))) ?>
                            <?php else: ?>
                                <span class="text-muted">Indéterminée</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-small"><?= e($sa['pos_suspend_reason'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td style="text-align:right;">
                            <form method="POST"
                                  action="/moderation/pos-payments/merchant/<?= (int) $sa['id'] ?>/resume"
                                  style="display:inline;"
                                  onsubmit="return confirm('Réactiver l\'accès TPE du compte #<?= (int) $sa['id'] ?> ?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-play-fill"></i> Réactiver
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Formulaire de nouvelle suspension -->
        <details <?= empty($suspendedAccounts) ? 'open' : '' ?>>
            <summary class="btn btn-outline-danger btn-sm" style="cursor:pointer;display:inline-block;">
                <i class="bi bi-slash-circle"></i> Suspendre un compte commerçant
            </summary>
            <form method="POST" action=""
                  id="pos-merchant-suspend-form"
                  style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.6rem;align-items:end;margin-top:0.75rem;"
                  onsubmit="
                    var id = document.getElementById('pos-suspend-account-id').value;
                    if (!id) { alert('Saisissez un identifiant de compte.'); return false; }
                    this.action = '/moderation/pos-payments/merchant/' + id + '/suspend';
                    return confirm('Suspendre ce compte du TPE ?');
                  ">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label text-small" for="pos-suspend-account-id">
                        ID du compte professionnel <span class="text-danger">*</span>
                    </label>
                    <input type="number" id="pos-suspend-account-id" min="1"
                           class="form-control form-control-sm" placeholder="ex. 42" required>
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="form-label text-small" for="pos-suspend-reason">
                        Motif <span class="text-danger">*</span>
                    </label>
                    <textarea id="pos-suspend-reason" name="reason" rows="2" maxlength="500"
                              class="form-control form-control-sm" required
                              placeholder="Raison de la suspension (fraude, contrôle, plainte…)"></textarea>
                </div>
                <div>
                    <label class="form-label text-small" for="pos-suspend-until">
                        Réactivation automatique (facultatif)
                    </label>
                    <input type="datetime-local" id="pos-suspend-until" name="suspended_until"
                           class="form-control form-control-sm">
                    <small class="text-muted">Vide = durée indéterminée.</small>
                </div>
                <div style="display:flex;align-items:end;">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-slash-circle"></i> Confirmer la suspension
                    </button>
                </div>
            </form>
        </details>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/moderation/pos-payments" class="pos-search-form"
              style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.6rem 0.8rem;align-items:end;">
            <div>
                <label class="form-label text-small" for="pos-f-client">Client</label>
                <input type="text" id="pos-f-client" name="client" value="<?= $f('client') ?>"
                       class="form-control form-control-sm"
                       placeholder="Nom, compte ou #id" autocomplete="off">
            </div>
            <div>
                <label class="form-label text-small" for="pos-f-merchant">Commerçant</label>
                <input type="text" id="pos-f-merchant" name="merchant" value="<?= $f('merchant') ?>"
                       class="form-control form-control-sm"
                       placeholder="Nom, compte ou #id" autocomplete="off">
            </div>
            <div>
                <label class="form-label text-small" for="pos-f-amount-min">Montant min</label>
                <input type="number" id="pos-f-amount-min" name="amount_min" value="<?= $f('amount_min') ?>"
                       step="0.01" min="0" class="form-control form-control-sm" placeholder="0,00">
            </div>
            <div>
                <label class="form-label text-small" for="pos-f-amount-max">Montant max</label>
                <input type="number" id="pos-f-amount-max" name="amount_max" value="<?= $f('amount_max') ?>"
                       step="0.01" min="0" class="form-control form-control-sm" placeholder="0,00">
            </div>
            <div>
                <label class="form-label text-small" for="pos-f-date-from">Du</label>
                <input type="date" id="pos-f-date-from" name="date_from" value="<?= $f('date_from') ?>"
                       class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label text-small" for="pos-f-date-to">Au</label>
                <input type="date" id="pos-f-date-to" name="date_to" value="<?= $f('date_to') ?>"
                       class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label text-small" for="pos-f-status">Statut</label>
                <select id="pos-f-status" name="status" class="form-control form-control-sm">
                    <?php $st = $filters['status'] ?? ''; ?>
                    <option value=""           <?= $st === ''           ? 'selected' : '' ?>>Tous</option>
                    <option value="success"    <?= $st === 'success'    ? 'selected' : '' ?>>Succès</option>
                    <option value="deferred"   <?= $st === 'deferred'   ? 'selected' : '' ?>>Différé</option>
                    <option value="failed"     <?= $st === 'failed'     ? 'selected' : '' ?>>Échec</option>
                    <option value="cancelled"  <?= $st === 'cancelled'  ? 'selected' : '' ?>>Annulé</option>
                </select>
            </div>
            <div style="display:flex;gap:0.4rem;align-items:end;">
                <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap;">
                    <i class="bi bi-search"></i> Rechercher
                </button>
                <?php if ($hasFilters): ?>
                <a href="/moderation/pos-payments" class="btn btn-outline btn-sm" style="white-space:nowrap;"
                   title="Réinitialiser les filtres">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <p class="text-muted text-center" style="padding:1.5rem 0;margin:0;">
                <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                <?= $hasFilters
                    ? 'Aucun paiement TPE ne correspond aux critères de recherche.'
                    : 'Aucun paiement TPE enregistré.' ?>
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table moderation-pos-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Carte</th>
                            <th>Client</th>
                            <th>Commerçant</th>
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
                                    <?php if (!empty($p['client_name'])): ?>
                                        <strong class="text-small"><?= e($p['client_name']) ?></strong><br>
                                    <?php endif; ?>
                                    <a href="/accounts/<?= (int) $p['account_id'] ?>" class="text-small">
                                        <?= !empty($p['client_account_name']) ? e($p['client_account_name']) : '#' . (int) $p['account_id'] ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['merchant_account_id'])): ?>
                                    <?php if (!empty($p['merchant_name'])): ?>
                                        <strong class="text-small"><?= e($p['merchant_name']) ?></strong><br>
                                    <?php endif; ?>
                                    <a href="/accounts/<?= (int) $p['merchant_account_id'] ?>" class="text-small">
                                        <?= !empty($p['merchant_account_name']) ? e($p['merchant_account_name']) : '#' . (int) $p['merchant_account_id'] ?>
                                    </a>
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
