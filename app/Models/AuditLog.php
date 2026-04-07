<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Journal d'audit : trace toutes les actions significatives de la plateforme.
 *
 * Utilisation — appel statique depuis n'importe quel contexte :
 *   AuditLog::log($userId, AuditLog::ACTION_ACCOUNT_FREEZE, ['name' => '...'], targetAccountId: 5);
 *
 * Le log ne lève jamais d'exception : une erreur d'écriture est silencieuse
 * pour ne jamais bloquer l'opération principale.
 */
class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    /** La table audit_logs est append-only : pas de colonne updated_at. */
    protected bool $hasUpdatedAt = false;

    // ── Constantes d'actions ────────────────────────────────────────────────

    // Authentification
    public const ACTION_AUTH_LOGIN        = 'auth.login';
    public const ACTION_AUTH_LOGIN_FAILED = 'auth.login_failed';
    public const ACTION_AUTH_LOGOUT       = 'auth.logout';
    public const ACTION_AUTH_REGISTER     = 'auth.register';

    // Comptes bancaires
    public const ACTION_ACCOUNT_CREATE   = 'account.create';
    public const ACTION_ACCOUNT_DELETE   = 'account.delete';
    public const ACTION_ACCOUNT_FREEZE   = 'account.freeze';
    public const ACTION_ACCOUNT_UNFREEZE = 'account.unfreeze';

    // Transactions
    public const ACTION_TRANSACTION_CREATE = 'transaction.create';
    public const ACTION_TRANSACTION_DELETE = 'transaction.delete';

    // Virements
    public const ACTION_TRANSFER_CREATE           = 'transfer.create';
    public const ACTION_TRANSFER_CANCEL           = 'transfer.cancel';
    public const ACTION_TRANSFER_RECURRING_CREATE = 'transfer_recurring.create';
    public const ACTION_TRANSFER_RECURRING_CANCEL = 'transfer_recurring.cancel';

    // Prélèvements
    public const ACTION_DIRECT_DEBIT_CREATE = 'direct_debit.create';
    public const ACTION_DIRECT_DEBIT_CANCEL = 'direct_debit.cancel';
    public const ACTION_DIRECT_DEBIT_REJECT = 'direct_debit.reject';
    public const ACTION_DIRECT_DEBIT_EXEC   = 'direct_debit.execute';
    public const ACTION_DIRECT_DEBIT_FAIL   = 'direct_debit.fail';

    // Mandats
    public const ACTION_MANDATE_CREATE = 'mandate.create';
    public const ACTION_MANDATE_REVOKE = 'mandate.revoke';

    // Utilisateurs (modération)
    public const ACTION_USER_ROLE_CHANGE = 'user.role_change';
    public const ACTION_USER_SUSPEND     = 'user.suspend';
    public const ACTION_USER_BAN         = 'user.ban';
    public const ACTION_USER_ACTIVATE    = 'user.activate';

    // Accès partagés
    public const ACTION_ACCESS_GRANT  = 'access.grant';
    public const ACTION_ACCESS_REVOKE = 'access.revoke';

    // Tutelles
    public const ACTION_GUARDIANSHIP_ADD    = 'guardianship.add';
    public const ACTION_GUARDIANSHIP_REMOVE = 'guardianship.remove';

    // Comptes mineurs (création par modération)
    public const ACTION_MINOR_ACCOUNT_CREATE = 'minor_account.create';

    // Virements récurrents (cron)
    public const ACTION_TRANSFER_RECURRING_EXEC = 'transfer_recurring.execute';
    public const ACTION_TRANSFER_RECURRING_FAIL = 'transfer_recurring.fail';

    // ── Labels lisibles ─────────────────────────────────────────────────────

    public const LABELS = [
        'auth.login'               => 'Connexion',
        'auth.login_failed'        => 'Échec de connexion',
        'auth.logout'              => 'Déconnexion',
        'auth.register'            => 'Inscription',
        'account.create'           => 'Création de compte',
        'account.delete'           => 'Suppression de compte',
        'account.freeze'           => 'Compte gelé',
        'account.unfreeze'         => 'Compte dégelé',
        'transaction.create'       => 'Transaction créée',
        'transaction.delete'       => 'Transaction supprimée',
        'transfer.create'          => 'Virement créé',
        'transfer.cancel'          => 'Virement annulé',
        'transfer_recurring.create'  => 'Virement récurrent créé',
        'transfer_recurring.cancel'  => 'Virement récurrent annulé',
        'transfer_recurring.execute' => 'Virement récurrent exécuté',
        'transfer_recurring.fail'    => 'Virement récurrent échoué',
        'direct_debit.create'      => 'Prélèvement créé',
        'direct_debit.cancel'      => 'Prélèvement annulé',
        'direct_debit.reject'      => 'Prélèvement rejeté',
        'direct_debit.execute'     => 'Prélèvement exécuté',
        'direct_debit.fail'        => 'Prélèvement échoué',
        'mandate.create'           => 'Mandat créé',
        'mandate.revoke'           => 'Mandat révoqué',
        'user.role_change'         => 'Rôle modifié',
        'user.suspend'             => 'Utilisateur suspendu',
        'user.ban'                 => 'Utilisateur banni',
        'user.activate'            => 'Utilisateur réactivé',
        'access.grant'             => 'Accès accordé',
        'access.revoke'            => 'Accès révoqué',
        'guardianship.add'         => 'Tutelle ajoutée',
        'guardianship.remove'      => 'Tutelle supprimée',
        'minor_account.create'     => 'Compte mineur créé',
    ];

    /** Couleur Bootstrap associée à chaque catégorie. */
    public const BADGE_CLASSES = [
        'auth'             => 'bg-secondary',
        'account'          => 'bg-primary',
        'transaction'      => 'bg-info text-dark',
        'transfer'         => 'bg-primary',
        'transfer_recurring' => 'bg-primary',
        'direct_debit'     => 'bg-warning text-dark',
        'mandate'          => 'bg-warning text-dark',
        'user'             => 'bg-danger',
        'access'           => 'bg-success',
        'guardianship'     => 'bg-success',
        'minor_account'    => 'bg-success',
    ];

    // ── Écriture ─────────────────────────────────────────────────────────────

    /**
     * Enregistre une entrée de journal.
     * Méthode statique : appelable depuis n'importe quel contexte (contrôleur, cron…).
     * N'interrompt jamais l'opération principale en cas d'erreur.
     *
     * @param int|null $userId          Auteur de l'action (null = cron / système)
     * @param string   $action          Constante ACTION_* de cette classe
     * @param array    $details         Données contextuelles (sérialisées en JSON)
     * @param int|null $targetUserId    Utilisateur cible (facultatif)
     * @param int|null $targetAccountId Compte cible (facultatif)
     * @param string|null $ip           IP (déduite automatiquement si null)
     */
    public static function log(
        ?int   $userId,
        string $action,
        array  $details         = [],
        ?int   $targetUserId    = null,
        ?int   $targetAccountId = null,
        ?string $ip             = null
    ): void {
        try {
            (new self())->create([
                'user_id'           => $userId,
                'action'            => $action,
                'details'           => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'target_user_id'    => $targetUserId,
                'target_account_id' => $targetAccountId,
                'ip_address'        => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
        } catch (\Throwable) {
            // L'audit ne doit jamais bloquer l'opération principale.
        }
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Compte le nombre d'entrées correspondant aux filtres.
     *
     * @param array $filters  ['action'=>?, 'username'=>?, 'date_from'=>?, 'date_to'=>?, 'target_account_id'=>?]
     */
    public function countFiltered(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT COUNT(*) FROM `audit_logs`
                LEFT JOIN `users` AS u ON u.id = audit_logs.user_id
                $where";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne une page d'entrées enrichies (username de l'auteur).
     */
    public function getFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $sql = "SELECT
                    audit_logs.*,
                    u.username   AS actor_name,
                    tu.username  AS target_name
                FROM `audit_logs`
                LEFT JOIN `users` AS u  ON u.id  = audit_logs.user_id
                LEFT JOIN `users` AS tu ON tu.id = audit_logs.target_user_id
                $where
                ORDER BY audit_logs.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Privé ────────────────────────────────────────────────────────────────

    /** Construit la clause WHERE + les paramètres PDO pour les filtres. */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['action'])) {
            $conditions[] = 'audit_logs.action = :action';
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['username'])) {
            $conditions[] = 'u.username LIKE :username';
            $params[':username'] = '%' . $filters['username'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'DATE(audit_logs.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'DATE(audit_logs.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['target_account_id'])) {
            $conditions[] = 'audit_logs.target_account_id = :ta_id';
            $params[':ta_id'] = (int) $filters['target_account_id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    // ── Helpers statiques ────────────────────────────────────────────────────

    /** Retourne le badge CSS pour un type d'action. */
    public static function badgeClass(string $action): string
    {
        $category = explode('.', $action)[0] ?? '';
        return self::BADGE_CLASSES[$category] ?? 'bg-secondary';
    }

    /** Retourne le libellé lisible d'un type d'action. */
    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
