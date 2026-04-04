<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Contrôleur de la page d'accueil.
 * Exemple de contrôleur simple à adapter.
 */
class HomeController extends Controller
{
    /**
     * Page d'accueil.
     */
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Accueil',
        ]);
    }

    /**
     * Page de mentions légales.
     */
    public function legal(): void
    {
        $this->render('home/legal', [
            'title' => 'Mentions légales',
        ]);
    }
}
