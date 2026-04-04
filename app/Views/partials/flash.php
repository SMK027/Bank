<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <span><?= e($flash['message']) ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>
