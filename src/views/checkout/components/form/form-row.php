<?php
/**
 * Form row — wraps children in a responsive grid.
 *
 * This replaces the repeated <div class="form-row"> pattern used throughout
 * checkout.php, subscription.php, billing-form.php, etc.
 *
 * Because the template engine cannot pass child markup through @include variables
 * in a clean way, the component supports two usage modes:
 *
 * MODE A – pre-rendered content passed as a variable:
 * @include('checkout/components/form/form-row', ['content' => $html])
 *
 * MODE B – bare wrapper (open the div yourself, close with </div>):
 *   This is the common case; just use the CSS class directly if you need
 *   the div opened inline. This file documents the canonical class name.
 *
 * @var int|null $cols 2 (default) or 1.
 * @var string|null $content Pre-rendered inner HTML.
 * @var string|null $class Extra CSS classes.
 * @var string|null $style Extra inline styles.
 */
$cols = $cols ?? 2;
$content = $content ?? '';
$class = $class ?? '';
$style = $style ?? '';

$gridStyle = 'grid-template-columns: ' . ($cols === 1 ? '1fr' : '1fr 1fr') . ';';
if ($style) {
    $gridStyle .= ' ' . $style;
}
?>
<div class="form-row<?= $class ? ' ' . htmlspecialchars($class) : '' ?>"
     style="<?= htmlspecialchars($gridStyle) ?>">
    <?= $content ?>
</div>