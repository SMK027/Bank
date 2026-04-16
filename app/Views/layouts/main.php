<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'BankApp') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="<?= is_authenticated() ? '/dashboard' : '/' ?>" class="navbar-brand">🏦 BankApp</a>

            <button class="navbar-toggle" id="navToggle" aria-label="Menu">
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
                    ?>

                    <!-- Liens principaux -->
                    <div class="navbar-group navbar-nav-links">
                        <a href="/dashboard" class="navbar-link"><i class="bi bi-speedometer2"></i> <span class="nav-label">Tableau de bord</span></a>
                        <a href="/accounts/create" class="navbar-link"><i class="bi bi-plus-circle"></i> <span class="nav-label">Nouveau compte</span></a>
                        <a href="/transfers/create" class="navbar-link"><i class="bi bi-arrow-left-right"></i> <span class="nav-label">Virement</span></a>
                        <a href="/tickets" class="navbar-link"><i class="bi bi-ticket-perforated"></i> <span class="nav-label">Demandes</span></a>
                        <?php if (is_moderator()): ?>
                        <a href="/moderation" class="navbar-link navbar-link-mod"><i class="bi bi-shield-check"></i> <span class="nav-label">Modération</span></a>
                        <?php endif; ?>
                    </div>

                    <!-- Icônes rapides (messagerie + notifications) -->
                    <div class="navbar-group navbar-quick-icons">
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
                <?php else: ?>
                    <a href="/login" class="navbar-link">Connexion</a>
                    <a href="/register" class="btn btn-sm btn-primary">Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </main>

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
