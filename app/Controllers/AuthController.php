<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
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
            $this->setFlash('danger', 'Identifiants incorrects.');
            $this->redirect('/login');
            return;
        }

        // Régénérer l'ID de session (sécurité)
        Session::regenerate();

        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);

        $this->setFlash('success', 'Bienvenue, ' . $user['username'] . ' !');
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
        $data = $this->getPostData(['username', 'email', 'password']);

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $this->setFlash('danger', 'Tous les champs sont requis.');
            $this->redirect('/register');
            return;
        }

        if (strlen($data['password']) < 8) {
            $this->setFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
            $this->redirect('/register');
            return;
        }

        // Vérifier l'unicité de l'email
        if ($this->userModel->findByEmail($data['email'])) {
            $this->setFlash('danger', 'Cette adresse email est déjà utilisée.');
            $this->redirect('/register');
            return;
        }

        // Vérifier l'unicité du nom d'utilisateur
        if ($this->userModel->findByUsername($data['username'])) {
            $this->setFlash('danger', 'Ce nom d\'utilisateur est déjà pris.');
            $this->redirect('/register');
            return;
        }

        $userId = $this->userModel->register($data['username'], $data['email'], $data['password']);

        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('username', $data['username']);
        Session::set('global_role', 'user');

        $this->setFlash('success', 'Compte créé avec succès !');
        $this->redirect('/dashboard');
    }

    /**
     * Déconnexion.
     */
    public function logout(): void
    {
        Session::destroy();
        Session::start();
        $this->setFlash('success', 'Vous avez été déconnecté.');
        $this->redirect('/login');
    }
}
