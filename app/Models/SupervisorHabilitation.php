<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SupervisorHabilitation extends Model
{
    protected string $table = 'supervisor_habilitations';

    public function grantAuthorization(int $supervisorDbId, string $featureKey): int
    {
        $existing = $this->findOneBy([
            'supervisor_id' => $supervisorDbId,
            'feature_key'   => $featureKey,
        ]);

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->create([
            'supervisor_id' => $supervisorDbId,
            'feature_key'   => mb_substr(trim($featureKey), 0, 60),
        ]);
    }

    public function revokeAuthorization(int $supervisorDbId, string $featureKey): bool
    {
        $existing = $this->findOneBy([
            'supervisor_id' => $supervisorDbId,
            'feature_key'   => $featureKey,
        ]);

        if ($existing === null) {
            return false;
        }

        return $this->delete((int) $existing['id']);
    }

    public function hasAuthorization(int $supervisorDbId, string $featureKey): bool
    {
        return $this->findOneBy([
            'supervisor_id' => $supervisorDbId,
            'feature_key'   => $featureKey,
        ]) !== null;
    }

    public function getAuthorizationsForSupervisor(int $supervisorDbId): array
    {
        return $this->findBy(['supervisor_id' => (string) $supervisorDbId], 'feature_key', 'ASC');
    }
}