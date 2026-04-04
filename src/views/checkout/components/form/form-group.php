<?php
/**
 * FormControl — label + input (or select/textarea) + validation message.
 *
 * @var string $name Input name attribute.
 * @var string|null $label Label text. If null, no label is rendered.
 * @var string|null $type Input type (text, email, tel, password, number). Defaults to 'text'.
 * @var string|null $value Pre-filled value.
 * @var bool $required Whether field is required.
 * @var string|null $placeholder Placeholder text.
 * @var string|null $id Input id. Defaults to "field-{$name}".
 * @var string|null $class Extra CSS classes on the input.
 * @var string|null $errorId Id for the error span. Defaults to "error-{$name}".
 * @var array|null $attrs Extra HTML attributes as key => value array.
 */
$name = $name ?? '';
$label = $label ?? null;
$type = $type ?? 'text';
$value = $value ?? '';
$required = $required ?? false;
$placeholder = $placeholder ?? '';
$inputId = $id ?? 'field-' . $name;
$extraClass = $class ?? '';
$errorId = $errorId ?? 'error-' . $name;
$attrs = $attrs ?? [];

$attrStr = '';
foreach ($attrs as $attrKey => $attrVal) {
    $attrStr .= ' ' . htmlspecialchars($attrKey) . '="' . htmlspecialchars($attrVal) . '"';
}
?>
<div class="form-group">
    <?php if ($label): ?>
        <label class="form-label" for="<?= htmlspecialchars($inputId) ?>">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?><span class="required">*</span><?php endif; ?>
        </label>
    <?php endif; ?>
    <input
            type="<?= htmlspecialchars($type) ?>"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($inputId) ?>"
            class="form-input <?= htmlspecialchars($extraClass) ?>"
            value="<?= htmlspecialchars($value) ?>"
            placeholder="<?= htmlspecialchars($placeholder) ?>"
            <?= $required ? 'required' : '' ?>
            <?= $attrStr ?>
    >
    <span class="form-error" id="<?= htmlspecialchars($errorId) ?>"></span>
</div>