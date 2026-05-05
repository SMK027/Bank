<?php
/** @var array $clients */
/** @var array|null $newCredentials */
?>
<div class="page-header">
    <h1><i class="bi bi-plug"></i> Clients API</h1>
    <p class="text-muted" style="margin:0;">
        Plateformes tierces autorisées à utiliser l'API de paiement par carte bancaire.
    </p>
</div>

<?php if ($newCredentials): ?>
<div class="alert alert-warning" role="alert" style="margin-bottom:1.5rem;">
    <h4 style="margin-top:0;"><i class="bi bi-key"></i> Nouvelles informations d'authentification</h4>
    <p>
        Ces identifiants ne seront affichés qu'<strong>une seule fois</strong>. Conservez-les en lieu sûr.
        Le secret n'est <em>jamais</em> stocké en clair côté plateforme.
    </p>
    <table class="table" style="background:#fff;">
        <tr>
            <th style="width:160px;">Nom</th>
            <td><?= e($newCredentials['name']) ?></td>
        </tr>
        <tr>
            <th>API Key</th>
            <td><code><?= e($newCredentials['api_key']) ?></code></td>
        </tr>
        <tr>
            <th>API Secret</th>
            <td><code><?= e($newCredentials['api_secret']) ?></code></td>
        </tr>
    </table>
    <details>
        <summary style="cursor:pointer;"><strong>Exemple d'appel</strong> (HTTP Basic)</summary>
        <pre style="background:#1e1e1e;color:#dcdcdc;padding:0.75rem;border-radius:6px;overflow:auto;"><code>curl -X POST <?= e(($_ENV['APP_URL'] ?? 'http://localhost:8080')) ?>/api/v1/payments/debit \
  -u "<?= e($newCredentials['api_key']) ?>:<?= e($newCredentials['api_secret']) ?>" \
  -H "Content-Type: application/json" \
  -d '{"card_number":"4242XXXXXXXXXXXX","amount":12.50,"currency":"EUR","comment":"Achat boutique"}'</code></pre>
    </details>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><h3><i class="bi bi-plus-circle"></i> Nouveau client API</h3></div>
    <div class="card-body">
        <form method="POST" action="/moderation/api-clients" style="display:flex;gap:0.6rem;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="text" name="name" class="form-control" placeholder="Nom de la plateforme tierce"
                   maxlength="150" required style="flex:1;min-width:240px;">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-key"></i> Provisionner
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($clients)): ?>
            <p class="text-muted text-center" style="padding:1.5rem 0;margin:0;">
                Aucun client API enregistré.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>API Key</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><?= (int) $c['id'] ?></td>
                            <td><?= e($c['name']) ?></td>
                            <td><code><?= e($c['api_key']) ?></code></td>
                            <td>
                                <?php if ($c['status'] === 'active'): ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Révoqué</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td style="text-align:right;">
                                <?php if ($c['status'] === 'active'): ?>
                                <form method="POST" action="/moderation/api-clients/<?= (int) $c['id'] ?>/revoke"
                                      style="display:inline;"
                                      onsubmit="return confirm('Révoquer ce client API ? Toutes les requêtes seront refusées.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-x-circle"></i> Révoquer
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
