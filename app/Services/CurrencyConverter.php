<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeRate;

/**
 * Service de conversion de devise.
 *
 * Source : open.er-api.com (API gratuite, libre de droits, sans clé).
 *          https://www.exchangerate-api.com/docs/free
 *
 * Les taux sont mis en cache en base de données pour une durée de
 * CACHE_TTL_SECONDS (par défaut 6 heures). En cas d'indisponibilité
 * de l'API, le dernier taux connu est utilisé en fallback.
 */
class CurrencyConverter
{
    /** Durée de validité du cache en secondes (6 h par défaut). */
    public const CACHE_TTL_SECONDS = 6 * 3600;

    /** URL de l'API publique (latest rates). */
    private const API_URL = 'https://open.er-api.com/v6/latest/%s';

    /** Timeout HTTP en secondes (court : ne pas bloquer la requête utilisateur). */
    private const HTTP_TIMEOUT = 4;

    private ExchangeRate $rateModel;

    /** @var callable|null Fonction injectable pour récupérer les taux (tests). */
    private $fetcher = null;

    public function __construct(?ExchangeRate $rateModel = null, ?callable $fetcher = null)
    {
        $this->rateModel = $rateModel ?? new ExchangeRate();
        $this->fetcher   = $fetcher;
    }

    /**
     * Convertit un montant d'une devise vers une autre.
     *
     * @return array{amount: float, rate: float} Montant converti et taux appliqué.
     */
    public function convert(float $amount, string $from, string $to): array
    {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));

        if ($from === $to) {
            return ['amount' => round($amount, 2), 'rate' => 1.0];
        }

        $rate = $this->getRate($from, $to);
        return [
            'amount' => round($amount * $rate, 2),
            'rate'   => $rate,
        ];
    }

    /**
     * Retourne le taux de conversion 1 unité de $from → $to.
     *
     * Lit le cache, le rafraîchit si périmé, retombe sur le cache (même expiré)
     * en cas d'erreur réseau. Lève une exception si aucun taux n'est disponible.
     */
    public function getRate(string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        $cached = $this->rateModel->findPair($from, $to);
        if ($cached !== null && !$this->isExpired($cached)) {
            return (float) $cached['rate'];
        }

        try {
            $rate = $this->fetchRate($from, $to);
            $this->rateModel->upsertPair($from, $to, $rate, date('Y-m-d H:i:s'));
            return $rate;
        } catch (\Throwable $e) {
            // Fallback : taux périmé encore acceptable plutôt que rien.
            if ($cached !== null) {
                return (float) $cached['rate'];
            }
            throw new \RuntimeException(
                "Impossible de récupérer le taux de conversion {$from}→{$to} : " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Indique si une entrée de cache est expirée (au-delà de CACHE_TTL_SECONDS).
     */
    private function isExpired(array $cached): bool
    {
        $fetchedAt = $cached['fetched_at'] ?? null;
        if (!$fetchedAt) {
            return true;
        }
        return (time() - (int) strtotime($fetchedAt)) > self::CACHE_TTL_SECONDS;
    }

    /**
     * Interroge l'API publique et retourne le taux 1 $from → $to.
     */
    private function fetchRate(string $from, string $to): float
    {
        $payload = $this->httpGetJson(sprintf(self::API_URL, urlencode($from)));

        if (!is_array($payload)
            || ($payload['result'] ?? null) !== 'success'
            || empty($payload['rates'])
            || !is_array($payload['rates'])
        ) {
            throw new \RuntimeException('Réponse API inattendue.');
        }

        if (!isset($payload['rates'][$to])) {
            throw new \RuntimeException("Devise cible « {$to} » non disponible.");
        }

        $rate = (float) $payload['rates'][$to];
        if ($rate <= 0) {
            throw new \RuntimeException('Taux invalide reçu de l\'API.');
        }

        return $rate;
    }

    /**
     * Effectue un GET HTTP et décode la réponse JSON.
     * Injectable via le constructeur pour les tests unitaires.
     */
    private function httpGetJson(string $url): mixed
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($url);
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_TIMEOUT,
                'header'  => "Accept: application/json\r\nUser-Agent: BankApp/1.0\r\n",
                'ignore_errors' => true,
            ],
            'https' => [
                'timeout' => self::HTTP_TIMEOUT,
                'header'  => "Accept: application/json\r\nUser-Agent: BankApp/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException('Échec de la requête HTTP.');
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON invalide : ' . json_last_error_msg());
        }
        return $data;
    }
}
