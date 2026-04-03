<?php
/**
 * Button component.
 *
 * @var string $label Button text.
 * @var string $variant 'primary' | 'secondary' | 'danger'. Defaults to 'primary'.
 * @var string|null $id DOM id.
 * @var string|null $type 'button' | 'submit'. Defaults to 'button'.
 * @var string|null $onClick Inline onclick JS.
 * @var bool $disabled Whether disabled.
 * @var string|null $style Extra inline style.
 * @var string|null $class Extra CSS classes.
 */
$label = $label ?? '';
$variant = $variant ?? 'primary';
$btnId = $id ?? null;
$type = $type ?? 'button';
$onClick = $onClick ?? '';
$disabled = $disabled ?? false;
$style = $style ?? '';
$class = $class ?? '';
?>
<button
        type="<?= htmlspecialchars($type) ?>"
        class="btn btn-<?= htmlspecialchars($variant) ?> <?= htmlspecialchars($class) ?>"
        <?= $btnId ? 'id="' . htmlspecialchars($btnId) . '"' : '' ?>
        <?= $onClick ? 'onclick="' . htmlspecialchars($onClick) . '"' : '' ?>
        <?= $disabled ? 'disabled' : '' ?>
        <?= $style ? 'style="' . htmlspecialchars($style) . '"' : '' ?>
>
    <?= htmlspecialchars($label) ?>
</button>