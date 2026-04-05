<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class ProfileController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Affiche le profil de l'utilisateur connecté.
     */
    public function index(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $user   = $this->userModel->find($userId);

        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('profile/index', [
            'title' => 'Mon profil',
            'user'  => $user,
        ]);
    }

    /**
     * Traitement du changement de mot de passe.
     */
    public function updatePassword(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['current_password', 'new_password', 'confirm_password']);

        if (empty($data['current_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
            $this->setFlash('danger', 'Tous les champs sont requis.');
            $this->redirect('/profile');
            return;
        }

        if (strlen($data['new_password']) < 8) {
            $this->setFlash('danger', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            $this->redirect('/profile');
            return;
        }

        if ($data['new_password'] !== $data['confirm_password']) {
            $this->setFlash('danger', 'Les mots de passe ne correspondent pas.');
            $this->redirect('/profile');
            return;
        }

        $user = $this->userModel->find($userId);
        if (!$user || !password_verify($data['current_password'], $user['password'])) {
            $this->setFlash('danger', 'Mot de passe actuel incorrect.');
            $this->redirect('/profile');
            return;
        }

        $this->userModel->updatePassword($userId, $data['new_password']);

        $this->setFlash('success', 'Mot de passe mis à jour avec succès.');
        $this->redirect('/profile');
    }
}
