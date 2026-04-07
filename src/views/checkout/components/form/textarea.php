<?php
/**
 * Textarea component — renders a single <textarea> with label and validation message.
 *
 * Extracted from form-group.php. Always spans the full grid width via the
 * `full-width` class so multi-column form rows don't clip it.
 *
 * @var string $name Textarea name attribute.
 * @var string|null $label Visible label text. Omit to suppress the <label>.
 * @var string $value Current content.
 * @var bool $required Appends a required marker and the HTML required attr.
 * @var string $placeholder Placeholder text.
 * @var string|null $id DOM id. Defaults to "field-{$name}".
 * @var string|null $class Extra CSS classes for the <textarea>.
 * @var string|null $errorId ID of the error <span>. Defaults to "error-{$name}".
 * @var array $attrs Additional HTML attributes as key→value pairs.
 *
 * DEFENSIVE DEFAULTS: every variable is initialised with ?? here so that scope
 * pollution from a preceding @include cannot corrupt this component's output.
 */
$name = $name ?? '';
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

<textarea
        name="<?= htmlspecialchars($name) ?>"
        id="<?= htmlspecialchars($inputId) ?>"
        class="form-textarea <?= htmlspecialchars($extraClass) ?>"
        placeholder="<?= htmlspecialchars($placeholder) ?>"
        <?= $required ? 'required' : '' ?>
        <?= $attrStr ?>
    ><?= htmlspecialchars($value) ?></textarea>