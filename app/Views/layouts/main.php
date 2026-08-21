<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#4361ee">
    <title><?= e($title ?? 'BankApp') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="<?= is_authenticated() ? '/dashboard' : '/' ?>" class="navbar-brand">🏦 BankApp</a>

            <button class="navbar-toggle" id="navToggle" aria-label="Menu" aria-controls="navMenu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="navbar-menu" id="navMenu">
                <?php if (is_authenticated()): ?>
                    <?php
                        $__convModel    = new \App\Models\Conversation();
                        $__unreadMsgs   = $__convModel->countTotalUnread((int) current_user_id());
                        $__notifModel   = new \App\Models\Notification();
                        $__unreadNotifs = $__notifModel->countUnread((int) current_user_id());
                        $__prModel      = new \App\Models\PaymentRequest();
                        $__pendingPR    = $__prModel->countPendingForRecipient((int) current_user_id());
                        $__friendModel  = new \App\Models\Friendship();
                        $__pendingFR    = $__friendModel->countPendingReceived((int) current_user_id());
                    ?>

                    <div class="navbar-panel navbar-panel-primary">
                        <div class="navbar-group navbar-nav-links">
                            <!-- Tableau de bord -->
                            <a href="/dashboard" class="navbar-link"><i class="bi bi-speedometer2"></i> <span class="nav-label">Tableau de bord</span></a>

                            <span class="navbar-sep" aria-hidden="true"></span>

                            <!-- Dropdown : Opérations -->
                            <div class="navbar-dropdown" id="opsDropdown">
                                <button class="navbar-dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="opsMenu">
                                    <i class="bi bi-grid"></i>
                                    <span class="nav-label">Opérations</span>
                                    <i class="bi bi-chevron-down navbar-dropdown-chevron"></i>
                                </button>
                                <div class="navbar-dropdown-menu" id="opsMenu" role="menu">
                                    <a href="/accounts/create" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-plus-circle"></i> Nouveau compte
                                    </a>
                                    <a href="/transfers/create" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-arrow-left-right"></i> Virement
                                    </a>
                                    <a href="/tickets" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-ticket-perforated"></i> Demandes
                                    </a>
                                    <?php if (is_professional() || is_moderator()): ?>
                                    <a href="/pos" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-shop"></i> TPE
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Dropdown : Finance -->
                            <div class="navbar-dropdown" id="financeDropdown">
                                <button class="navbar-dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="financeMenu">
                                    <i class="bi bi-bar-chart-line"></i>
                                    <span class="nav-label">Finance</span>
                                    <i class="bi bi-chevron-down navbar-dropdown-chevron"></i>
                                </button>
                                <div class="navbar-dropdown-menu" id="financeMenu" role="menu">
                                    <a href="/loans/simulator" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-calculator-fill"></i> Simulateur
                                    </a>
                                    <a href="/loans" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-cash-coin"></i> Crédits
                                    </a>
                                    <a href="/budget" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-pie-chart-fill"></i> Budgets
                                    </a>
                                </div>
                            </div>

                            <span class="navbar-sep" aria-hidden="true"></span>

                            <!-- Dropdown : Paiements -->
                            <div class="navbar-dropdown" id="payDropdown">
                                <button class="navbar-dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="payMenu">
                                    <i class="bi bi-wallet2"></i>
                                    <span class="nav-label">Paiements</span>
                                    <i class="bi bi-chevron-down navbar-dropdown-chevron"></i>
                                </button>
                                <div class="navbar-dropdown-menu" id="payMenu" role="menu">
                                    <a href="/cards" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-credit-card-2-front"></i> Cartes
                                    </a>
                                    <a href="/checkbooks" class="navbar-dropdown-item" role="menuitem">
                                        <i class="bi bi-journal-check"></i> Chéquiers
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (is_moderator()): ?>
                    <!-- Section modération — hors du conteneur scrollable pour permettre l'overflow du dropdown -->
                    <div class="navbar-mod-section">
                        <div class="navbar-sep"></div>
                        <div class="navbar-dropdown navbar-mod-dropdown" id="modDropdown">
                            <button class="navbar-dropdown-toggle navbar-mod-toggle" id="modToggle" aria-expanded="false" aria-haspopup="true" aria-controls="modMenu">
                                <i class="bi bi-shield-check"></i>
                                <span class="nav-label">Modération</span>
                                <i class="bi bi-chevron-down navbar-mod-chevron"></i>
                            </button>
                            <div class="navbar-mod-menu" id="modMenu" role="menu">
                                <div class="navbar-mod-menu-header">Modération</div>

                                <div class="navbar-mod-group-label">Vue d'ensemble</div>
                                <a href="/moderation" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-speedometer2"></i> Tableau de bord
                                </a>
                                <a href="/moderation/audit-log" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-journal-text"></i> Journal d'audit
                                </a>

                                <div class="navbar-mod-sep"></div>
                                <div class="navbar-mod-group-label">Comptes &amp; Utilisateurs</div>
                                <a href="/moderation/users" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-people"></i> Utilisateurs
                                </a>
                                <a href="/moderation/accounts/create" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-plus-square"></i> Créer un compte
                                </a>
                                <a href="/moderation/internal-accounts/create" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-tools"></i> Compte interne (test)
                                </a>
                                <a href="/moderation/guardianships" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-person-heart"></i> Tutelles
                                </a>

                                <div class="navbar-mod-sep"></div>
                                <div class="navbar-mod-group-label">Transactions</div>
                                <a href="/moderation/transfers" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-arrow-left-right"></i> Virements
                                </a>
                                <a href="/moderation/direct-debits" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-calendar2-check"></i> Prélèvements
                                </a>
                                <a href="/moderation/mandates" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-file-earmark-text"></i> Mandats
                                </a>
                                <a href="/moderation/recurring-transfers" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-arrow-repeat"></i> Virements récurrents
                                </a>

                                <div class="navbar-mod-sep"></div>
                                <div class="navbar-mod-group-label">Crédit &amp; Épargne</div>
                                <a href="/moderation/loans" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-cash-coin"></i> Crédits
                                </a>
                                <a href="/moderation/savings-rate" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-piggy-bank"></i> Taux d'épargne
                                </a>

                                <div class="navbar-mod-sep"></div>
                                <div class="navbar-mod-group-label">Support</div>
                                <a href="/moderation/tickets" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-ticket-perforated"></i> Tickets
                                </a>
                                <a href="/moderation/api-clients" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-plug"></i> Clients API
                                </a>
                                <a href="/moderation/pos-payments" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-shop"></i> Paiements TPE
                                </a>

                                <div class="navbar-mod-sep"></div>
                                <div class="navbar-mod-group-label">Découvert</div>
                                <a href="/moderation/overdraft-authorizations" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-shield-plus"></i> Autorisations de dépassement
                                </a>

                                <div class="navbar-mod-sep"></div>
                                <div class="navbar-mod-group-label">Configuration</div>
                                <a href="/moderation/features" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-toggles"></i> Fonctionnalités
                                </a>
                                <a href="/moderation/supervisors" class="navbar-mod-item" role="menuitem">
                                    <i class="bi bi-person-badge"></i> Superviseurs
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="navbar-panel navbar-panel-secondary">
                        <!-- Icônes rapides (messagerie + notifications) -->
                        <div class="navbar-group navbar-quick-icons">
                            <a href="/friends" class="navbar-icon-link" title="Amis">
                                <i class="bi bi-people<?= $__pendingFR > 0 ? '-fill' : '' ?>"></i>
                                <?php if ($__pendingFR > 0): ?>
                                    <span class="notif-badge"><?= $__pendingFR > 99 ? '99+' : $__pendingFR ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="/payment-requests" class="navbar-icon-link" title="Demandes d'argent">
                                <i class="bi bi-send<?= $__pendingPR > 0 ? '-fill' : '' ?>"></i>
                                <?php if ($__pendingPR > 0): ?>
                                    <span class="notif-badge"><?= $__pendingPR > 99 ? '99+' : $__pendingPR ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="/messages" class="navbar-icon-link" title="Messagerie">
                                <i class="bi bi-envelope<?= $__unreadMsgs > 0 ? '-fill' : '' ?>"></i>
                                <?php if ($__unreadMsgs > 0): ?>
                                    <span class="notif-badge"><?= $__unreadMsgs > 99 ? '99+' : $__unreadMsgs ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="/notifications" class="navbar-icon-link" title="Notifications">
                                <i class="bi bi-bell<?= $__unreadNotifs > 0 ? '-fill' : '' ?>"></i>
                                <?php if ($__unreadNotifs > 0): ?>
                                    <span class="notif-badge"><?= $__unreadNotifs > 99 ? '99+' : $__unreadNotifs ?></span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <!-- Utilisateur -->
                        <div class="navbar-group navbar-user">
                            <a href="/profile" class="navbar-link navbar-profile-link">
                                <span class="navbar-avatar navbar-avatar-placeholder"><?= strtoupper(substr(current_username(), 0, 1)) ?></span>
                                <span class="nav-label"><?= e(current_username()) ?></span>
                            </a>
                            <a href="/logout" class="btn btn-sm btn-outline">Déconnexion</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="navbar-panel navbar-panel-secondary navbar-panel-guest">
                        <a href="/login" class="navbar-link">Connexion</a>
                        <a href="/register" class="btn btn-sm btn-primary">Inscription</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <?php include __DIR__ . '/../partials/flash.php'; ?>

            <?php if (is_authenticated() && is_moderator() && is_account_control_active()): ?>
                <?php $control = account_control_context(); ?>
                <div class="alert alert-warning" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <strong><i class="bi bi-incognito"></i> Prise de main active</strong>
                        <div style="font-size:0.9rem;opacity:0.95;">
                            Vous utilisez actuellement le compte de <strong><?= e((string) ($control['target_username'] ?? '')) ?></strong> (#<?= (int) ($control['target_user_id'] ?? 0) ?>).
                            Toute opération de modération nécessite une authentification superviseur.
                        </div>
                    </div>
                    <form method="POST" action="/moderation/users/control/stop" style="margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline btn-sm">
                            <i class="bi bi-box-arrow-left"></i> Quitter la prise de main
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </main>

    <?php if (is_authenticated()): ?>
    <!-- Barre de navigation inférieure (visible uniquement sur mobile ≤ 768px) -->
    <nav class="bottom-nav" aria-label="Navigation principale mobile">
        <a href="/dashboard" class="bottom-nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Tableau de bord</span>
        </a>
        <a href="/accounts/create" class="bottom-nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/accounts/create') ? 'active' : '' ?>">
            <i class="bi bi-plus-circle"></i>
            <span>Compte</span>
        </a>
        <a href="/transfers/create" class="bottom-nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/transfers') ? 'active' : '' ?>">
            <i class="bi bi-arrow-left-right"></i>
            <span>Virement</span>
        </a>
        <a href="/notifications" class="bottom-nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/notifications') ? 'active' : '' ?>" style="position:relative;">
            <i class="bi bi-bell<?= $__unreadNotifs > 0 ? '-fill' : '' ?>"></i>
            <?php if ($__unreadNotifs > 0): ?>
                <span class="notif-badge"><?= $__unreadNotifs > 99 ? '99+' : $__unreadNotifs ?></span>
            <?php endif; ?>
            <span>Alertes</span>
        </a>
        <a href="/profile" class="bottom-nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/profile') ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>Profil</span>
        </a>
    </nav>
    <?php endif; ?>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> BankApp — Simulation bancaire</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script src="/js/app.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/app.js') ?>"></script>
</body>
</html>
