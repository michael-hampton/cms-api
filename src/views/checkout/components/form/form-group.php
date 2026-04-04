<?php
/**
 * FormControl — label + input (or select/textarea) + validation message.
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
<div class="form-group <?= ($type === 'textarea') ? 'full-width' : '' ?>">
    <?php if ($label): ?>
        <label class="form-label" for="<?= htmlspecialchars($inputId) ?>">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?><span class="required">*</span><?php endif; ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'textarea'): ?>
        <textarea
                name="<?= htmlspecialchars($name) ?>"
                id="<?= htmlspecialchars($inputId) ?>"
                class="form-textarea <?= htmlspecialchars($extraClass) ?>"
                placeholder="<?= htmlspecialchars($placeholder) ?>"
            <?= $required ? 'required' : '' ?>
                <?= $attrStr ?>><?= htmlspecialchars($value) ?></textarea>
    <?php else: ?>
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
    <?php endif; ?>
    <span class="form-error" id="<?= htmlspecialchars($errorId) ?>"></span>
</div>