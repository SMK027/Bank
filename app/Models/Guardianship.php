<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Gestion des tutelles légales : lien entre un mineur et ses responsables légaux (adultes).
 *
 * Règles métier :
 *  - Un mineur peut avoir 1 ou 2 responsables légaux.
 *  - La tutelle est automatiquement inactive dès que le mineur atteint 18 ans (calculé dynamiquement).
 *  - Les enregistrements restent en base pour l'historique.
 *  - Seuls les modérateurs peuvent créer ou supprimer des tutelles.
 */
class Guardianship extends Model
{
    protected string $table = 'guardianships';

    /**
     * Crée un lien de tutelle légale.
     * Les vérifications (max 2 tuteurs, adulte, non-doublon) sont à la charge du contrôleur.
     */
    public function addGuardian(int $minorId, int $guardianId, int $createdBy): int
    {
        return $this->create([
            'minor_user_id'    => $minorId,
            'guardian_user_id' => $guardianId,
            'created_by'       => $createdBy,
        ]);
    }

    /**
     * Supprime un lien de tutelle par son ID.
     */
    public function removeGuardianship(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Retourne tous les enregistrements de tutelle pour un mineur donné.
     */
    public function getGuardiansOf(int $minorId): array
    {
        return $this->findBy(['minor_user_id' => (string) $minorId]);
    }

    /**
     * Retourne tous les enregistrements de tutelle pour un responsable légal donné.
     */
    public function getMinorsOf(int $guardianId): array
    {
        return $this->findBy(['guardian_user_id' => (string) $guardianId]);
    }

    /**
     * Compte le nombre de responsables légaux d'un mineur.
     */
    public function countGuardiansOf(int $minorId): int
    {
        return count($this->getGuardiansOf($minorId));
    }

    /**
     * Vérifie si un lien de tutelle (sans condition d'âge) existe entre les deux utilisateurs.
     */
    public function isGuardianOf(int $guardianId, int $minorId): bool
    {
        $record = $this->findOneBy([
            'minor_user_id'    => (string) $minorId,
            'guardian_user_id' => (string) $guardianId,
        ]);
        return $record !== null;
    }

    /**
     * Vérifie si l'utilisateur est responsable légal ACTIF du mineur.
     * La tutelle n'est active que si le mineur a encore moins de 18 ans.
     * Retourne false dès que le mineur est devenu majeur (procuration expirée automatiquement).
     */
    public function isActiveGuardianOf(int $guardianId, int $minorId): bool
    {
        if (!$this->isGuardianOf($guardianId, $minorId)) {
            return false;
        }

        $userModel = new User();
        $minor     = $userModel->find($minorId);
        if (!$minor) {
            return false;
        }

        return User::isMinorFromDate($minor['birth_date'] ?? null);
    }
}
