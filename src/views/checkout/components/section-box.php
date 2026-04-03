<?php
/**
 * Section box — a white card wrapper with an optional title.
 *
 * @var string|null $title Optional section heading.
 * @var string|null $id Optional id attribute on the wrapper div.
 * @var string|null $class Extra CSS classes for the outer wrapper.
 * @var string $content Inner HTML content (passed as variable from @include caller).
 * @var bool $sticky If true, makes the box sticky at top: 100px (for sidebars).
 */
$sectionTitle = $title ?? null;
$sectionId = $id ?? null;
$extraClass = $class ?? '';
$sticky = $sticky ?? false;

$stickyStyle = $sticky ? 'position: sticky; top: 100px;' : '';
?>
<style>
    .section-box {
        background: white;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: var(--shadow);
        height: fit-content;
    }

    .section-box-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border-color);
    }
</style>
<div class="section-box <?= htmlspecialchars($extraClass) ?>"
        <?= $sectionId ? 'id="' . htmlspecialchars($sectionId) . '"' : '' ?>
     style="<?= $stickyStyle ?>">
    <?php if ($sectionTitle): ?>
        <div class="section-box-title"><?= htmlspecialchars($sectionTitle) ?></div>
    <?php endif; ?>
    <?= $content ?? '' ?>
</div>