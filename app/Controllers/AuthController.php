<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\LoginRateLimit;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Mailer;

/**
 * Contrôleur d'authentification.
 * Gère connexion, inscription et déconnexion.
 * À enrichir selon les besoins (mot de passe oublié, vérification email, etc.).
 */
class AuthController extends Controller
{
    private User           $userModel;
    private LoginRateLimit $rateLimitModel;

    public function __construct()
    {
        $this->userModel      = new User();
        $this->rateLimitModel = new LoginRateLimit();
    }

    /**
     * Formulaire de connexion.
     */
    public function loginForm(): void
    {
        $this->requireFeature('auth.login');
        $ip           = LoginRateLimit::resolveClientIp();
        $blockedUntil = $this->rateLimitModel->getBlockedUntil($ip);
        if ($blockedUntil !== null) {
            $remaining = $this->minutesRemaining($blockedUntil);
            $this->setFlash('danger', "Trop de tentatives de connexion échouées. Votre accès est bloqué. Réessayez dans {$remaining} minute" . ($remaining > 1 ? 's' : '') . '.');
        }
        $this->render('auth/login', ['title' => 'Connexion']);
    }

    /**
     * Traitement de la connexion.
     */
    public function login(): void
    {
        $this->requireFeature('auth.login');
        $this->validateCSRF();

        $ip   = LoginRateLimit::resolveClientIp();
        $data = $this->getPostData(['email', 'password']);
        $targetIsModerator = !empty($data['email']) && $this->isModeratorEmail((string) $data['email']);

        if (!$targetIsModerator && $this->rateLimitModel->isBlocked($ip)) {
            AuditLog::log(null, AuditLog::ACTION_AUTH_LOGIN_FAILED, ['ip_blocked' => true]);
            $remaining = $this->minutesRemaining($this->rateLimitModel->getBlockedUntil($ip));
            $this->setFlash('danger', "Trop de tentatives de connexion échouées. Réessayez dans {$remaining} minute" . ($remaining > 1 ? 's' : '') . '.');
            $this->redirect('/login');
            return;
        }

        if (empty($data['email']) || empty($data['password'])) {
            $this->setFlash('danger', 'Tous les champs sont requis.');
            $this->redirect('/login');
            return;
        }

        $user = $this->userModel->authenticate($data['email'], $data['password']);

        if (!$user) {
            $justBlocked = !$targetIsModerator && $this->rateLimitModel->recordFailedAttempt($ip);
            AuditLog::log(null, AuditLog::ACTION_AUTH_LOGIN_FAILED, ['email' => $data['email']]);
            if ($justBlocked) {
                AuditLog::log(null, AuditLog::ACTION_AUTH_IP_BLOCKED, ['ip' => $ip]);
                $this->setFlash('danger', 'Trop de tentatives de connexion échouées. Votre accès est bloqué pendant 30 minutes.');
            } else {
                $this->setFlash('danger', 'Identifiants incorrects.');
            }
            $this->redirect('/login');
            return;
        }

        // Identifiants corrects : réinitialiser le compteur de tentatives
        $this->rateLimitModel->clearIp($ip);

        // Vérifier que le compte n'est pas suspendu ou banni
        $status = $user['status'] ?? 'active';
        if ($status === 'suspended') {
            $msg = 'Votre compte est suspendu';
            if (!empty($user['suspended_until'])) {
                $dt   = \DateTime::createFromFormat('Y-m-d H:i:s', $user['suspended_until']);
                $msg .= ' jusqu\'au ' . ($dt ? $dt->format('d/m/Y') : $user['suspended_until']);
            }
            AuditLog::log($user['id'], AuditLog::ACTION_AUTH_LOGIN_FAILED, ['email' => $data['email'], 'reason' => 'suspended']);
            $this->setFlash('danger', $msg . '.');
            $this->redirect('/login');
            return;
        }
        if ($status === 'banned') {
            AuditLog::log($user['id'], AuditLog::ACTION_AUTH_LOGIN_FAILED, ['email' => $data['email'], 'reason' => 'banned']);
            $this->setFlash('danger', 'Votre compte a été banni de la plateforme.');
            $this->redirect('/login');
            return;
        }

        // Régénérer l'ID de session (sécurité)
        Session::regenerate();

        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);
        Session::set('birth_date_missing', empty($user['birth_date']));

        $this->setFlash('success', 'Bienvenue, ' . $user['username'] . ' !');
        AuditLog::log($user['id'], AuditLog::ACTION_AUTH_LOGIN, ['username' => $user['username']], targetUserId: $user['id']);
        $this->redirect('/dashboard');
    }

    /**
     * Formulaire de connexion par numéro de compte + code PIN.
     */
    public function loginPinForm(): void
    {
        $this->requireFeature('auth.login_pin');
        $raw      = strtoupper(trim($_GET['account'] ?? ''));
        $prefilled = preg_match('/^BK\d{8}$/', $raw) ? $raw : '';

        $ip           = LoginRateLimit::resolveClientIp();
        $blockedUntil = $this->rateLimitModel->getBlockedUntil($ip);
        if ($blockedUntil !== null) {
            $remaining = $this->minutesRemaining($blockedUntil);
            $this->setFlash('danger', "Trop de tentatives de connexion échouées. Votre accès est bloqué. Réessayez dans {$remaining} minute" . ($remaining > 1 ? 's' : '') . '.');
        }

        $this->render('auth/login_pin', [
            'title'     => 'Connexion par code PIN',
            'prefilled' => $prefilled,
        ]);
    }

    /**
     * Traitement de la connexion par numéro de compte + code PIN.
     */
    public function loginPin(): void
    {
        $this->requireFeature('auth.login_pin');
        $this->validateCSRF();

        $ip = LoginRateLimit::resolveClientIp();
        $data = $this->getPostData(['account_number', 'pin']);

        $accountNumber = trim($data['account_number'] ?? '');
        $pin           = $data['pin'] ?? '';
        $targetIsModerator = $accountNumber !== '' && $this->isModeratorAccountNumber($accountNumber);

        if (!$targetIsModerator && $this->rateLimitModel->isBlocked($ip)) {
            AuditLog::log(null, AuditLog::ACTION_AUTH_LOGIN_FAILED, ['ip_blocked' => true]);
            $remaining = $this->minutesRemaining($this->rateLimitModel->getBlockedUntil($ip));
            $this->setFlash('danger', "Trop de tentatives de connexion échouées. Réessayez dans {$remaining} minute" . ($remaining > 1 ? 's' : '') . '.');
            $this->redirect('/login/pin');
            return;
        }

        if ($accountNumber === '' || $pin === '') {
            $this->setFlash('danger', 'Tous les champs sont requis.');
            $this->redirect('/login/pin');
            return;
        }

        if (!preg_match('/^\d{6}$/', $pin)) {
            $this->setFlash('danger', 'Le code PIN doit contenir exactement 6 chiffres.');
            $this->redirect('/login/pin');
            return;
        }

        $user = $this->userModel->authenticateByPin($accountNumber, $pin);

        if (!$user) {
            $justBlocked = !$targetIsModerator && $this->rateLimitModel->recordFailedAttempt($ip);
            AuditLog::log(null, AuditLog::ACTION_AUTH_LOGIN_FAILED, ['account_number' => $accountNumber]);
            if ($justBlocked) {
                AuditLog::log(null, AuditLog::ACTION_AUTH_IP_BLOCKED, ['ip' => $ip]);
                $this->setFlash('danger', 'Trop de tentatives de connexion échouées. Votre accès est bloqué pendant 30 minutes.');
            } else {
                $this->setFlash('danger', 'Numéro de compte ou code PIN incorrect.');
            }
            $this->redirect('/login/pin');
            return;
        }

        // Identifiants corrects : réinitialiser le compteur de tentatives
        $this->rateLimitModel->clearIp($ip);

        // Vérifier que le compte n'est pas suspendu ou banni
        $status = $user['status'] ?? 'active';
        if ($status === 'suspended') {
            $msg = 'Votre compte est suspendu';
            if (!empty($user['suspended_until'])) {
                $dt   = \DateTime::createFromFormat('Y-m-d H:i:s', $user['suspended_until']);
                $msg .= ' jusqu\'au ' . ($dt ? $dt->format('d/m/Y') : $user['suspended_until']);
            }
            AuditLog::log($user['id'], AuditLog::ACTION_AUTH_LOGIN_FAILED, ['account_number' => $accountNumber, 'reason' => 'suspended']);
            $this->setFlash('danger', $msg . '.');
            $this->redirect('/login/pin');
            return;
        }
        if ($status === 'banned') {
            AuditLog::log($user['id'], AuditLog::ACTION_AUTH_LOGIN_FAILED, ['account_number' => $accountNumber, 'reason' => 'banned']);
            $this->setFlash('danger', 'Votre compte a été banni de la plateforme.');
            $this->redirect('/login/pin');
            return;
        }

        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);
        Session::set('birth_date_missing', empty($user['birth_date']));

        $this->setFlash('success', 'Bienvenue, ' . $user['username'] . ' !');
        AuditLog::log($user['id'], AuditLog::ACTION_AUTH_LOGIN, ['username' => $user['username']], targetUserId: $user['id']);

        // Si le PIN est temporaire, forcer le changement immédiat
        if (!empty($user['pin_must_change'])) {
            Session::set('pin_must_change', true);
            $this->redirect('/profile/pin/change');
            return;
        }

        $this->redirect('/dashboard');
    }

    /**
     * Formulaire d'inscription.
     */
    public function registerForm(): void
    {
        $this->requireFeature('auth.register');
        $this->render('auth/register', ['title' => 'Inscription']);
    }

    /**
     * Traitement de l'inscription.
     */
    public function register(): void
    {
        $this->requireFeature('auth.register');
        $this->validateCSRF();
        $data = $this->getPostData(['username', 'email', 'password', 'birth_date']);

        if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['birth_date'])) {
            $this->setFlash('danger', 'Tous les champs sont requis, y compris la date de naissance.');
            $this->redirect('/register');
            return;
        }

        if (strlen($data['password']) < 8) {
            $this->setFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
            $this->redirect('/register');
            return;
        }

        $dob = \DateTime::createFromFormat('Y-m-d', $data['birth_date']);
        if (!$dob || $dob->format('Y-m-d') !== $data['birth_date']) {
            $this->setFlash('danger', 'Date de naissance invalide.');
            $this->redirect('/register');
            return;
        }
        $now = new \DateTime();
        if ($dob > $now) {
            $this->setFlash('danger', 'La date de naissance ne peut pas être dans le futur.');
            $this->redirect('/register');
            return;
        }
        if ($now->diff($dob)->y > 120) {
            $this->setFlash('danger', 'Date de naissance invalide.');
            $this->redirect('/register');
            return;
        }

        if ($this->userModel->findByEmail($data['email'])) {
            $this->setFlash('danger', 'Cette adresse email est déjà utilisée.');
            $this->redirect('/register');
            return;
        }

        if ($this->userModel->findByUsername($data['username'])) {
            $this->setFlash('danger', 'Ce nom d\'utilisateur est déjà pris.');
            $this->redirect('/register');
            return;
        }

        $userId = $this->userModel->register($data['username'], $data['email'], $data['password'], $data['birth_date']);

        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('username', $data['username']);
        Session::set('global_role', 'user');
        Session::set('birth_date_missing', false);

        $isMinor = User::isMinorFromDate($data['birth_date']);
        AuditLog::log($userId, AuditLog::ACTION_AUTH_REGISTER, ['username' => $data['username'], 'email' => $data['email']], targetUserId: $userId);
        $this->setFlash('success', 'Compte créé avec succès !' . ($isMinor ? ' (profil mineur : types de comptes limités)' : ''));
        $this->redirect('/dashboard');
    }

    /**
     * Déconnexion.
     */
    public function logout(): void
    {
        $uid = $this->getCurrentUserId();
        Session::destroy();
        Session::start();
        AuditLog::log($uid, AuditLog::ACTION_AUTH_LOGOUT, []);
        $this->setFlash('success', 'Vous avez été déconnecté.');
        $this->redirect('/login');
    }

    // ─── Réinitialisation du mot de passe ───────────────────────────────────

    /**
     * Formulaire de demande de réinitialisation.
     */
    public function forgotPasswordForm(): void
    {
        $this->requireFeature('auth.password_reset');
        $this->render('auth/forgot_password', ['title' => 'Mot de passe oublié']);
    }

    /**
     * Traitement de la demande : génère un token et envoie l'email.
     * La réponse est volontairement neutre pour ne pas révéler l'existence d'un compte.
     */
    public function forgotPassword(): void
    {
        $this->requireFeature('auth.password_reset');
        $this->validateCSRF();
        $email = trim($this->getPostData(['email'])['email'] ?? '');

        if ($email !== '') {
            $user = $this->userModel->findByEmail($email);
            if ($user) {
                $resetModel = new PasswordReset();
                $token      = $resetModel->createToken((int)$user['id']);
                $appUrl     = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
                $resetUrl   = "{$appUrl}/reset-password/{$token}";

                try {
                    (new Mailer())->sendPasswordReset($email, $user['username'], $resetUrl);
                } catch (\Throwable) {
                    // L'email est silencieusement ignoré pour ne pas bloquer
                }

                AuditLog::log(
                    (int)$user['id'],
                    AuditLog::ACTION_AUTH_PASSWORD_RESET_REQUEST,
                    ['email' => $email],
                    targetUserId: (int)$user['id']
                );
            }
        }

        // Toujours le même message pour éviter l'énumération d'emails
        $this->setFlash('success', 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation sous quelques instants.');
        $this->redirect('/forgot-password');
    }

    /**
     * Formulaire de saisie du nouveau mot de passe.
     */
    public function resetPasswordForm(string $token): void
    {
        $this->requireFeature('auth.password_reset');
        $resetModel = new PasswordReset();
        $record     = $resetModel->findValidByToken($token);

        if (!$record) {
            $this->setFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            $this->redirect('/forgot-password');
            return;
        }

        $this->render('auth/reset_password', [
            'title' => 'Nouveau mot de passe',
            'token' => $token,
        ]);
    }

    /**
     * Traitement du nouveau mot de passe.
     */
    public function resetPassword(string $token): void
    {
        $this->requireFeature('auth.password_reset');
        $this->validateCSRF();

        $resetModel = new PasswordReset();
        $record     = $resetModel->findValidByToken($token);

        if (!$record) {
            $this->setFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            $this->redirect('/forgot-password');
            return;
        }

        $data     = $this->getPostData(['password', 'password_confirm']);
        $password = $data['password']         ?? '';
        $confirm  = $data['password_confirm'] ?? '';

        if (strlen($password) < 8) {
            $this->setFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        if ($password !== $confirm) {
            $this->setFlash('danger', 'Les mots de passe ne correspondent pas.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        $userId = (int)$record['user_id'];
        $this->userModel->updatePassword($userId, $password);
        $resetModel->markUsed((int)$record['id']);

        AuditLog::log($userId, AuditLog::ACTION_AUTH_PASSWORD_RESET, [], targetUserId: $userId);

        $this->setFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
        $this->redirect('/login');
    }

    // ─── Helpers privés ─────────────────────────────────────────────────────

    /**
     * Calcule le nombre de minutes restantes avant la fin d'un blocage IP.
     */
    private function minutesRemaining(?string $blockedUntil): int
    {
        if ($blockedUntil === null) {
            return 0;
        }
        return max(1, (int) ceil((strtotime($blockedUntil) - time()) / 60));
    }

    /**
     * Indique si l'adresse e-mail correspond à un compte modérateur.
     * Utilisé pour exempter les modérateurs de la restriction de connexion.
     */
    private function isModeratorEmail(string $email): bool
    {
        $user = $this->userModel->findByEmail($email);
        return $user !== null && ($user['global_role'] ?? 'user') === 'moderator';
    }

    /**
     * Indique si le numéro de compte correspond à un modérateur.
     */
    private function isModeratorAccountNumber(string $accountNumber): bool
    {
        $user = $this->userModel->findByAccountNumber($accountNumber);
        return $user !== null && ($user['global_role'] ?? 'user') === 'moderator';
    }
}
