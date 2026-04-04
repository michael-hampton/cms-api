<?php
/**
 * Stripe card element component.
 *
 * @var string|null $sectionId DOM id for the wrapper div. Defaults to 'new-card-section'.
 * @var string|null $sectionTitle Heading text. Defaults to 'Card Details'.
 * @var string|null $backBtnOnClick JS onclick for the back button. Defaults to 'showSavedCards()'.
 * @var bool $showBackButton Whether to render the "Back to Saved Cards" button at all.
 *                                   Defaults to true — the button is hidden via CSS until JS reveals it.
 */
$sectionId = $sectionId ?? 'new-card-section';
$sectionTitle = $sectionTitle ?? 'Card Details';
$backBtnOnClick = $backBtnOnClick ?? 'showSavedCards()';
$showBackButton = $showBackButton ?? true;
?>
<style>
    #card-errors:not(:empty) {
        margin-top: .5rem;
        padding: .75rem 1rem;
        background: #fee2e2;
        border: 1px solid #ef4444;
        border-radius: .375rem;
        color: #991b1b;
        font-size: .875rem;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .section-header .section-title {
        margin-bottom: 0;
        padding-bottom: 0;
        border: none;
    }
</style>

<div class="form-section" id="<?= htmlspecialchars($sectionId) ?>">
    <div class="section-header">
        <h2 class="section-title"><?= htmlspecialchars($sectionTitle) ?></h2>
        <?php if ($showBackButton): ?>
            <button type="button"
                    id="back-to-saved-cards-btn"
                    onclick="<?= htmlspecialchars($backBtnOnClick) ?>"
                    class="btn btn-secondary"
                    style="display: none; width: auto; padding: .5rem 1rem;">
                ← Back to Saved Cards
            </button>
        <?php endif; ?>
    </div>
    <div class="form-group full-width">
        <label class="form-label">
            Card Information <span class="required">*</span>
        </label>
        <div id="card-element"
             style="padding: .75rem; border: 1px solid var(--border-color); border-radius: .5rem;">
        </div>
        <div id="card-errors" class="form-error" role="alert" aria-live="polite"></div>
    </div>
</div>