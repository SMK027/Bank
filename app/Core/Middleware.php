<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Middleware pour les vérifications d'accès et de rôles.
 *
 * Fournit des méthodes statiques pour vérifier les rôles globaux
 * de l'utilisateur connecté. À étendre selon les besoins du projet
 * (ex: vérification d'accès à des espaces, groupes, etc.).
 */
class Middleware
{
    /**
     * Vérifie que l'utilisateur connecté possède le rôle global requis.
     *
     * @param array $allowedRoles Rôles autorisés
     * @return bool
     */
    public static function hasGlobalRole(array $allowedRoles = ['admin']): bool
    {
        $role = Session::get('global_role');
        return $role !== null && in_array($role, $allowedRoles, true);
    }

    /**
     * Vérifie si l'utilisateur est superadmin.
     */
    public static function isSuperAdmin(): bool
    {
        return Session::get('global_role') === 'superadmin';
    }

    /**
     * Vérifie si l'utilisateur fait partie du staff global
     * (superadmin, admin ou moderator).
     */
    public static function isGlobalStaff(): bool
    {
        $role = Session::get('global_role');
        return in_array($role, ['superadmin', 'admin', 'moderator'], true);
    }

    /**
     * Vérifie que l'utilisateur est connecté.
     */
    public static function isAuthenticated(): bool
    {
        return Session::get('user_id') !== null;
    }

    /**
     * Exige un rôle global, sinon retourne false.
     * Exemple d'utilisation dans un contrôleur :
     *
     *   if (!Middleware::requireRole(['admin', 'moderator'])) {
     *       $this->setFlash('danger', 'Accès non autorisé.');
     *       $this->redirect('/');
     *   }
     *
     * @param array $roles Rôles autorisés
     * @return bool
     */
    public static function requireRole(array $roles): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }
        return self::hasGlobalRole($roles);
    }
}
