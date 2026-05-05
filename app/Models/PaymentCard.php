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

    /** Cartes d'un utilisateur. */
    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId], 'created_at', 'DESC');
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
