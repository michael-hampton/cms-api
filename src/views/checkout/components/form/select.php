<?php
/**
 * Select component.
 *
 * @var string $name Select name attribute.
 * @var string|null $label Label text.
 * @var array $options ['value' => 'label'] pairs.
 * @var string|null $selected Currently selected value.
 * @var bool $required Whether field is required.
 * @var string|null $id Element id. Defaults to "field-{$name}".
 * @var string|null $errorId Error span id. Defaults to "error-{$name}".
 * @var string|null $onChange Inline onchange JS string.
 * @var bool $blank Prepend a blank 'Select...' option.
 * @var string|null $blankLabel Label for the blank option. Defaults to 'Select...'
 */
$name = $name ?? '';
$label = $label ?? null;
$options = $options ?? [];
$selected = $selected ?? '';
$required = $required ?? false;
$inputId = $id ?? 'field-' . $name;
$errorId = $errorId ?? 'error-' . $name;
$onChange = $onChange ?? '';
$blank = $blank ?? false;
$blankLabel = $blankLabel ?? 'Select...';
?>
<div class="form-group">
    <?php if ($label): ?>
        <label class="form-label" for="<?= htmlspecialchars($inputId) ?>">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?><span class="required">*</span><?php endif; ?>
        </label>
    <?php endif; ?>
    <select
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($inputId) ?>"
            class="form-select"
            <?= $required ? 'required' : '' ?>
            <?= $onChange ? 'onchange="' . htmlspecialchars($onChange) . '"' : '' ?>
    >
        <?php if ($blank): ?>
            <option value=""><?= htmlspecialchars($blankLabel) ?></option>
        <?php endif; ?>
        <?php foreach ($options as $val => $optLabel): ?>
            <option value="<?= htmlspecialchars($val) ?>"
                    <?= (string)$val === (string)$selected ? 'selected' : '' ?>>
                <?= htmlspecialchars($optLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <span class="form-error" id="<?= htmlspecialchars($errorId) ?>"></span>
</div>