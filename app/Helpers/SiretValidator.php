<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Vérifie un numéro SIRET auprès de l'API Recherche Entreprises
 * du gouvernement français (https://recherche-entreprises.api.gouv.fr).
 *
 * Retourne les informations de l'établissement ou null si invalide.
 */
class SiretValidator
{
    private const API_URL = 'https://recherche-entreprises.api.gouv.fr/search';

    /**
     * Vérifie le format d'un SIRET (14 chiffres + algorithme de Luhn).
     */
    public static function isValidFormat(string $siret): bool
    {
        $siret = preg_replace('/\s+/', '', $siret);
        if (!preg_match('/^\d{14}$/', $siret)) {
            return false;
        }
        return self::luhnCheck($siret);
    }

    /**
     * Vérifie l'algorithme de Luhn adapté au SIRET.
     * Les positions paires (0-indexées) sont doublées.
     */
    private static function luhnCheck(string $number): bool
    {
        $sum = 0;
        $len = strlen($number);
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $number[$i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        return $sum % 10 === 0;
    }

    /**
     * Interroge l'API gouvernementale pour vérifier l'existence du SIRET
     * et l'activité de l'entreprise / établissement.
     *
     * @return array{valid: bool, company_name: string|null, active: bool|null, error: string|null}
     */
    public static function verify(string $siret): array
    {
        $siret = preg_replace('/\s+/', '', $siret);

        if (!self::isValidFormat($siret)) {
            return ['valid' => false, 'company_name' => null, 'active' => null, 'error' => 'Format SIRET invalide (14 chiffres requis).'];
        }

        $url = self::API_URL . '?' . http_build_query(['q' => $siret]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 8,
                'header'  => "Accept: application/json\r\nUser-Agent: BankApp/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['valid' => false, 'company_name' => null, 'active' => null, 'error' => 'Impossible de contacter le service de vérification SIRET. Réessayez ultérieurement.'];
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['results'])) {
            return ['valid' => false, 'company_name' => null, 'active' => null, 'error' => 'SIRET introuvable dans le répertoire SIRENE.'];
        }

        // Chercher l'établissement correspondant exactement au SIRET
        foreach ($data['results'] as $enterprise) {
            $companyName = $enterprise['nom_complet']
                ?? $enterprise['nom_raison_sociale']
                ?? null;

            // Statut administratif de l'entreprise (unité légale)
            $enterpriseActive = ($enterprise['etat_administratif'] ?? '') === 'A';

            // Chercher dans les établissements correspondants
            $matchingEtab = $enterprise['matching_etablissements'] ?? [];
            foreach ($matchingEtab as $etab) {
                if (($etab['siret'] ?? '') === $siret) {
                    $etabActive = ($etab['etat_administratif'] ?? '') === 'A';
                    $isActive   = $enterpriseActive && $etabActive;

                    if (!$isActive) {
                        return [
                            'valid'        => false,
                            'company_name' => $companyName,
                            'active'       => false,
                            'error'        => 'L\'entreprise ou l\'établissement n\'est plus en activité (statut : cessé).',
                        ];
                    }

                    return ['valid' => true, 'company_name' => $companyName, 'active' => true, 'error' => null];
                }
            }

            // Vérifier aussi le siège
            $siege = $enterprise['siege'] ?? [];
            if (($siege['siret'] ?? '') === $siret) {
                $siegeActive = ($siege['etat_administratif'] ?? '') === 'A';
                $isActive    = $enterpriseActive && $siegeActive;

                if (!$isActive) {
                    return [
                        'valid'        => false,
                        'company_name' => $companyName,
                        'active'       => false,
                        'error'        => 'L\'entreprise ou l\'établissement n\'est plus en activité (statut : cessé).',
                    ];
                }

                return ['valid' => true, 'company_name' => $companyName, 'active' => true, 'error' => null];
            }
        }

        return ['valid' => false, 'company_name' => null, 'active' => null, 'error' => 'SIRET introuvable dans le répertoire SIRENE.'];
    }
}
