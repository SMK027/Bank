<?php
/** @var string $action  URL de l'action POST originale */
/** @var array  $fields  Données du formulaire à rejouer */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reprise en cours…</title>
    <style>
        body { display:flex; align-items:center; justify-content:center;
               min-height:100vh; margin:0; font-family:system-ui,sans-serif;
               background:#f5f7fa; color:#334155; }
        .box { text-align:center; padding:2rem; }
        .spinner { width:2.5rem; height:2.5rem; border:3px solid #cbd5e1;
                   border-top-color:#3b82f6; border-radius:50%;
                   animation:spin .8s linear infinite; margin:1.25rem auto 0; }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<div class="box">
    <p>Authentification réussie. Reprise de l'opération&hellip;</p>
    <div class="spinner"></div>
</div>

<!-- Formulaire caché soumis automatiquement : rejoue la requête POST originale -->
<form id="bypass-replay" method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>" style="display:none;">
    <?php foreach ($fields as $name => $value): ?>
        <?php if (is_array($value)): ?>
            <?php foreach ($value as $item): ?>
                <input type="hidden"
                       name="<?= htmlspecialchars($name, ENT_QUOTES) ?>[]"
                       value="<?= htmlspecialchars((string) $item, ENT_QUOTES) ?>">
            <?php endforeach; ?>
        <?php else: ?>
            <input type="hidden"
                   name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
                   value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
</form>

<script>document.getElementById('bypass-replay').submit();</script>

<!-- Repli sans JavaScript -->
<noscript>
    <style>body { display:block; padding:2rem; }</style>
    <form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES) ?>">
        <?php foreach ($fields as $name => $value): ?>
            <?php if (is_array($value)): ?>
                <?php foreach ($value as $item): ?>
                    <input type="hidden"
                           name="<?= htmlspecialchars($name, ENT_QUOTES) ?>[]"
                           value="<?= htmlspecialchars((string) $item, ENT_QUOTES) ?>">
                <?php endforeach; ?>
            <?php else: ?>
                <input type="hidden"
                       name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
                       value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <p>JavaScript est désactivé. Cliquez pour continuer :</p>
        <button type="submit">Continuer</button>
    </form>
</noscript>
</body>
</html>
