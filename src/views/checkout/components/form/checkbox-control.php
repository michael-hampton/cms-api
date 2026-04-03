<?php
/**
 * Checkbox control.
 *
 * @var string $name Input name.
 * @var string|null $id Input id. Defaults to "checkbox-{$name}".
 * @var string $label Label HTML (may contain links).
 * @var bool $checked Whether pre-checked.
 * @var bool $required Whether required.
 * @var string|null $value Input value. Defaults to '1'.
 * @var string|null $style Wrapper style string.
 */
$name = $name ?? '';
$inputId = $id ?? 'checkbox-' . $name;
$label = $label ?? '';
$checked = $checked ?? false;
$required = $required ?? false;
$value = $value ?? '1';
$style = $style ?? '';
?>
<label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; <?= htmlspecialchars($style) ?>">
    <input
            type="checkbox"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($inputId) ?>"
            value="<?= htmlspecialchars($value) ?>"
            <?= $checked ? 'checked' : '' ?>
            <?= $required ? 'required' : '' ?>
            style="margin-top: 0.25rem; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;"
    >
    <span style="flex: 1; font-size: 0.875rem;"><?= $label ?></span>
</label>