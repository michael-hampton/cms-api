<?php
/** @var \App\ViewModels\OpenCollab\ProfileFieldViewModel $field */

$fieldId = htmlspecialchars($field->key);
$errorId = htmlspecialchars($field->errorId());
?>

<div class="oc-form-group" style="margin-bottom:20px;">
    <label class="oc-label" for="<?= $fieldId ?>">
        <?= htmlspecialchars($field->name) ?>

        <?php if (!$field->required): ?>
            <span style="font-size:.75rem;color:var(--slate);font-weight:500;">Optional</span>
        <?php endif; ?>
    </label>

    <?php if ($field->description): ?>
        <div class="oc-help" style="margin-bottom:8px;">
            <?= htmlspecialchars($field->description) ?>
        </div>
    <?php endif; ?>

    <?php if ($field->renderType === 'textarea'): ?>
        <textarea
                class="oc-textarea"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>"
                rows="4"
                placeholder="<?= htmlspecialchars($field->placeholder) ?>"
            <?= $field->required ? 'required' : '' ?>><?= htmlspecialchars($field->stringValue) ?></textarea>

    <?php elseif ($field->renderType === 'multi_select'): ?>
        <select
                class="oc-input"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>[]"
                multiple
            <?= $field->required ? 'required' : '' ?>>

            <?php foreach ($field->options as $option): ?>
                <option
                        value="<?= htmlspecialchars($option['value']) ?>"
                    <?= $field->isSelected($option['value']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($option['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($field->renderType === 'select'): ?>
        <select
                class="oc-select"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>"
                <?= $field->required ? 'required' : '' ?>>

            <option value="">Select country…</option>

            <?php foreach ($field->options as $option): ?>
                <option
                        value="<?= htmlspecialchars($option['value']) ?>"
                        <?= $field->stringValue === $option['value'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($option['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php else: ?>
        <input
                class="oc-input"
                type="<?= htmlspecialchars($field->inputType()) ?>"
                id="<?= $fieldId ?>"
                name="<?= $fieldId ?>"
                value="<?= htmlspecialchars($field->stringValue) ?>"
                placeholder="<?= htmlspecialchars($field->placeholder) ?>"
            <?= $field->required ? 'required' : '' ?>>
    <?php endif; ?>

    <div class="oc-error-msg" id="<?= $errorId ?>"></div>
</div>