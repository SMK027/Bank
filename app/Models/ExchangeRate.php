<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle ExchangeRate : cache des taux de change (couple base→target).
 */
class ExchangeRate extends Model
{
    protected string $table = 'exchange_rates';

    /**
     * Récupère le taux en cache pour le couple donné, ou null s'il n'existe pas.
     */
    public function findPair(string $base, string $target): ?array
    {
        return $this->findOneBy([
            'base_currency'   => $base,
            'target_currency' => $target,
        ]);
    }

    /**
     * Insère ou met à jour le taux pour un couple base/target.
     */
    public function upsertPair(string $base, string $target, float $rate, string $fetchedAt): void
    {
        $existing = $this->findPair($base, $target);
        if ($existing) {
            $this->update((int) $existing['id'], [
                'rate'       => $rate,
                'fetched_at' => $fetchedAt,
            ]);
        } else {
            $this->create([
                'base_currency'   => $base,
                'target_currency' => $target,
                'rate'            => $rate,
                'fetched_at'      => $fetchedAt,
            ]);
        }
    }
}
