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
    public const ACTION_AUTH_LOGIN                  = 'auth.login';
    public const ACTION_AUTH_LOGIN_FAILED           = 'auth.login_failed';
    public const ACTION_AUTH_LOGOUT                 = 'auth.logout';
    public const ACTION_AUTH_REGISTER               = 'auth.register';
    public const ACTION_AUTH_PASSWORD_RESET_REQUEST = 'auth.password_reset_request';
    public const ACTION_AUTH_PASSWORD_RESET         = 'auth.password_reset';
    public const ACTION_AUTH_IP_BLOCKED             = 'auth.ip_blocked';

    // Comptes bancaires
    public const ACTION_ACCOUNT_CREATE   = 'account.create';
    public const ACTION_ACCOUNT_DELETE   = 'account.delete';
    public const ACTION_ACCOUNT_FREEZE   = 'account.freeze';
    public const ACTION_ACCOUNT_UNFREEZE = 'account.unfreeze';
    public const ACTION_ACCOUNT_DISABLE  = 'account.disable';
    public const ACTION_ACCOUNT_ENABLE   = 'account.enable';
    public const ACTION_ACCOUNT_CLOSE    = 'account.close';
    public const ACTION_ACCOUNT_HIDE     = 'account.hide';
    public const ACTION_ACCOUNT_SHOW     = 'account.show';

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
    public const ACTION_MANDATE_CREATE     = 'mandate.create';
    public const ACTION_MANDATE_REVOKE     = 'mandate.revoke';
    public const ACTION_MANDATE_RESCHEDULE = 'mandate.reschedule';

    public const ACTION_DIRECT_DEBIT_RESCHEDULE = 'direct_debit.reschedule';

    public const ACTION_RECURRING_TRANSFER_RESCHEDULE = 'recurring_transfer.reschedule';

    // Crédits
    public const ACTION_LOAN_GRANT       = 'loan.grant';
    public const ACTION_LOAN_ACCEPT      = 'loan.accept';
    public const ACTION_LOAN_REJECT      = 'loan.reject';
    public const ACTION_LOAN_RATE_UPDATE = 'loan.rate_update';
    public const ACTION_LOAN_INSTALLMENT_PAID        = 'loan.installment_paid';
    public const ACTION_LOAN_INSTALLMENT_FAILED      = 'loan.installment_failed';
    public const ACTION_LOAN_INSTALLMENT_REFUNDED    = 'loan.installment_refunded';
    public const ACTION_LOAN_INSTALLMENT_RESCHEDULED = 'loan.installment_rescheduled';
    public const ACTION_LOAN_CLOSED      = 'loan.closed';
    public const ACTION_LOAN_CANCELLED   = 'loan.cancelled';

    // Utilisateurs (modération)
    public const ACTION_USER_ROLE_CHANGE = 'user.role_change';
    public const ACTION_USER_SUSPEND     = 'user.suspend';
    public const ACTION_USER_BAN         = 'user.ban';
    public const ACTION_USER_ACTIVATE    = 'user.activate';
    public const ACTION_USER_PIN_RESET   = 'user.pin_reset';

    // Accès partagés
    public const ACTION_ACCESS_GRANT  = 'access.grant';
    public const ACTION_ACCESS_REVOKE = 'access.revoke';

    // Tutelles
    public const ACTION_GUARDIANSHIP_ADD    = 'guardianship.add';
    public const ACTION_GUARDIANSHIP_REMOVE = 'guardianship.remove';

    // Comptes mineurs (création par modération)
    public const ACTION_MINOR_ACCOUNT_CREATE = 'minor_account.create';

    // Intérêts épargne
    public const ACTION_INTEREST_CONFIRM  = 'interest.confirm';
    public const ACTION_INTEREST_RATE_SET = 'interest.rate_set';
    public const ACTION_INTEREST_RUN      = 'interest.run';

    // Virements récurrents (cron)
    public const ACTION_TRANSFER_RECURRING_EXEC = 'transfer_recurring.execute';
    public const ACTION_TRANSFER_RECURRING_FAIL = 'transfer_recurring.fail';

    // ── Labels lisibles ─────────────────────────────────────────────────────

    public const LABELS = [
        'auth.login'               => 'Connexion',
        'auth.login_failed'        => 'Échec de connexion',
        'auth.logout'              => 'Déconnexion',
        'auth.register'                  => 'Inscription',
        'auth.password_reset_request'    => 'Demande de réinitialisation de mot de passe',
        'auth.password_reset'            => 'Mot de passe réinitialisé',
        'auth.ip_blocked'                => 'IP bloquée (tentatives de connexion excessives)',
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
        'user.pin_reset'           => 'PIN réinitialisé (modération)',
        'access.grant'             => 'Accès accordé',
        'access.revoke'            => 'Accès révoqué',
        'guardianship.add'         => 'Tutelle ajoutée',
        'guardianship.remove'      => 'Tutelle supprimée',
        'minor_account.create'     => 'Compte mineur créé',
        'interest.confirm'         => 'Intérêts confirmés',
        'interest.rate_set'        => 'Taux d\'intérêt maximum configuré',
        'interest.run'             => 'Calcul des intérêts déclenché (modération)',
        'loan.grant'               => 'Crédit octroyé',
        'loan.accept'              => 'Crédit accepté',
        'loan.reject'              => 'Crédit refusé',
        'loan.rate_update'         => 'Taux de crédit modifié',
        'loan.installment_paid'    => 'Mensualité de crédit prélevée',
        'loan.installment_failed'  => 'Mensualité de crédit échouée',
        'loan.closed'              => 'Crédit soldé',
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
        'loan'             => 'bg-purple',
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
                'ip_address'        => $ip ?? self::resolveClientIp(),
            ]);
        } catch (\Throwable) {
            // L'audit ne doit jamais bloquer l'opération principale.
        }
    }

    /**
     * Résout l'IP réelle du client en tenant compte des proxies de confiance.
     *
     * X-Forwarded-For peut contenir plusieurs adresses séparées par des virgules :
     *   client, proxy1, proxy2
     * On prend la première (la plus à gauche), qui est l'IP du client d'origine.
     * On valide que c'est bien une adresse IP (filter_var) pour rejeter toute
     * valeur forgée par un attaquant qui enverrait un en-tête arbitraire.
     *
     * Note : cette résolution ne s'applique que si REMOTE_ADDR est une IP privée
     * (Gateway Docker, reverse-proxy local…), afin de ne pas faire confiance
     * à X-Forwarded-For envoyé directement par un client non proxifié.
     */
    private static function resolveClientIp(): ?string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        // Si le serveur est directement exposé (IP publique), on l'utilise telle quelle.
        if ($remoteAddr !== null && !self::isPrivateIp($remoteAddr)) {
            return $remoteAddr;
        }

        // Derrière un proxy (Docker gateway, reverse-proxy) : lire X-Forwarded-For.
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if ($xff !== null) {
            // Prendre la première IP de la chaîne (le client d'origine).
            $candidate = trim(explode(',', $xff)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remoteAddr;
    }

    /** Retourne true si l'IP est dans un espace d'adressage privé (RFC 1918 / loopback / link-local). */
    private static function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
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
