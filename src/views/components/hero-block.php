<?php
// Initialize variable bindings with safe fallback defaults
$type = (string) ($type ?? 'image');
$imageUrl = $imageUrl ?? null;
$videoUrl = $videoUrl ?? null;
$title = (string) ($title ?? '');
$subtitle = (string) ($subtitle ?? '');

// Capture incoming layout option choice from database pipeline context
$titlePosition = (string) ($heroTitlePosition ?? $title_position ?? 'standard');

$hasImage = $type === 'image' && !empty($imageUrl);
$hasVideo = $type === 'video' && !empty($videoUrl);

// Compile explicit layout state classes using standard BEM conventions
$classes = [
        'hero-block',
        'hero-block--layout-' . $titlePosition,
        'hero-block--type-' . $type
];

if ($hasImage) { $classes[] = 'hero-block--has-image'; }
if ($hasVideo) { $classes[] = 'hero-block--has-video'; }

$classString = implode(' ', $classes);
?>

<div class="<?= htmlspecialchars($classString, ENT_QUOTES, 'UTF-8') ?>">

    <?php if ($hasImage || $hasVideo): ?>
        <div class="hero-block__media">
            <?php if ($type === 'video' && $hasVideo): ?>
                <video class="hero-block__video" src="<?= htmlspecialchars((string) $videoUrl, ENT_QUOTES, 'UTF-8') ?>" autoplay muted loop playsinline></video>
            <?php elseif ($type === 'image' && $hasImage): ?>
                <img class="hero-block__image" src="<?= htmlspecialchars((string) $imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($title) || !empty($subtitle)): ?>
        <div class="hero-block__content">
            <h1 class="hero-block__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if (!empty($subtitle)): ?>
                <p class="hero-block__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>