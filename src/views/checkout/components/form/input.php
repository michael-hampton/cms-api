<?php
/**
 * Input component — renders a single <input> with label and validation message.
 *
 * Extracted from form-group.php. Use this for all standard text/email/tel/etc.
 * inputs, including the address lookup field.
 *
 * @var string $name Input name attribute.
 * @var string|null $label Visible label text. Omit to suppress the <label>.
 * @var string $type Input type. Defaults to 'text'.
 * @var string $value Current value.
 * @var bool $required Appends a required marker and the HTML required attr.
 * @var string $placeholder Placeholder text.
 * @var string|null $id DOM id. Defaults to "field-{$name}".
 * @var string|null $class Extra CSS classes for the <input>.
 * @var string|null $errorId ID of the error <span>. Defaults to "error-{$name}".
 * @var array $attrs Additional HTML attributes as key→value pairs.
 *
 * DEFENSIVE DEFAULTS: every variable is initialised with ?? here so that scope
 * pollution from a preceding @include cannot corrupt this component's output.
 */
$name = $name ?? '';
$type = $type ?? 'text';
$value = $value ?? '';
$required = $required ?? false;
$placeholder = $placeholder ?? '';
$inputId = $id ?? 'field-' . $name;
$extraClass = $class ?? '';
$attrs = $attrs ?? [];

$attrStr = '';
foreach ($attrs as $attrKey => $attrVal) {
    $attrStr .= ' ' . htmlspecialchars($attrKey) . '="' . htmlspecialchars($attrVal) . '"';
}
?>
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