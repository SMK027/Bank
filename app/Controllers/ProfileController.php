<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
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

    /**
     * Formulaire de saisie de la date de naissance (utilisateurs existants sans birth_date).
     */
    public function birthDateForm(): void
    {
        $this->requireAuth();

        $this->render('profile/birth_date', [
            'title' => 'Date de naissance requise',
        ]);
    }

    /**
     * Enregistre la date de naissance (POST).
     */
    public function saveBirthDate(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $data      = $this->getPostData(['birth_date']);
        $birthDate = $data['birth_date'];

        // Validation : format Y-m-d
        $parsed = \DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $birthDate) {
            $this->setFlash('danger', 'Format de date invalide.');
            $this->redirect('/profile/birth-date');
            return;
        }

        // Ne pas accepter une date future
        if ($parsed > new \DateTime()) {
            $this->setFlash('danger', 'La date de naissance ne peut pas être dans le futur.');
            $this->redirect('/profile/birth-date');
            return;
        }

        // Plafond à 120 ans
        if ($parsed < new \DateTime('-120 years')) {
            $this->setFlash('danger', 'Date de naissance invalide (plus de 120 ans).');
            $this->redirect('/profile/birth-date');
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->userModel->updateBirthDate($userId, $birthDate);

        // Mettre à jour le drapeau en session
        Session::set('birth_date_missing', false);

        $this->setFlash('success', 'Date de naissance enregistrée.');
        $this->redirect('/dashboard');
    }
}
