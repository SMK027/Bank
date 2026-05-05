<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Client API d'une plateforme tierce autorisée à utiliser l'API de paiement.
 * L'authentification se fait via une clé publique (api_key) et un secret
 * (api_secret) transmis en HTTP Basic Auth.
 */
class ApiClient extends Model
{
    protected string $table = 'api_clients';

    /**
     * Crée un client API et retourne ['id' => int, 'api_key' => string, 'api_secret' => string].
     * Le secret n'est exposé qu'une seule fois.
     */
    public function provision(string $name, ?int $createdBy = null): array
    {
        $apiKey    = 'pk_' . bin2hex(random_bytes(16));
        $apiSecret = 'sk_' . bin2hex(random_bytes(24));

        $id = $this->create([
            'name'            => $name,
            'api_key'         => $apiKey,
            'api_secret_hash' => password_hash($apiSecret, PASSWORD_BCRYPT),
            'status'          => 'active',
            'created_by'      => $createdBy,
        ]);

        return ['id' => $id, 'api_key' => $apiKey, 'api_secret' => $apiSecret];
    }

    /** Authentifie un client à partir de la clé publique et du secret en clair. */
    public function authenticate(string $apiKey, string $apiSecret): ?array
    {
        $client = $this->findOneBy(['api_key' => $apiKey]);
        if (!$client || ($client['status'] ?? '') !== 'active') {
            return null;
        }
        if (!password_verify($apiSecret, $client['api_secret_hash'])) {
            return null;
        }
        return $client;
    }

    public function revoke(int $id): bool
    {
        return $this->update($id, ['status' => 'revoked']);
    }
}
