<div class="error-page">
    <h1><?= $code ?></h1>
    <p><?= e($message) ?></p>
    <a href="/" class="btn btn-primary">Retour à l'accueil</a>
    <?php if (!empty($debug)): ?>
        <pre style="margin-top: 2rem; text-align: left; max-width: 800px; overflow-x: auto;"><?= htmlspecialchars($debug, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
</div>
