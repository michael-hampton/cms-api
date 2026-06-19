<?php if (!empty($cssVariables)): ?>
<style data-public-content-design-tokens="<?= htmlspecialchars((string) $siteSlug, ENT_QUOTES, 'UTF-8') ?>">
:root {
<?php foreach ($cssVariables as $name => $value): ?>
    <?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>;
<?php endforeach; ?>
}
</style>
<?php endif; ?>
