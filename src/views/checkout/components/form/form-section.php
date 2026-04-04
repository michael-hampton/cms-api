<?php
/**
 * Form section wrapper with optional title.
 *
 * Replaces the repeated inline <div class="form-section"> + <h2 class="section-title">
 * pattern found in checkout.php, subscription.php, billing-form.php, etc.
 *
 * Usage:
 * @include('checkout/components/form/form-section', [
 *       'title'   => 'Contact Information',
 *       'id'      => 'contact-section',       // optional
 *       'style'   => 'display: none;',        // optional
 *       'content' => $renderedHtml,           // optional – for wrapper-only use
 *   ])
 *
 * More commonly the content is supplied via surrounding template markup;
 * in that case omit 'content' and close the section in the parent template.
 *
 * @var string|null $title Section heading text (omit to skip the <h2>).
 * @var string|null $id DOM id for the wrapper div.
 * @var string|null $style Inline style for the wrapper div.
 * @var string|null $class Extra CSS classes for the wrapper div.
 * @var string|null $content Pre-rendered inner HTML (optional).
 */
$title = $title ?? null;
$id = $id ?? null;
$style = $style ?? '';
$class = $class ?? '';
$content = $content ?? null;
?>
<div class="form-section<?= $class ? ' ' . htmlspecialchars($class) : '' ?>"
        <?= $id ? 'id="' . htmlspecialchars($id) . '"' : '' ?>
        <?= $style ? 'style="' . htmlspecialchars($style) . '"' : '' ?>>
    <?php if ($title): ?>
        <h2 class="section-title"><?= htmlspecialchars($title) ?></h2>
    <?php endif; ?>
    <?= $content ?>
</div>