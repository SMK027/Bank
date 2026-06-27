<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Carte bancaire fictive associée à un compte non-épargne.
 */
class PaymentCard extends Model
{
    protected string $table = 'payment_cards';

    /**
     * Génère un numéro de carte fictif à 16 chiffres dont les 4 derniers
     * sont fournis par l'utilisateur. Le checksum de Luhn est appliqué pour
     * obtenir un format réaliste, et l'unicité globale est garantie en BDD.
     */
    public function generateCardNumber(string $last4): string
    {
        if (!preg_match('/^\d{4}$/', $last4)) {
            throw new \InvalidArgumentException('Les 4 derniers chiffres doivent être numériques.');
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            // Préfixe BIN factice : 4242 (réservé aux tests Stripe, n'existe pas en réalité)
            $bin    = '4242';
            $middle = '';
            for ($i = 0; $i < 7; $i++) {
                $middle .= (string) random_int(0, 9);
            }
            // Cherche le chiffre 12 qui rend le numéro complet Luhn-valide.
            for ($d = 0; $d <= 9; $d++) {
                $candidate = $bin . $middle . (string) $d . $last4;
                if (!self::isValidLuhn($candidate)) {
                    continue;
                }
                $stmt = $this->getPdo()->prepare(
                    "SELECT 1 FROM `payment_cards` WHERE card_number = ? LIMIT 1"
                );
                $stmt->execute([$candidate]);
                if ($stmt->fetchColumn() === false) {
                    return $candidate;
                }
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro de carte unique.');
    }

    /** Calcule le chiffre de contrôle Luhn d'une chaîne numérique. */
    public static function luhnCheckDigit(string $digits): string
    {
        $sum    = 0;
        $double = true;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = !$double;
        }
        return (string) ((10 - ($sum % 10)) % 10);
    }

    /** Vérifie qu'un numéro de carte est valide selon Luhn. */
    public static function isValidLuhn(string $number): bool
    {
        if (!preg_match('/^\d{13,19}$/', $number)) {
            return false;
        }
        $sum    = 0;
        $double = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $d = (int) $number[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = !$double;
        }
        return $sum % 10 === 0;
    }

    /** Normalise un numéro de carte (supprime espaces et tirets). */
    public static function normalize(string $number): string
    {
        return preg_replace('/[\s-]/', '', $number) ?? '';
    }

    /** Masque un numéro de carte : 4242 **** **** 1234 */
    public static function mask(string $number): string
    {
        $n = self::normalize($number);
        if (strlen($n) < 8) {
            return $n;
        }
        $first4 = substr($n, 0, 4);
        $last4  = substr($n, -4);
        return $first4 . ' **** **** ' . $last4;
    }

    /**
     * Calcule la date d'expiration (dernier jour du mois) à partir d'une
     * saisie MM/YY ou MM/YYYY.
     * Retourne null si le format est invalide.
     */
    public static function parseExpiry(string $input): ?string
    {
        $input = trim($input);
        if (!preg_match('/^(\d{2})\/(\d{2}|\d{4})$/', $input, $m)) {
            return null;
        }
        $month = (int) $m[1];
        $year  = (int) $m[2];
        if ($year < 100) {
            $year += 2000;
        }
        if ($month < 1 || $month > 12) {
            return null;
        }
        // Dernier jour du mois
        $last = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        return sprintf('%04d-%02d-%02d', $year, $month, $last);
    }

    /** Vérifie si une carte est expirée (expires_at dans le passé). */
    public static function isExpired(array $card): bool
    {
        if (empty($card['expires_at'])) {
            return false;
        }
        return strtotime($card['expires_at']) < strtotime('today');
    }

    /**
     * Retourne le total dépensé via cette carte depuis la dernière remise à zéro
     * (paiements TPE succès non annulés).
     * Si aucune remise à zéro n'a eu lieu, la borne inférieure est le 1er du mois courant.
     */
    public function getMonthlySpent(int $cardId): float
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
               FROM api_payments p
               JOIN payment_cards pc ON pc.id = p.card_id
              WHERE p.card_id      = ?
                AND p.status       = 'success'
                AND p.cancelled_at IS NULL
                AND p.created_at  >= COALESCE(pc.monthly_reset_at, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'))"
        );
        $stmt->execute([$cardId]);
        return (float) $stmt->fetchColumn();
    }

    /** Cartes d'un utilisateur. */
    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId], 'created_at', 'DESC');
    }

    /** Cartes associées à un compte donné. */
    public function getByAccount(int $accountId): array
    {
        return $this->findBy(['account_id' => (string) $accountId], 'created_at', 'DESC');
    }

    /**
     * Total des débits différés en attente liés à cette carte depuis la dernière
     * remise à zéro. Utilisé pour vérifier que l'ajout d'un nouveau débit différé
     * ne dépasse pas le plafond.
     */
    public function getPendingDeferredTotal(int $cardId): float
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COALESCE(SUM(d.amount), 0)
               FROM deferred_debits d
               JOIN payment_cards pc ON pc.id = d.card_id
              WHERE d.card_id      = ?
                AND d.status       = 'pending'
                AND d.operation_date >= COALESCE(pc.monthly_reset_at, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'))"
        );
        $stmt->execute([$cardId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total des transactions manuelles de dépense liées à cette carte depuis la
     * dernière remise à zéro (immédiates et programmées confondues).
     */
    public function getManualTransactionTotal(int $cardId): float
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COALESCE(SUM(t.amount), 0)
               FROM transactions t
               JOIN payment_cards pc ON pc.id = t.card_id
              WHERE t.card_id  = ?
                AND t.type     = 'expense'
                AND t.created_at >= COALESCE(pc.monthly_reset_at, DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'))"
        );
        $stmt->execute([$cardId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Total mensuel complet : paiements TPE + débits différés pending + transactions
     * manuelles de dépense du mois en cours.
     * Si un modérateur a défini un override pour le mois calendaire en cours,
     * cette valeur est utilisée à la place du total calculé.
     * C'est la valeur à comparer au plafond mensuel de la carte.
     */
    public function getMonthlyTotal(int $cardId): float
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT monthly_spent_override, monthly_spent_override_month
               FROM payment_cards WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$cardId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row
            && $row['monthly_spent_override'] !== null
            && $row['monthly_spent_override_month'] === date('Y-m')
        ) {
            return (float) $row['monthly_spent_override'];
        }
        return $this->getMonthlySpent($cardId)
            + $this->getPendingDeferredTotal($cardId)
            + $this->getManualTransactionTotal($cardId);
    }

    /**
     * Définit ou supprime l'override modérateur du plafond dépensé pour le mois courant.
     * Passer null efface l'override et rétablit le calcul automatique.
     */
    public function setMonthlySpentOverride(int $cardId, ?float $value): void
    {
        if ($value === null) {
            $this->update($cardId, [
                'monthly_spent_override'       => null,
                'monthly_spent_override_month' => null,
            ]);
        } else {
            $this->update($cardId, [
                'monthly_spent_override'       => $value,
                'monthly_spent_override_month' => date('Y-m'),
            ]);
        }
    }

    /**
     * Restitue un montant sur le plafond mensuel dépensé de la carte, suite à
     * une annulation ou un remboursement (partiel ou total).
     *
     * Comportement selon le mode actif :
     * - Override actif pour le mois courant → décrémente l'override du montant
     *   (plancher à 0). Couvre annulations ET remboursements partiels.
     * - Mode dynamique + annulation complète → aucune action nécessaire :
     *   `getMonthlySpent()` exclut déjà les paiements dont `cancelled_at IS NOT NULL`.
     * - Mode dynamique + remboursement partiel → le paiement n'est pas annulé,
     *   donc `getMonthlySpent()` le compterait toujours. On crée un override = total
     *   actuel - montant remboursé pour que la restitution soit visible.
     *
     * @param int   $cardId        ID de la carte
     * @param float $amount        Montant à restituer (positif)
     * @param bool  $isCancellation true si le paiement sera marqué cancelled_at
     *                              (mode dynamique suffisant, pas d'override à créer)
     */
    public function restoreMonthlySpent(int $cardId, float $amount, bool $isCancellation = false): void
    {
        if ($amount <= 0) {
            return;
        }

        $stmt = $this->getPdo()->prepare(
            'SELECT monthly_spent_override, monthly_spent_override_month
               FROM payment_cards WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$cardId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $overrideActive = $row
            && $row['monthly_spent_override'] !== null
            && $row['monthly_spent_override_month'] === date('Y-m');

        if ($overrideActive) {
            // Override présent : décrémenter directement.
            $this->setMonthlySpentOverride(
                $cardId,
                max(0.0, round((float) $row['monthly_spent_override'] - $amount, 2))
            );
        } elseif (!$isCancellation) {
            // Remboursement partiel en mode dynamique : le paiement reste dans
            // api_payments avec cancelled_at = NULL, donc getMonthlySpent() le
            // compterait toujours. Créer un override reflétant la restitution.
            $currentTotal = $this->getMonthlyTotal($cardId);
            $this->setMonthlySpentOverride(
                $cardId,
                max(0.0, round($currentTotal - $amount, 2))
            );
        }
        // Annulation en mode dynamique : cancelled_at est posé juste après cet appel,
        // getMonthlySpent() exclura automatiquement ce paiement. Rien à faire.
    }

    /**
     * Remet le plafond mensuel dépensé à zéro en posant la date courante
     * comme nouvelle borne inférieure de comptage.
     * Efface également l'override modérateur le cas échéant.
     */
    public function resetMonthlySpent(int $cardId): void
    {
        $this->update($cardId, [
            'monthly_reset_at'             => date('Y-m-d H:i:s'),
            'monthly_spent_override'       => null,
            'monthly_spent_override_month' => null,
        ]);
    }

    /** Recherche par numéro complet. */
    public function findByNumber(string $number): ?array
    {
        return $this->findOneBy(['card_number' => self::normalize($number)]);
    }

    /** Vérifie qu'un utilisateur n'a pas déjà enregistré une carte avec ces 4 derniers chiffres. */
    public function userHasLast4(int $userId, string $last4): bool
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT 1 FROM `payment_cards` WHERE user_id = ? AND last4 = ? LIMIT 1"
        );
        $stmt->execute([$userId, $last4]);
        return $stmt->fetchColumn() !== false;
    }
}
