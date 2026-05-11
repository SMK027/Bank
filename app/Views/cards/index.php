<?php
/** @var array $cards */
/** @var array $accountsById */
/** @var array $eligibleAccounts */
/** @var array $sharedCards */
/** @var array $sharedAccountsById */

$justCreated = $_SESSION['card_just_created'] ?? null;
unset($_SESSION['card_just_created']);
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <h1><i class="bi bi-credit-card-2-front"></i> Mes cartes bancaires</h1>
    <a href="/cards/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Enregistrer une carte
    </a>
</div>

<?php if ($justCreated): ?>
<div class="alert alert-success" role="alert" style="display:flex;align-items:flex-start;gap:0.75rem;margin-bottom:1rem;">
    <i class="bi bi-check-circle-fill" style="font-size:1.3rem;flex-shrink:0;"></i>
    <div style="flex:1">
        <strong>Carte enregistrée !</strong>
        Voici votre numéro complet (visible une seule fois) :
        <div style="margin-top:0.5rem;">
            <code style="font-size:1.2rem;letter-spacing:0.15em;background:#fff;padding:0.45rem 0.8rem;border-radius:6px;border:1px solid var(--gray-light);">
                <?= e(chunk_split($justCreated['number'], 4, ' ')) ?>
            </code>
        </div>
        <p class="text-muted text-small" style="margin-top:0.4rem;margin-bottom:0;">
            Conservez ce numéro pour le communiquer aux plateformes tierces. Vous pourrez le reconsulter plus tard depuis cette page en saisissant votre mot de passe.
        </p>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($cards)): ?>
            <p class="text-muted text-center" style="padding:1.5rem 0;margin:0;">
                <i class="bi bi-credit-card" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                Vous n'avez encore aucune carte bancaire enregistrée.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Carte</th>
                            <th>Libellé</th>
                            <th>Compte associé</th>
                            <th>Expiration</th>
                            <th>Plafond mensuel</th>
                            <th>Statut</th>
                            <th>Créée le</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cards as $card):
                        $acc        = $accountsById[(int) $card['account_id']] ?? null;
                        $isExpired  = \App\Models\PaymentCard::isExpired($card);
                        $expirySoon = !$isExpired && !empty($card['expires_at'])
                            && strtotime($card['expires_at']) < strtotime('+30 days');
                    ?>
                        <tr class="<?= $isExpired ? 'text-muted' : '' ?>">
                            <td>
                                <code style="letter-spacing:0.1em;">
                                    <?= e(\App\Models\PaymentCard::mask($card['card_number'])) ?>
                                </code>
                            </td>
                            <td><?= e($card['label'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if ($acc): ?>
                                    <a href="/accounts/<?= (int) $acc['id'] ?>"><?= e($acc['name']) ?></a>
                                    <span class="text-muted text-small"> (<?= e($acc['currency'] ?? '') ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">Compte introuvable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($card['expires_at'])): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="badge badge-danger" title="Expirée le <?= e(date('d/m/Y', strtotime($card['expires_at']))) ?>">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <?= date('m/Y', strtotime($card['expires_at'])) ?>
                                    </span>
                                <?php elseif ($expirySoon): ?>
                                    <span class="badge bg-warning text-dark" title="Expire bientôt">
                                        <i class="bi bi-clock"></i>
                                        <?= date('m/Y', strtotime($card['expires_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span><?= date('m/Y', strtotime($card['expires_at'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($card['monthly_limit']) && $card['monthly_limit'] !== null): ?>
                                    <span><?= number_format((float) $card['monthly_limit'], 2, ',', ' ') ?> <?= e($acc['currency'] ?? '€') ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Illimité</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isExpired): ?>
                                    <span class="badge badge-danger">Expirée</span>
                                <?php elseif (($card['status'] ?? '') === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Bloquée</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($card['created_at'])) ?></td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a href="/cards/<?= (int) $card['id'] ?>/reveal" class="btn btn-sm btn-secondary" title="Afficher le numéro complet">
                                    <i class="bi bi-eye"></i> Afficher
                                </a>
                                <!-- Activer / Bloquer la carte -->
                                <?php if (!$isExpired): ?>
                                <form method="POST" action="/cards/<?= (int) $card['id'] ?>/toggle"
                                      style="display:inline;vertical-align:middle;"
                                      onsubmit="return confirm('<?= ($card['status'] ?? '') === 'active' ? 'Bloquer cette carte ?' : 'Activer cette carte ?' ?>');">
                                    <?= csrf_field() ?>
                                    <?php if (($card['status'] ?? '') === 'active'): ?>
                                        <button type="submit" class="btn btn-sm btn-warning" title="Bloquer la carte">
                                            <i class="bi bi-lock"></i> Bloquer
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-success" title="Activer la carte">
                                            <i class="bi bi-unlock"></i> Activer
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                                <!-- Modifier les paramètres (expiration + plafond) -->
                                <details style="display:inline-block;text-align:left;vertical-align:middle;">
                                    <summary class="btn btn-sm btn-outline" style="cursor:pointer;" title="Modifier expiration et plafond">
                                        <i class="bi bi-sliders"></i> Paramètres
                                    </summary>
                                    <form method="POST" action="/cards/<?= (int) $card['id'] ?>/settings"
                                          style="min-width:260px;padding:0.75rem;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:6px;margin-top:0.4rem;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                                        <?= csrf_field() ?>
                                        <div style="margin-bottom:0.6rem;">
                                            <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;">
                                                <i class="bi bi-calendar-x"></i> Date d'expiration
                                            </label>
                                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                                <input type="text" name="expires_at"
                                                       class="form-control form-control-sm"
                                                       style="width:90px;"
                                                       maxlength="7" placeholder="MM/AA"
                                                       value="<?= !empty($card['expires_at']) ? date('m/y', strtotime($card['expires_at'])) : '' ?>">
                                                <label style="font-size:0.78rem;display:flex;align-items:center;gap:0.25rem;white-space:nowrap;cursor:pointer;">
                                                    <input type="checkbox" name="clear_expiry" value="1">
                                                    Supprimer
                                                </label>
                                            </div>
                                        </div>
                                        <div style="margin-bottom:0.7rem;">
                                            <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;">
                                                <i class="bi bi-bar-chart"></i> Plafond mensuel
                                            </label>
                                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                                <input type="text" name="monthly_limit"
                                                       class="form-control form-control-sm"
                                                       style="width:90px;"
                                                       maxlength="12" placeholder="Ex : 500"
                                                       inputmode="decimal"
                                                       value="<?= isset($card['monthly_limit']) && $card['monthly_limit'] !== null ? number_format((float) $card['monthly_limit'], 2, ',', '') : '' ?>">
                                                <label style="font-size:0.78rem;display:flex;align-items:center;gap:0.25rem;white-space:nowrap;cursor:pointer;">
                                                    <input type="checkbox" name="clear_limit" value="1">
                                                    Supprimer
                                                </label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary" style="width:100%;">
                                            <i class="bi bi-check-lg"></i> Enregistrer
                                        </button>
                                    </form>
                                </details>
                                <!-- Changer de compte associé -->
                                <details style="display:inline-block;text-align:left;vertical-align:middle;">
                                    <summary class="btn btn-sm btn-secondary" style="cursor:pointer;">
                                        <i class="bi bi-pencil"></i> Compte
                                    </summary>
                                    <form method="POST" action="/cards/<?= (int) $card['id'] ?>/account"
                                          style="min-width:220px;padding:0.6rem;background:var(--card-bg,#fff);border:1px solid var(--border-color);border-radius:6px;margin-top:0.4rem;box-shadow:0 4px 12px rgba(0,0,0,0.1);display:flex;flex-direction:column;gap:0.4rem;">
                                        <?= csrf_field() ?>
                                        <select name="account_id" class="form-control form-control-sm" required>
                                            <?php foreach ($eligibleAccounts as $a): ?>
                                                <option value="<?= (int) $a['id'] ?>"
                                                    <?= (int) $a['id'] === (int) $card['account_id'] ? 'selected' : '' ?>>
                                                    <?= e($a['name']) ?> (<?= e($a['currency']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-check-lg"></i> Valider
                                        </button>
                                    </form>
                                </details>
                                <form method="POST" action="/cards/<?= (int) $card['id'] ?>/delete"
                                      style="display:inline;vertical-align:middle;"
                                      onsubmit="return confirm('Supprimer définitivement cette carte ?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer la carte">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($sharedCards)): ?>
<div class="card" style="margin-top:1.5rem;">
    <div class="card-body">
        <h2 style="font-size:1.1rem;margin-bottom:1rem;">
            <i class="bi bi-people"></i> Cartes des comptes partagés
        </h2>
        <p class="text-muted text-small" style="margin-bottom:1rem;">
            Ces cartes sont liées à des comptes auxquels vous avez accès par procuration ou en tant que responsable légal. Leur consultation et gestion restent réservées au titulaire.
        </p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Carte</th>
                        <th>Libellé</th>
                        <th>Compte associé</th>
                        <th>Expiration</th>
                        <th>Plafond mensuel</th>
                        <th>Statut</th>
                        <th>Créée le</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sharedCards as $card):
                    $acc        = $sharedAccountsById[(int) $card['account_id']] ?? null;
                    $isExpired  = \App\Models\PaymentCard::isExpired($card);
                    $expirySoon = !$isExpired && !empty($card['expires_at'])
                        && strtotime($card['expires_at']) < strtotime('+30 days');
                ?>
                    <tr class="<?= $isExpired ? 'text-muted' : '' ?>">
                        <td>
                            <code style="letter-spacing:0.1em;">
                                <?= e(\App\Models\PaymentCard::mask($card['card_number'])) ?>
                            </code>
                        </td>
                        <td><?= e($card['label'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?php if ($acc): ?>
                                <a href="/accounts/<?= (int) $acc['id'] ?>"><?= e($acc['name']) ?></a>
                                <span class="text-muted text-small"> (<?= e($acc['currency'] ?? '') ?>)</span>
                            <?php else: ?>
                                <span class="text-muted">Compte introuvable</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($card['expires_at'])): ?>
                                <span class="text-muted">—</span>
                            <?php elseif ($isExpired): ?>
                                <span class="badge badge-danger" title="Expirée le <?= e(date('d/m/Y', strtotime($card['expires_at']))) ?>">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <?= date('m/Y', strtotime($card['expires_at'])) ?>
                                </span>
                            <?php elseif ($expirySoon): ?>
                                <span class="badge bg-warning text-dark" title="Expire bientôt">
                                    <i class="bi bi-clock"></i>
                                    <?= date('m/Y', strtotime($card['expires_at'])) ?>
                                </span>
                            <?php else: ?>
                                <span><?= date('m/Y', strtotime($card['expires_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($card['monthly_limit']) && $card['monthly_limit'] !== null): ?>
                                <span><?= number_format((float) $card['monthly_limit'], 2, ',', ' ') ?> <?= e($acc['currency'] ?? '€') ?></span>
                            <?php else: ?>
                                <span class="text-muted">Illimité</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isExpired): ?>
                                <span class="badge badge-danger">Expirée</span>
                            <?php elseif (($card['status'] ?? '') === 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Bloquée</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($card['created_at'])) ?></td>
                        <td style="text-align:right;white-space:nowrap;">
                            <?php if (!$isExpired): ?>
                            <form method="POST" action="/cards/<?= (int) $card['id'] ?>/toggle"
                                  style="display:inline;vertical-align:middle;"
                                  onsubmit="return confirm('<?= ($card['status'] ?? '') === 'active' ? 'Bloquer cette carte ?' : 'Activer cette carte ?' ?>');">
                                <?= csrf_field() ?>
                                <?php if (($card['status'] ?? '') === 'active'): ?>
                                    <button type="submit" class="btn btn-sm btn-warning" title="Bloquer la carte">
                                        <i class="bi bi-lock"></i> Bloquer
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success" title="Activer la carte">
                                        <i class="bi bi-unlock"></i> Activer
                                    </button>
                                <?php endif; ?>
                            </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
