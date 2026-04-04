<?php
/**
 * Radio option card component.
 *
 * Renders a single selectable card containing a radio button + label text.
 * Used by payment-method-selector, saved-address cards, saved-card cards,
 * and any other radio-card group in the checkout flow.
 *
 * @var string $name Radio input name attribute.
 * @var string $value Radio input value.
 * @var string $id Input id (also used as label target). Defaults to "radio-{$name}-{$value}".
 * @var bool $checked Whether this option is pre-selected.
 * @var bool $selected Alias for $checked (either works).
 * @var string|null $cardClass Extra CSS class(es) on the outer label/card element.
 * @var string|null $dataAttr Raw "data-*" attribute string to add to the card, e.g. 'data-method="card"'.
 * @var string|null $onChange Inline onchange JS for the <input>.
 * @var string|null $title Primary bold line inside the card.
 * @var string|null $description Secondary smaller line inside the card.
 * @var string|null $content Arbitrary pre-rendered HTML appended after title/description.
 *                               Useful for card-number / expiry rows in saved-card lists.
 * @var string|null $badge Optional badge HTML appended after the content block.
 */
$name = $name ?? '';
$value = $value ?? '';
$inputId = $id ?? ('radio-' . $name . '-' . $value);
$checked = ($checked ?? false) || ($selected ?? false);
$cardClass = $cardClass ?? '';
$dataAttr = $dataAttr ?? '';
$onChange = $onChange ?? '';
$title = $title ?? null;
$description = $description ?? null;
$content = $content ?? null;
$badge = $badge ?? null;
?>
<label class="radio-option-card <?= htmlspecialchars($cardClass) ?>"
       for="<?= htmlspecialchars($inputId) ?>"
        <?= $dataAttr ?>>
    <input
            type="radio"
            name="<?= htmlspecialchars($name) ?>"
            id="<?= htmlspecialchars($inputId) ?>"
            value="<?= htmlspecialchars($value) ?>"
            class="radio-option-input"
            <?= $checked ? 'checked' : '' ?>
            <?= $onChange ? 'onchange="' . $onChange . '"' : '' ?>
    >
    <div class="radio-option-body">
        <?php if ($title): ?>
            <div class="radio-option-title"><?= htmlspecialchars($title) ?></div>
        <?php endif; ?>
        <?php if ($description): ?>
            <div class="radio-option-description"><?= htmlspecialchars($description) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </div>
    <?= $badge ?>
</label>