<?php
/**
 * Button component.
 *
 * @var string $label Button text.
 * @var string $variant 'primary' | 'secondary' | 'danger'. Defaults to 'primary'.
 * @var string|null $id DOM id.
 * @var string|null $type 'button' | 'submit' | 'reset'. Defaults to 'button'.
 * @var string|null $onclick Inline onclick JS.
 * @var bool $disabled Whether disabled.
 * @var string|null $style Extra inline style.
 * @var string|null $class Extra CSS classes.
 *
 * IMPORTANT: Every variable is declared with a safe ?? default as the very first
 * thing this file does. This prevents scope pollution from previous @include calls
 * (e.g. $type = 'tel' leaking in from a form-group include) silently corrupting
 * the rendered output — the root cause of the original "submit button rendered as
 * type=tel" bug. The same defensive pattern must be followed in every component.
 */
$label = $label ?? '';
$variant = $variant ?? 'primary';
$btnId = $id ?? null;
$type = (isset($type) && in_array($type, ['button', 'submit', 'reset'], true))
        ? $type : 'button';
$onClick = $onclick ?? $onclick ?? '';
$disabled = isset($disabled) ? (bool)$disabled : false;
$style = $style ?? '';
$class = $class ?? '';
?>
<button
        type="<?= htmlspecialchars($type) ?>"
        class="btn btn-<?= htmlspecialchars($variant) ?><?= $class ? ' ' . htmlspecialchars($class) : '' ?>"
        <?= $btnId ? 'id="' . htmlspecialchars($btnId) . '"' : '' ?>
        <?= $onClick ? 'onclick="' . $onClick . '"' : '' ?>
        <?= $disabled ? 'disabled' : '' ?>
        <?= $style ? 'style="' . htmlspecialchars($style) . '"' : '' ?>
>
    <?= htmlspecialchars($label) ?>
</button>