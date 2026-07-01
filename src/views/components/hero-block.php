<?php
$type = (string) ($type ?? 'image');
$imageUrl = $imageUrl ?? null;
$videoUrl = $videoUrl ?? null;
$title = (string) ($title ?? '');
$hasImage = $type === 'image' && !empty($imageUrl);
$style = $hasImage ? ' style="background-image: url(\'' . htmlspecialchars((string) $imageUrl, ENT_QUOTES, 'UTF-8') . '\');"' : '';
$classes = 'hero-block' . ($hasImage ? ' hero-block--has-image' : '');
?>
<div class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>"<?= $style ?>>
    <?php if ($type === 'video' && !empty($videoUrl)): ?>
        <video class="hero-block__video" src="<?= htmlspecialchars((string) $videoUrl, ENT_QUOTES, 'UTF-8') ?>" autoplay muted loop playsinline></video>
    <?php endif; ?>
    <div class="hero-content">
        <h1 class="hero-content__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
</div>