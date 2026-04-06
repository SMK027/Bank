<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Contrôleur d'authentification.
 * Gère connexion, inscription et déconnexion.
 * À enrichir selon les besoins (mot de passe oublié, vérification email, etc.).
 */
class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Formulaire de connexion.
     */
    public function loginForm(): void
    {
        $this->render('auth/login', ['title' => 'Connexion']);
    }

    /**
     * Traitement de la connexion.
     */
    public function login(): void
    {
        $this->validateCSRF();
        $data = $this->getPostData(['email', 'password']);

        if (empty($data['email']) || empty($data['password'])) {
            $this->setFlash('danger', 'Tous les champs sont requis.');
            $this->redirect('/login');
            return;
        }

        $user = $this->userModel->authenticate($data['email'], $data['password']);

        if (!$user) {
            AuditLog::log(null, AuditLog::ACTION_AUTH_LOGIN_FAILED, ['email' => $data['email']]);
            $this->setFlash('danger', 'Identifiants incorrects.');
            $this->redirect('/login');
            return;
        }

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
     * Formulaire d'inscription.
     */
    public function registerForm(): void
    {
        $this->render('auth/register', ['title' => 'Inscription']);
    }

    /**
     * Traitement de l'inscription.
     */
    public function register(): void
    {
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
}
