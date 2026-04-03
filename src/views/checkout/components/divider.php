<?php
/**
 * Divider component.
 *
 * @var string|null $style 'solid' (default) | 'gradient' | 'dashed'
 * @var string|null $margin CSS margin shorthand, defaults to '1rem 0'
 */
$dividerStyle = $style ?? 'solid';
$dividerMargin = $margin ?? '1rem 0';
?>
<?php if ($dividerStyle === 'gradient'): ?>
    <div style="height: 1px; background: linear-gradient(90deg, #2563eb22, #e2e8f0, transparent); margin: <?= htmlspecialchars($dividerMargin) ?>;"></div>
<?php elseif ($dividerStyle === 'dashed'): ?>
    <div style="height: 0; border-top: 1px dashed var(--border-color); margin: <?= htmlspecialchars($dividerMargin) ?>;"></div>
<?php else: ?>
    <div style="height: 1px; background: var(--border-color); margin: <?= htmlspecialchars($dividerMargin) ?>;"></div>
<?php endif; ?>