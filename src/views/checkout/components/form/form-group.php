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
        @include('checkout/components/form/textarea', ['name' => $name, 'value' => $value, 'required' => $required, 'placeholder' => $placeholder, 'inputId' => $inputId, 'class' => $extraClass, 'attrs' => $attrs])
    <?php else: ?>
        @include('checkout/components/form/input', ['name' => $name, 'value' => $value, 'type' => $type, 'required' => $required, 'placeholder' => $placeholder, 'inputId' => $inputId, 'class' => $extraClass, 'attrs' => $attrs])
    <?php endif; ?>
    <span class="form-error" id="<?= htmlspecialchars($errorId) ?>"></span>
</div>