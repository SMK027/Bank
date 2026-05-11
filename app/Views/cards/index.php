<?php
/** @var array $cards */
/** @var array $accountsById */
/** @var array $eligibleAccounts */
/** @var array $sharedCards */
/** @var array $sharedAccountsById */
/** @var array $monthlySpentById */
/** @var bool $isModerator */

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
                        $cardSpent     = $monthlySpentById[(int) $card['id']] ?? null;
                        $cardLimitRaw  = isset($card['monthly_limit']) && $card['monthly_limit'] !== null ? (float) $card['monthly_limit'] : null;
                        $cardCurrency  = $acc['currency'] ?? '€';
                        $cardData = [
                            'id'              => (int) $card['id'],
                            'masked'          => \App\Models\PaymentCard::mask($card['card_number']),
                            'label'           => $card['label'] ?? '',
                            'status'          => $card['status'] ?? 'active',
                            'expired'         => $isExpired,
                            'expiresAt'       => !empty($card['expires_at']) ? date('m/y', strtotime($card['expires_at'])) : '',
                            'monthlyLimit'    => $cardLimitRaw !== null ? number_format($cardLimitRaw, 2, ',', '') : '',
                            'monthlyLimitRaw' => $cardLimitRaw,
                            'monthlySpent'    => $cardSpent,
                            'monthlyOverride' => ($cardLimitRaw !== null
                                && isset($card['monthly_spent_override'])
                                && $card['monthly_spent_override'] !== null
                                && ($card['monthly_spent_override_month'] ?? '') === date('Y-m'))
                                ? (float) $card['monthly_spent_override']
                                : null,
                            'currency'        => $cardCurrency,
                            'accountId'       => (int) $card['account_id'],
                            'shared'          => false,
                        ];
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
                                <?php if ($cardLimitRaw !== null): ?>
                                    <?php
                                        $pct      = $cardLimitRaw > 0 ? min(100, (int) round(($cardSpent / $cardLimitRaw) * 100)) : 0;
                                        $barColor = $pct >= 100 ? 'var(--danger,#dc3545)' : ($pct >= 80 ? '#ffc107' : 'var(--success-color,#28a745)');
                                    ?>
                                    <div style="min-width:140px;">
                                        <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.2rem;gap:0.4rem;">
                                            <span><?= number_format($cardSpent, 2, ',', ' ') ?></span>
                                            <span class="text-muted"><?= number_format($cardLimitRaw, 2, ',', ' ') ?> <?= e($cardCurrency) ?></span>
                                        </div>
                                        <div style="height:5px;background:var(--gray-light,#e9ecef);border-radius:3px;overflow:hidden;" title="<?= $pct ?>% utilisé">
                                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:3px;"></div>
                                        </div>
                                    </div>
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
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-sm btn-outline js-open-card-modal"
                                        data-card="<?= htmlspecialchars(json_encode($cardData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>">
                                    <i class="bi bi-gear"></i> Gérer
                                </button>
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
            Ces cartes sont liées à des comptes auxquels vous avez accès par procuration ou en tant que responsable légal. Seule la désactivation est disponible depuis cette vue.
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
                    $sCardSpent    = $monthlySpentById[(int) $card['id']] ?? null;
                    $sCardLimitRaw = isset($card['monthly_limit']) && $card['monthly_limit'] !== null ? (float) $card['monthly_limit'] : null;
                    $sCardCurrency = $acc['currency'] ?? '€';
                    $cardData = [
                        'id'              => (int) $card['id'],
                        'masked'          => \App\Models\PaymentCard::mask($card['card_number']),
                        'label'           => $card['label'] ?? '',
                        'status'          => $card['status'] ?? 'active',
                        'expired'         => $isExpired,
                        'monthlyLimitRaw' => $sCardLimitRaw,
                        'monthlySpent'    => $sCardSpent,
                        'monthlyOverride' => ($sCardLimitRaw !== null
                            && isset($card['monthly_spent_override'])
                            && $card['monthly_spent_override'] !== null
                            && ($card['monthly_spent_override_month'] ?? '') === date('Y-m'))
                            ? (float) $card['monthly_spent_override']
                            : null,
                        'currency'        => $sCardCurrency,
                        'shared'          => true,
                    ];
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
                            <?php if ($sCardLimitRaw !== null): ?>
                                <?php
                                    $sPct      = $sCardLimitRaw > 0 ? min(100, (int) round(($sCardSpent / $sCardLimitRaw) * 100)) : 0;
                                    $sBarColor = $sPct >= 100 ? 'var(--danger,#dc3545)' : ($sPct >= 80 ? '#ffc107' : 'var(--success-color,#28a745)');
                                ?>
                                <div style="min-width:140px;">
                                    <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.2rem;gap:0.4rem;">
                                        <span><?= number_format($sCardSpent, 2, ',', ' ') ?></span>
                                        <span class="text-muted"><?= number_format($sCardLimitRaw, 2, ',', ' ') ?> <?= e($sCardCurrency) ?></span>
                                    </div>
                                    <div style="height:5px;background:var(--gray-light,#e9ecef);border-radius:3px;overflow:hidden;" title="<?= $sPct ?>% utilisé">
                                        <div style="height:100%;width:<?= $sPct ?>%;background:<?= $sBarColor ?>;border-radius:3px;"></div>
                                    </div>
                                </div>
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
                        <td style="text-align:right;">
                            <button type="button" class="btn btn-sm btn-outline js-open-card-modal"
                                    data-card="<?= htmlspecialchars(json_encode($cardData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>">
                                <i class="bi bi-gear"></i> Gérer
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================
     Modale de gestion d'une carte
     ======================================================== -->
<div class="modal-overlay" id="cardModalOverlay" role="dialog" aria-modal="true" aria-labelledby="cardModalTitle" style="display:none;align-items:center;justify-content:center;">
    <div class="modal" style="max-width:520px;width:95%;padding:1.5rem;">

        <!-- En-tête -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem;">
            <div>
                <h3 id="cardModalTitle" style="margin:0 0 0.2rem;font-size:1rem;"></h3>
                <span class="text-muted text-small" id="cardModalSub" style="font-size:0.82rem;"></span>
            </div>
            <button type="button" id="cardModalClose" class="btn btn-sm btn-outline" style="margin-left:1rem;padding:0.2rem 0.6rem;font-size:1rem;line-height:1;" aria-label="Fermer">&times;</button>
        </div>

        <!-- Afficher le numéro (cartes propres uniquement) -->
        <div class="cm-own" style="margin-bottom:0.6rem;">
            <a id="cmRevealLink" href="#" class="btn btn-secondary" style="width:100%;justify-content:flex-start;gap:0.5rem;">
                <i class="bi bi-eye"></i> Afficher le numéro complet
            </a>
        </div>

        <!-- Utilisation du plafond mensuel -->
        <div id="cmLimitBlock" style="margin-bottom:0.75rem;padding:0.75rem;background:var(--bg,#f8f9fa);border-radius:var(--border-radius,6px);border:1px solid var(--border-color,#dee2e6);">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.35rem;">
                <span style="font-size:0.82rem;font-weight:600;"><i class="bi bi-bar-chart"></i> Plafond mensuel</span>
                <span id="cmLimitText" style="font-size:0.82rem;"></span>
            </div>
            <div style="height:8px;background:var(--gray-light,#e9ecef);border-radius:4px;overflow:hidden;">
                <div id="cmLimitBar" style="height:100%;width:0%;border-radius:4px;transition:width 0.4s;"></div>
            </div>
            <div id="cmLimitPct" style="font-size:0.75rem;color:var(--gray,#6c757d);margin-top:0.3rem;text-align:right;"></div>
        </div>

        <!-- Activer / Bloquer (si non expirée) -->
        <div class="cm-not-expired" style="margin-bottom:0.6rem;">
            <form method="POST" id="cmToggleForm">
                <input type="hidden" name="csrf_token" class="cm-csrf">
                <button type="submit" id="cmToggleBtn" class="btn" style="width:100%;justify-content:flex-start;gap:0.5rem;"></button>
            </form>
        </div>

        <!-- Paramètres expiration + plafond (cartes propres uniquement) -->
        <div class="cm-own" style="margin-bottom:0.6rem;">
            <details id="cmSettingsDetails">
                <summary class="btn btn-outline" style="width:100%;cursor:pointer;justify-content:flex-start;gap:0.5rem;list-style:none;display:flex;align-items:center;">
                    <i class="bi bi-sliders"></i> Modifier les paramètres
                    <i class="bi bi-chevron-down" style="margin-left:auto;font-size:0.75rem;"></i>
                </summary>
                <form method="POST" id="cmSettingsForm" style="margin-top:0.6rem;padding:0.75rem;background:var(--bg,#f8f9fa);border-radius:var(--border-radius,6px);border:1px solid var(--border-color,#dee2e6);">
                    <input type="hidden" name="csrf_token" class="cm-csrf">
                    <div style="margin-bottom:0.6rem;">
                        <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;">
                            <i class="bi bi-calendar-x"></i> Date d'expiration
                        </label>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <input type="text" name="expires_at" id="cmExpiresAt" class="form-control form-control-sm"
                                   style="width:90px;" maxlength="7" placeholder="MM/AA">
                            <label style="font-size:0.78rem;display:flex;align-items:center;gap:0.25rem;white-space:nowrap;cursor:pointer;">
                                <input type="checkbox" name="clear_expiry" value="1"> Supprimer
                            </label>
                        </div>
                    </div>
                    <div style="margin-bottom:0.75rem;">
                        <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;">
                            <i class="bi bi-bar-chart"></i> Plafond mensuel
                        </label>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <input type="text" name="monthly_limit" id="cmMonthlyLimit" class="form-control form-control-sm"
                                   style="width:90px;" maxlength="12" placeholder="Ex : 500" inputmode="decimal">
                            <label style="font-size:0.78rem;display:flex;align-items:center;gap:0.25rem;white-space:nowrap;cursor:pointer;">
                                <input type="checkbox" name="clear_limit" value="1"> Supprimer
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">
                        <i class="bi bi-check-lg"></i> Enregistrer les paramètres
                    </button>
                </form>
            </details>
        </div>

        <!-- Changer de compte associé (cartes propres uniquement) -->
        <div class="cm-own" style="margin-bottom:0.6rem;">
            <details id="cmAccountDetails">
                <summary class="btn btn-outline" style="width:100%;cursor:pointer;justify-content:flex-start;gap:0.5rem;list-style:none;display:flex;align-items:center;">
                    <i class="bi bi-arrow-left-right"></i> Changer de compte associé
                    <i class="bi bi-chevron-down" style="margin-left:auto;font-size:0.75rem;"></i>
                </summary>
                <form method="POST" id="cmAccountForm" style="margin-top:0.6rem;padding:0.75rem;background:var(--bg,#f8f9fa);border-radius:var(--border-radius,6px);border:1px solid var(--border-color,#dee2e6);display:flex;flex-direction:column;gap:0.5rem;">
                    <input type="hidden" name="csrf_token" class="cm-csrf">
                    <select name="account_id" id="cmAccountSelect" class="form-control" required></select>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg"></i> Valider
                    </button>
                </form>
            </details>
        </div>

        <!-- Zone danger : supprimer (cartes propres uniquement) -->
        <div class="cm-own">
            <hr style="margin:0.75rem 0;border-color:var(--border-color,#dee2e6);">
            <form method="POST" id="cmDeleteForm">
                <input type="hidden" name="csrf_token" class="cm-csrf">
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:flex-start;gap:0.5rem;">
                    <i class="bi bi-trash"></i> Supprimer cette carte
                </button>
            </form>
        </div>

        <!-- Note cartes partagées -->
        <div class="cm-shared">
            <p class="text-muted text-small" style="margin:0.5rem 0 0;font-size:0.82rem;">
                <i class="bi bi-info-circle"></i>
                La consultation du numéro, la modification des paramètres et la suppression restent réservées au titulaire de la carte.
            </p>
        </div>

        <?php if ($isModerator): ?>
        <!-- Correction modérateur : plafond dépensé ce mois -->
        <div class="cm-moderator-override" style="margin-top:0.6rem;">
            <hr style="margin:0.75rem 0;border-color:var(--border-color,#dee2e6);">
            <details id="cmOverrideDetails">
                <summary class="btn btn-outline btn-sm" style="width:100%;cursor:pointer;justify-content:flex-start;gap:0.5rem;list-style:none;display:flex;align-items:center;border-color:#f59e0b;color:#b45309;">
                    <i class="bi bi-pencil-square" style="color:#f59e0b;"></i>
                    <span style="flex:1;text-align:left;">Corriger le montant dépensé (modération)</span>
                    <i class="bi bi-chevron-down" style="margin-left:auto;font-size:0.75rem;"></i>
                </summary>
                <div style="margin-top:0.6rem;padding:0.75rem;background:var(--bg,#f8f9fa);border-radius:var(--border-radius,6px);border:1px solid #f59e0b;">
                    <p style="font-size:0.78rem;color:var(--text-muted);margin:0 0 0.6rem;">
                        <i class="bi bi-info-circle"></i>
                        Remplace le calcul automatique pour le mois en cours. La correction est ignorée automatiquement au mois suivant.
                    </p>
                    <div id="cmOverrideCurrentInfo" style="font-size:0.78rem;margin-bottom:0.6rem;display:none;"></div>
                    <form method="POST" id="cmOverrideForm">
                        <input type="hidden" name="csrf_token" class="cm-csrf">
                        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                            <input type="text" name="override_value" id="cmOverrideValue"
                                   class="form-control form-control-sm" style="width:110px;"
                                   inputmode="decimal" placeholder="Ex : 245,50">
                            <span style="font-size:0.82rem;">€</span>
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="bi bi-check-lg"></i> Appliquer
                            </button>
                        </div>
                    </form>
                    <form method="POST" id="cmOverrideClearForm" style="margin-top:0.5rem;display:none;">
                        <input type="hidden" name="csrf_token" class="cm-csrf">
                        <input type="hidden" name="clear_override" value="1">
                        <button type="submit" class="btn btn-outline btn-sm" style="font-size:0.75rem;color:var(--danger);">
                            <i class="bi bi-x-circle"></i> Supprimer la correction
                        </button>
                    </form>
                </div>
            </details>
        </div>
        <?php endif; ?>

        <!-- Message carte expirée -->
        <div class="cm-expired">
            <p class="text-muted text-small" style="margin:0.5rem 0 0;font-size:0.82rem;">
                <i class="bi bi-exclamation-triangle"></i>
                Cette carte est expirée. Aucune action n'est disponible.
            </p>
        </div>

    </div>
</div>

<script>
(function () {
    const overlay  = document.getElementById('cardModalOverlay');
    const csrfToken = <?= json_encode(\App\Core\CSRF::generate()) ?>;

    const eligibleAccounts = <?= json_encode(array_map(fn($a) => [
        'id'       => (int) $a['id'],
        'label'    => ($a['name'] ?? '') . ' (' . ($a['currency'] ?? '') . ')',
    ], $eligibleAccounts)) ?>;

    function openCardModal(card) {
        const isOwn     = !card.shared;
        const isExpired = !!card.expired;
        const isActive  = card.status === 'active';
        const id        = card.id;

        // Titre
        document.getElementById('cardModalTitle').textContent = card.masked;
        document.getElementById('cardModalSub').textContent   = card.label || '';

        // CSRF dans tous les formulaires
        overlay.querySelectorAll('.cm-csrf').forEach(function (el) {
            el.value = csrfToken;
        });

        // Visibilité des sections
        overlay.querySelectorAll('.cm-own').forEach(function (el) {
            el.style.display = isOwn ? '' : 'none';
        });
        overlay.querySelectorAll('.cm-shared').forEach(function (el) {
            el.style.display = isOwn ? 'none' : '';
        });
        overlay.querySelectorAll('.cm-not-expired').forEach(function (el) {
            el.style.display = isExpired ? 'none' : '';
        });
        overlay.querySelectorAll('.cm-expired').forEach(function (el) {
            el.style.display = isExpired && !isOwn ? '' : 'none';
        });

        // Bloc plafond
        const limitBlock = document.getElementById('cmLimitBlock');
        const limitBar   = document.getElementById('cmLimitBar');
        const limitText  = document.getElementById('cmLimitText');
        const limitPct   = document.getElementById('cmLimitPct');
        if (card.monthlyLimitRaw !== null && card.monthlyLimitRaw !== undefined) {
            const spent = card.monthlySpent || 0;
            const limit = card.monthlyLimitRaw;
            const pct   = limit > 0 ? Math.min(100, Math.round(spent / limit * 100)) : 0;
            const color = pct >= 100 ? 'var(--danger,#dc3545)' : (pct >= 80 ? '#ffc107' : 'var(--success-color,#28a745)');
            const cur   = card.currency || '€';
            const fmt   = function (n) { return n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
            let limitTextContent = fmt(spent) + ' / ' + fmt(limit) + ' ' + cur;
            if (card.monthlyOverride !== null && card.monthlyOverride !== undefined) {
                limitTextContent += ' \u26a0\ufe0f correction';
            }
            limitText.textContent  = limitTextContent;
            limitBar.style.width   = pct + '%';
            limitBar.style.background = color;
            limitPct.textContent   = pct + '% utilisé ce mois';
            limitBlock.style.display = '';
        } else {
            limitBlock.style.display = 'none';
        }

        // Afficher le numéro
        if (isOwn) {
            document.getElementById('cmRevealLink').href = '/cards/' + id + '/reveal';
        }

        // Toggle statut
        if (!isExpired) {
            const toggleForm = document.getElementById('cmToggleForm');
            const toggleBtn  = document.getElementById('cmToggleBtn');
            toggleForm.action = '/cards/' + id + '/toggle';
            if (isActive) {
                toggleBtn.className = 'btn btn-warning';
                toggleBtn.style.cssText = 'width:100%;justify-content:flex-start;gap:0.5rem;';
                toggleBtn.innerHTML = '<i class="bi bi-lock"></i> Bloquer cette carte';
                toggleForm.onsubmit = function () { return confirm('Bloquer cette carte ?'); };
            } else {
                toggleBtn.className = 'btn btn-success';
                toggleBtn.style.cssText = 'width:100%;justify-content:flex-start;gap:0.5rem;';
                toggleBtn.innerHTML = '<i class="bi bi-unlock"></i> Activer cette carte';
                toggleForm.onsubmit = function () { return confirm('Activer cette carte ?'); };
            }
        }

        // Paramètres
        if (isOwn) {
            document.getElementById('cmSettingsForm').action = '/cards/' + id + '/settings';
            document.getElementById('cmExpiresAt').value     = card.expiresAt || '';
            document.getElementById('cmMonthlyLimit').value  = card.monthlyLimit || '';
            // Réinitialiser les cases à cocher
            document.getElementById('cmSettingsDetails').open = false;
            document.getElementById('cmSettingsForm').querySelectorAll('input[type=checkbox]').forEach(function (cb) {
                cb.checked = false;
            });

            // Compte associé
            document.getElementById('cmAccountForm').action = '/cards/' + id + '/account';
            document.getElementById('cmAccountDetails').open = false;
            const select = document.getElementById('cmAccountSelect');
            select.innerHTML = '';
            eligibleAccounts.forEach(function (a) {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.label;
                if (a.id === card.accountId) opt.selected = true;
                select.appendChild(opt);
            });

            // Supprimer
            const deleteForm = document.getElementById('cmDeleteForm');
            deleteForm.action = '/cards/' + id + '/delete';
            deleteForm.onsubmit = function () { return confirm('Supprimer définitivement cette carte ?'); };
        }

        // Bloc correction modérateur
        const modOverrideBlock = document.querySelector('.cm-moderator-override');
        if (modOverrideBlock) {
            if (card.monthlyLimitRaw !== null && card.monthlyLimitRaw !== undefined) {
                modOverrideBlock.style.display = '';
                const fmt = function (n) { return n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

                // Formulaire d'application
                document.getElementById('cmOverrideForm').action  = '/cards/' + id + '/override-spent';
                document.getElementById('cmOverrideClearForm').action = '/cards/' + id + '/override-spent';

                // Fermer le details au changement de carte
                document.getElementById('cmOverrideDetails').open = false;

                // Pré-remplir si override actif ce mois
                const input = document.getElementById('cmOverrideValue');
                const infoDiv = document.getElementById('cmOverrideCurrentInfo');
                const clearForm = document.getElementById('cmOverrideClearForm');
                if (card.monthlyOverride !== null && card.monthlyOverride !== undefined) {
                    input.value = String(card.monthlyOverride).replace('.', ',');
                    infoDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;"></i> '
                        + 'Correction active\u00a0: <strong>' + fmt(card.monthlyOverride) + '\u00a0\u20ac</strong>';
                    infoDiv.style.display = '';
                    clearForm.style.display = '';
                } else {
                    input.value = '';
                    infoDiv.style.display = 'none';
                    clearForm.style.display = 'none';
                }
            } else {
                modOverrideBlock.style.display = 'none';
            }
        }

        // Ouvrir la modale
        overlay.style.display = 'flex';
        requestAnimationFrame(function () { overlay.classList.add('active'); });
    }

    function closeCardModal() {
        overlay.classList.remove('active');
        setTimeout(function () { overlay.style.display = 'none'; }, 200);
    }

    // Bouton fermer
    document.getElementById('cardModalClose').addEventListener('click', closeCardModal);

    // Clic sur le fond
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeCardModal();
    });

    // Touche Échap
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) closeCardModal();
    });

    // Délégation sur les boutons Gérer
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-open-card-modal');
        if (!btn) return;
        const card = JSON.parse(btn.dataset.card);
        openCardModal(card);
    });
}());
</script>

